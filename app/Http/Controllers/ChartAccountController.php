<?php

namespace App\Http\Controllers;

use App\Models\ChartAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class ChartAccountController extends Controller
{
    public function index(Request $request)
    {
        return view('chart-accounts.index');
    }

    public function create()
    {
        return view('chart-accounts.create', [
            'account' => new ChartAccount(['level' => 'detail', 'nature' => 'neutral']),
            'parents' => ChartAccount::orderBy('code')->get(),
        ]);
    }

    public function store(Request $request)
    {
        ChartAccount::create($this->validated($request) + ['is_active' => true]);

        return redirect()->route('chart-accounts.index')->with('success', 'سرفصل مالی ثبت شد.');
    }

    public function edit(ChartAccount $chartAccount)
    {
        return view('chart-accounts.create', [
            'account' => $chartAccount,
            'parents' => ChartAccount::where('id', '!=', $chartAccount->id)->orderBy('code')->get(),
        ]);
    }

    public function update(Request $request, ChartAccount $chartAccount)
    {
        $chartAccount->update($this->validated($request, $chartAccount));

        return redirect()->route('chart-accounts.index')->with('success', 'سرفصل مالی ویرایش شد.');
    }

    public function destroy(ChartAccount $chartAccount)
    {
        $descendantIds = [];
        $queue = \App\Models\ChartAccount::query()
            ->where('parent_id', $chartAccount->id)
            ->pluck('id')
            ->all();

        while ($queue !== []) {
            $currentId = array_shift($queue);
            $descendantIds[] = (int) $currentId;
            foreach (\App\Models\ChartAccount::query()->where('parent_id', $currentId)->pluck('id') as $childId) {
                $queue[] = (int) $childId;
            }
        }

        $allIds = array_merge([(int) $chartAccount->id], $descendantIds);

        if ($chartAccount->is_system || \App\Models\ChartAccount::query()->whereIn('id', $allIds)->where('is_system', true)->exists()) {
            return redirect()->route('chart-accounts.index')
                ->with('error', 'این سرفصل قابل حذف نیست.')
                ->with('error_details', ['حساب سیستمی قابل حذف نیست.']);
        }

        $documentCount = (int) \App\Models\AccountingDocumentLine::query()
            ->where(function ($query) use ($allIds) {
                $query->whereIn('chart_account_id', $allIds)
                    ->orWhereIn('detail_account_id', $allIds);
            })
            ->selectRaw('COUNT(DISTINCT accounting_document_id) as aggregate')
            ->value('aggregate');

        if ($documentCount > 0) {
            return redirect()->route('chart-accounts.index')
                ->with('error', 'این سرفصل قابل حذف نیست.')
                ->with('error_details', ['این سرفصل یا زیرمجموعه‌هایش دارای گردش سند هستند: ' . number_format($documentCount)]);
        }

        try {
            DB::transaction(function () use ($descendantIds, $chartAccount) {
                foreach (array_reverse($descendantIds) as $descendantId) {
                    \App\Models\ChartAccount::query()->whereKey($descendantId)->delete();
                }
                $chartAccount->delete();
            });
        } catch (Throwable) {
            return redirect()->route('chart-accounts.index')
                ->with('error', 'امکان حذف این سرفصل مالی وجود ندارد.')
                ->with('error_details', ['ممکن است سند حسابداری یا زیرمجموعه‌ای به این حساب وصل باشد.']);
        }

        return redirect()->route('chart-accounts.index')->with('success', 'سرفصل مالی حذف شد.');
    }

    private function validated(Request $request, ?ChartAccount $account = null): array
    {
        $ignore = $account ? ',' . $account->id : '';

        return $request->validate([
            'parent_id' => 'nullable|exists:chart_accounts,id',
            'level' => 'required|in:group,ledger,subsidiary,detail',
            'code' => 'required|string|max:255|unique:chart_accounts,code' . $ignore,
            'title' => 'required|string|max:255',
            'nature' => 'required|in:debit,credit,neutral',
        ]);
    }
}
