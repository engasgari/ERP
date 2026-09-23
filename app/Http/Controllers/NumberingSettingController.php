<?php

namespace App\Http\Controllers;

use App\Services\NumberingSettingService;
use Illuminate\Http\Request;

class NumberingSettingController extends Controller
{
    public function index(NumberingSettingService $service, Request $request)
    {
        $fiscalYearId = $request->integer('fiscal_year_id') ?: null;

        return view('numbering-settings.index', [
            'rows' => $service->rowsForFiscalYear($fiscalYearId),
            'fiscalYears' => $service->fiscalYears(),
            'selectedFiscalYearId' => $fiscalYearId ?: app(\App\Services\NumberingService::class)->resolveActiveFiscalYearId(),
        ]);
    }

    public function update(Request $request, NumberingSettingService $service)
    {
        $validated = $request->validate([
            'fiscal_year_id' => 'nullable|integer|exists:fiscal_years,id',
            'rows' => 'required|array',
            'rows.*.document_key' => 'required|string|max:100',
            'rows.*.prefix' => 'nullable|string|max:50',
            'rows.*.padding' => 'nullable|integer|min:1|max:12',
            'rows.*.next_number' => 'nullable|integer|min:1',
            'rows.*.reuse_deleted_numbers' => 'nullable|boolean',
        ]);

        $service->updateRows($validated['rows'], $validated['fiscal_year_id'] ?? null);

        return redirect()
            ->route('numbering-settings.index', ['fiscal_year_id' => $validated['fiscal_year_id'] ?? null])
            ->with('success', 'تنظیمات شماره‌گذاری ذخیره شد.');
    }

    public function sync(Request $request, NumberingSettingService $service)
    {
        $validated = $request->validate([
            'fiscal_year_id' => 'nullable|integer|exists:fiscal_years,id',
        ]);

        $service->syncFromDocuments($validated['fiscal_year_id'] ?? null);

        return redirect()
            ->route('numbering-settings.index', ['fiscal_year_id' => $validated['fiscal_year_id'] ?? null])
            ->with('success', 'شمارنده‌ها با آخرین اسناد موجود همگام‌سازی شدند.');
    }
}
