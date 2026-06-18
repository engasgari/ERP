<?php

use App\Http\Controllers\AccountingDocumentController;
use App\Http\Controllers\FinancialReportController;
use App\Http\Controllers\FiscalPeriodController;
use App\Http\Controllers\TreasuryController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('accounting-documents', AccountingDocumentController::class)->except(['create', 'edit']);
    Route::post('accounting-documents/{accountingDocument}/post', [AccountingDocumentController::class, 'post']);
    Route::post('accounting-documents/{accountingDocument}/unpost', [AccountingDocumentController::class, 'unpost']);

    Route::apiResource('treasury-transactions', TreasuryController::class)->only(['index', 'store']);
    Route::get('fiscal-periods', [FiscalPeriodController::class, 'index']);

    Route::get('financial-reports/general-ledger', [FinancialReportController::class, 'generalLedger']);
    Route::get('financial-reports/trial-balance', [FinancialReportController::class, 'trialBalance']);
    Route::get('financial-reports/statement', [FinancialReportController::class, 'statement']);
    Route::get('financial-reports/balance-sheet', [FinancialReportController::class, 'balanceSheet']);
    Route::get('financial-reports/profit-loss', [FinancialReportController::class, 'profitAndLoss']);
    Route::get('financial-reports/aging', [FinancialReportController::class, 'aging']);
});
