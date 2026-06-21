<?php

namespace App\Http\Controllers;

use App\Models\AccountingDocument;
use App\Models\FiscalPeriod;
use App\Models\FiscalYear;
use App\Models\InventoryDocument;
use App\Models\Invoice;
use App\Services\FiscalPeriodService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FiscalPeriodController extends Controller
{
    public function index(Request $request)
    {
        $editingYear = null;

        if ($request->filled('edit')) {
            $editingYear = FiscalYear::find($request->integer('edit'));
        }

        return view('fiscal-periods.index', [
            'years' => FiscalYear::with(['periods' => fn ($query) => $query->orderBy('period_number')])
                ->latest('jalali_year')
                ->get(),
            'editingYear' => $editingYear,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedYearData($request);

        DB::transaction(function () use ($data) {
            $year = FiscalYear::create($data);
            $this->syncSinglePeriod($year);
            if ($year->status === 'open') {
                app(FiscalPeriodService::class)->activatePeriod($year->periods()->first());
            }
        });

        return redirect()->route('fiscal-periods.index')
            ->with('success', 'دوره مالی ثبت شد.');
    }

    public function update(Request $request, FiscalYear $fiscalYear)
    {
        $data = $this->validatedYearData($request, $fiscalYear);
        $hasRelatedDocuments = $this->relatedDocumentCounts($fiscalYear)->sum() > 0;
        $dateOrYearChanged = (int) $data['jalali_year'] !== (int) $fiscalYear->jalali_year
            || $data['start_date'] !== $fiscalYear->start_date?->toDateString()
            || $data['end_date'] !== $fiscalYear->end_date?->toDateString();
        $statusChanged = $data['status'] !== $fiscalYear->status;

        if ($hasRelatedDocuments && $dateOrYearChanged) {
            throw ValidationException::withMessages([
                'start_date' => 'برای این دوره مالی سند مرتبط ثبت شده است؛ تاریخ شروع و پایان قابل تغییر نیست.',
            ]);
        }

        DB::transaction(function () use ($fiscalYear, $data, $dateOrYearChanged, $statusChanged) {
            $fiscalYear->update($data);

            if ($dateOrYearChanged) {
                $this->syncSinglePeriod($fiscalYear);
            } elseif ($statusChanged) {
                $this->syncSinglePeriodStatus($fiscalYear);
            } else {
                $this->syncSinglePeriodTitle($fiscalYear);
            }

            if ($fiscalYear->status === 'open') {
                app(FiscalPeriodService::class)->activatePeriod($fiscalYear->periods()->first());
            } elseif ($fiscalYear->status === 'closed') {
                $fiscalYear->periods()->update(['is_active' => false]);
            }
        });

        return redirect()->route('fiscal-periods.index')
            ->with('success', 'دوره مالی ویرایش شد.');
    }

    public function destroy(FiscalYear $fiscalYear)
    {
        $relatedCounts = $this->relatedDocumentCounts($fiscalYear);

        if ($relatedCounts->sum() > 0) {
            $details = $relatedCounts
                ->filter()
                ->map(fn ($count, $label) => $label . ': ' . $count)
                ->implode('، ');

            return back()->with('error', 'این دوره مالی قابل حذف نیست. اسناد مرتبط: ' . $details);
        }

        DB::transaction(function () use ($fiscalYear) {
            $fiscalYear->periods()->delete();
            $fiscalYear->delete();
        });

        return redirect()->route('fiscal-periods.index')
            ->with('success', 'دوره مالی حذف شد.');
    }

    public function close(Request $request, FiscalPeriod $fiscalPeriod, FiscalPeriodService $service)
    {
        $service->close($fiscalPeriod, $request->user()->id);
        $fiscalPeriod->fiscalYear?->update([
            'status' => 'closed',
            'is_active' => false,
            'closed_at' => now(),
        ]);

        return back()->with('success', 'دوره مالی بسته شد.');
    }

    public function reopen(FiscalPeriod $fiscalPeriod, FiscalPeriodService $service)
    {
        $service->reopen($fiscalPeriod);
        $fiscalPeriod->fiscalYear?->update([
            'status' => 'open',
            'is_active' => true,
            'closed_at' => null,
        ]);

        return back()->with('success', 'دوره مالی دوباره باز شد.');
    }

    private function validatedYearData(Request $request, ?FiscalYear $fiscalYear = null): array
    {
        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'start_date' => ['required', 'string', 'max:20'],
            'end_date' => ['required', 'string', 'max:20'],
            'currency' => ['nullable', 'string', 'max:10'],
            'status' => ['required', Rule::in(['open', 'closed'])],
        ]);

        $startDate = jalaliToGregorianDate($validated['start_date']);
        $endDate = jalaliToGregorianDate($validated['end_date']);

        if (! $startDate) {
            throw ValidationException::withMessages(['start_date' => 'تاریخ شروع معتبر نیست.']);
        }

        if (! $endDate) {
            throw ValidationException::withMessages(['end_date' => 'تاریخ پایان معتبر نیست.']);
        }

        if ($startDate > $endDate) {
            throw ValidationException::withMessages(['end_date' => 'تاریخ پایان باید بعد از تاریخ شروع باشد.']);
        }

        $jalaliYear = (int) substr(str_replace('-', '/', normalizePersianDigits($validated['start_date'])), 0, 4);

        if ($jalaliYear < 1300 || $jalaliYear > 1500) {
            throw ValidationException::withMessages(['start_date' => 'سال تاریخ شروع معتبر نیست.']);
        }

        $duplicate = FiscalYear::where('jalali_year', $jalaliYear)
            ->when($fiscalYear, fn ($query) => $query->whereKeyNot($fiscalYear->id))
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'start_date' => 'برای سال شروع این دوره قبلاً دوره مالی تعریف شده است.',
            ]);
        }

        return [
            'title' => $validated['title'] ?: 'دوره مالی ' . $jalaliYear,
            'jalali_year' => $jalaliYear,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'currency' => $validated['currency'] ?: 'IRR',
            'status' => $validated['status'],
            'closed_at' => $validated['status'] === 'closed' ? now() : null,
        ];
    }

    private function syncSinglePeriod(FiscalYear $fiscalYear): void
    {
        $fiscalYear->periods()->delete();

        $fiscalYear->periods()->create([
            'period_number' => 1,
            'title' => $fiscalYear->title,
            'start_date' => $fiscalYear->start_date,
            'end_date' => $fiscalYear->end_date,
            'status' => $fiscalYear->status,
            'is_active' => $fiscalYear->status === 'open',
            'closed_at' => $fiscalYear->status === 'closed' ? now() : null,
        ]);
    }

    private function syncSinglePeriodStatus(FiscalYear $fiscalYear): void
    {
        $fiscalYear->periods()->update([
            'status' => $fiscalYear->status,
            'is_active' => $fiscalYear->status === 'open',
            'closed_at' => $fiscalYear->status === 'closed' ? now() : null,
            'closed_by' => null,
        ]);
    }

    private function syncSinglePeriodTitle(FiscalYear $fiscalYear): void
    {
        $fiscalYear->periods()->where('period_number', 1)->update([
            'title' => $fiscalYear->title,
        ]);
    }

    private function relatedDocumentCounts(FiscalYear $fiscalYear)
    {
        $periodIds = $fiscalYear->periods()->pluck('id');

        return collect([
            'اسناد حسابداری' => AccountingDocument::withTrashed()
                ->where(function ($query) use ($fiscalYear, $periodIds) {
                    $query->where('fiscal_year_id', $fiscalYear->id)
                        ->orWhereIn('fiscal_period_id', $periodIds);
                })
                ->count(),
            'فاکتورها' => Invoice::where('fiscal_year_id', $fiscalYear->id)->count(),
            'اسناد انبار' => InventoryDocument::where('fiscal_year_id', $fiscalYear->id)->count(),
        ]);
    }
}
