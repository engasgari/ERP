<?php
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectCostController;
use App\Http\Controllers\BomVersionController;
use App\Http\Controllers\ProductionOrderController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\SalaryController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\WorkLogController;
use App\Http\Controllers\FinancialTransactionController;
use App\Http\Controllers\ManagementReportController;
use App\Http\Controllers\ReportCenterController;
use App\Http\Controllers\AccessRoleController;
use App\Http\Controllers\AccessUserController;
use App\Http\Controllers\AccountingDocumentController;
use App\Http\Controllers\BankAccountController;
use App\Http\Controllers\ChartAccountController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FinancialReportController;
use App\Http\Controllers\FiscalPeriodController;
use App\Http\Controllers\InventoryDocumentController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\PartyController;
use App\Http\Controllers\CompanySettingController;
use App\Http\Controllers\TreasuryController;
use App\Http\Controllers\WorkShiftController;
use App\Http\Controllers\WorkCalendarController;
use App\Http\Controllers\WorkGroupController;
use App\Http\Controllers\PayrollAccountingSettingController;
use App\Http\Controllers\OrganizationUnitController;
use App\Http\Controllers\JobController;
use App\Http\Controllers\PositionController;
use App\Http\Controllers\EmploymentOrderController;
use App\Http\Controllers\EmploymentContractController;
use App\Http\Controllers\EmployeeDocumentController;
use App\Http\Controllers\PayslipController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    return redirect()->route('login');
});


Route::middleware('auth')->group(function () {
    Route::view('self-service', 'self-service.index')->name('self-service.index');
    Route::resource('parties', PartyController::class)->except(['show'])->middleware('permission:base-info.view');
    Route::get('items/import', [ItemController::class, 'importForm'])->middleware('permission:base-info.view')->name('items.import.form');
    Route::post('items/import', [ItemController::class, 'import'])->middleware('permission:base-info.view')->name('items.import');
    Route::get('items/import/template', [ItemController::class, 'downloadTemplate'])->middleware('permission:base-info.view')->name('items.import.template');
    Route::resource('items', ItemController::class)->except(['show'])->middleware('permission:base-info.view');
    Route::resource('chart-accounts', ChartAccountController::class)->except(['show'])->middleware('permission:accounting.view');
    Route::get('bank-accounts', [BankAccountController::class, 'index'])->middleware('permission:financial.manage')->name('bank-accounts.index');
    Route::get('bank-accounts/{bankAccount}/statement', [BankAccountController::class, 'statement'])
        ->whereNumber('bankAccount')
        ->middleware('permission:financial.manage')
        ->name('bank-accounts.statement');
    Route::get('company-settings', [CompanySettingController::class, 'edit'])->middleware('permission:settings.manage')->name('company-settings.edit');
    Route::put('company-settings', [CompanySettingController::class, 'update'])->middleware('permission:settings.manage')->name('company-settings.update');
    Route::post('invoices/preview', [InvoiceController::class, 'preview'])->middleware('permission:commerce.view')->name('invoices.preview');
    Route::get('invoices/{invoice}/print', [InvoiceController::class, 'print'])->whereNumber('invoice')->middleware('permission:commerce.view')->name('invoices.print');
    Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'downloadPdf'])->whereNumber('invoice')->middleware('permission:commerce.view')->name('invoices.pdf');
    Route::get('invoices/{invoice}/excel', [InvoiceController::class, 'downloadExcel'])->whereNumber('invoice')->middleware('permission:commerce.view')->name('invoices.excel');
    Route::resource('invoices', InvoiceController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'])->whereNumber('invoice')->middleware('permission:commerce.view');
    Route::post('invoices/{invoice}/confirm', [InvoiceController::class, 'confirm'])->whereNumber('invoice')->middleware('permission:commerce.manage')->name('invoices.confirm');
    Route::post('invoices/{invoice}/settle', [InvoiceController::class, 'settle'])->whereNumber('invoice')->middleware('permission:commerce.manage')->name('invoices.settle');
    Route::post('invoices/{invoice}/unsettle', [InvoiceController::class, 'unsettle'])->whereNumber('invoice')->middleware('permission:commerce.manage')->name('invoices.unsettle');
    Route::post('invoices/{invoice}/convert', [InvoiceController::class, 'convert'])->whereNumber('invoice')->middleware('permission:commerce.manage')->name('invoices.convert');
    Route::get('accounting-documents', [AccountingDocumentController::class, 'index'])->middleware('permission:accounting.view')->name('accounting-documents.index');
    Route::get('accounting-documents/create', [AccountingDocumentController::class, 'create'])->middleware('permission:accounting.documents.create')->name('accounting-documents.create');
    Route::post('accounting-documents', [AccountingDocumentController::class, 'store'])->middleware('permission:accounting.documents.create')->name('accounting-documents.store');
    Route::get('accounting-documents/{accountingDocument}', [AccountingDocumentController::class, 'show'])->middleware('permission:accounting.view')->name('accounting-documents.show');
    Route::get('accounting-documents/{accountingDocument}/print', [AccountingDocumentController::class, 'print'])->middleware('permission:accounting.view')->name('accounting-documents.print');
    Route::get('accounting-documents/{accountingDocument}/pdf', [AccountingDocumentController::class, 'pdf'])->middleware('permission:accounting.view')->name('accounting-documents.pdf');
    Route::get('accounting-documents/{accountingDocument}/edit', [AccountingDocumentController::class, 'edit'])->middleware('permission:accounting.documents.edit')->name('accounting-documents.edit');
    Route::put('accounting-documents/{accountingDocument}', [AccountingDocumentController::class, 'update'])->middleware('permission:accounting.documents.edit')->name('accounting-documents.update');
    Route::delete('accounting-documents/{accountingDocument}', [AccountingDocumentController::class, 'destroy'])->middleware('permission:accounting.documents.delete')->name('accounting-documents.destroy');
    Route::post('accounting-documents/{accountingDocument}/post', [AccountingDocumentController::class, 'post'])->middleware('permission:accounting.documents.post')->name('accounting-documents.post');
    Route::post('accounting-documents/{accountingDocument}/unpost', [AccountingDocumentController::class, 'unpost'])->middleware('permission:accounting.documents.edit')->name('accounting-documents.unpost');

    Route::get('treasury', [TreasuryController::class, 'index'])->middleware('permission:treasury.manage')->name('treasury.index');
    Route::get('treasury/create', [TreasuryController::class, 'create'])->middleware('permission:treasury.manage')->name('treasury.create');
    Route::post('treasury', [TreasuryController::class, 'store'])->middleware('permission:treasury.manage')->name('treasury.store');
    Route::get('treasury/{transaction}/edit', [TreasuryController::class, 'edit'])->whereNumber('transaction')->middleware('permission:treasury.manage')->name('treasury.edit');
    Route::put('treasury/{transaction}', [TreasuryController::class, 'update'])->whereNumber('transaction')->middleware('permission:treasury.manage')->name('treasury.update');
    Route::delete('treasury/{transaction}', [TreasuryController::class, 'destroy'])->whereNumber('transaction')->middleware('permission:treasury.manage')->name('treasury.destroy');

    Route::get('fiscal-periods', [FiscalPeriodController::class, 'index'])->middleware('permission:fiscal-years.manage')->name('fiscal-periods.index');
    Route::post('fiscal-periods', [FiscalPeriodController::class, 'store'])->middleware('permission:fiscal-years.manage')->name('fiscal-periods.store');
    Route::put('fiscal-periods/{fiscalYear}', [FiscalPeriodController::class, 'update'])->middleware('permission:fiscal-years.manage')->name('fiscal-periods.update');
    Route::delete('fiscal-periods/{fiscalYear}', [FiscalPeriodController::class, 'destroy'])->middleware('permission:fiscal-years.manage')->name('fiscal-periods.destroy');
    Route::post('fiscal-periods/{fiscalPeriod}/close', [FiscalPeriodController::class, 'close'])->middleware('permission:fiscal-years.manage')->name('fiscal-periods.close');
    Route::post('fiscal-periods/{fiscalPeriod}/reopen', [FiscalPeriodController::class, 'reopen'])->middleware('permission:fiscal-periods.reopen')->name('fiscal-periods.reopen');

    Route::get('financial-reports', [FinancialReportController::class, 'index'])->middleware('permission:financial.reports.view')->name('financial-reports.index');
    Route::get('financial-reports/general-ledger', [FinancialReportController::class, 'generalLedger'])->middleware('permission:financial.reports.view')->name('financial-reports.general-ledger');
    Route::get('financial-reports/trial-balance', [FinancialReportController::class, 'trialBalance'])->middleware('permission:financial.reports.view')->name('financial-reports.trial-balance');
    Route::get('financial-reports/statement', [FinancialReportController::class, 'statement'])->middleware('permission:financial.reports.view')->name('financial-reports.statement');
    Route::get('financial-reports/statement/print', [FinancialReportController::class, 'printStatement'])->middleware('permission:financial.reports.view')->name('financial-reports.statement.print');
    Route::get('financial-reports/accounts/{account}/statement', [FinancialReportController::class, 'accountStatement'])->middleware('permission:financial.reports.view')->name('financial-reports.account-statement');
    Route::get('financial-reports/accounts/{account}/statement/print', [FinancialReportController::class, 'printAccountStatement'])->middleware('permission:financial.reports.view')->name('financial-reports.account-statement.print');
    Route::get('financial-reports/balance-sheet', [FinancialReportController::class, 'balanceSheet'])->middleware('permission:financial.reports.view')->name('financial-reports.balance-sheet');
    Route::get('financial-reports/profit-loss', [FinancialReportController::class, 'profitAndLoss'])->middleware('permission:financial.reports.view')->name('financial-reports.profit-loss');
    Route::get('financial-reports/aging', [FinancialReportController::class, 'aging'])->middleware('permission:financial.reports.view')->name('financial-reports.aging');
    Route::get('financial-reports/{report}/print', [FinancialReportController::class, 'print'])->middleware('permission:financial.reports.view')->name('financial-reports.report.print');
    Route::get('financial-reports/{report}/pdf', [FinancialReportController::class, 'pdf'])->middleware('permission:financial.reports.view')->name('financial-reports.report.pdf');
    Route::get('financial-reports/{report}/excel', [FinancialReportController::class, 'excel'])->middleware('permission:financial.reports.view')->name('financial-reports.report.excel');
    Route::get('financial-reports/{report}', [FinancialReportController::class, 'show'])->middleware('permission:financial.reports.view')->name('financial-reports.show');
    Route::resource('inventory-documents', InventoryDocumentController::class)
        ->only(['index', 'create', 'store', 'edit', 'update', 'destroy'])
        ->middleware('permission:inventory.view');

    Route::middleware('permission:users.manage')->prefix('access')->name('access.')->group(function () {
        Route::resource('users', AccessUserController::class)->except(['show']);
        Route::get('users/{user}/employee-access', [AccessUserController::class, 'employeeAccess'])
            ->name('users.employee-access');
        Route::put('users/{user}/employee-access', [AccessUserController::class, 'updateEmployeeAccess'])
            ->name('users.employee-access.update');
        Route::resource('roles', AccessRoleController::class)->except(['show']);
    });

    Route::get('/management-reports', [ReportCenterController::class, 'index'])
        ->middleware('permission:reports.view')
        ->name('management-reports.index');

    Route::get('/management-reports/trial-balance', [ManagementReportController::class, 'trialBalance'])
        ->middleware('permission:reports.view')
        ->name('management-reports.trial-balance');

    Route::get('/management-reports/warehouse-cardex', [ManagementReportController::class, 'warehouseCardex'])
        ->middleware('permission:reports.view')
        ->name('management-reports.warehouse-cardex');

    Route::get('/management-reports/warehouse-inventory', [ManagementReportController::class, 'warehouseInventory'])
        ->middleware('permission:reports.view')
        ->name('management-reports.warehouse-inventory');
    Route::get('/management-reports/hr-employees', [ManagementReportController::class, 'hrEmployees'])
        ->middleware('permission:reports.view')
        ->name('management-reports.hr-employees');
    Route::get('/management-reports/hr-history', [ManagementReportController::class, 'hrHistory'])
        ->middleware('permission:reports.view')
        ->name('management-reports.hr-history');
    Route::get('/management-reports/hr-employment-orders', [ManagementReportController::class, 'hrEmploymentOrders'])
        ->middleware('permission:reports.view')
        ->name('management-reports.hr-employment-orders');
    Route::get('/management-reports/attendance-monthly', [ManagementReportController::class, 'attendanceMonthly'])
        ->middleware('permission:reports.view')
        ->name('management-reports.attendance-monthly');
    Route::get('/management-reports/attendance-exceptions', [ManagementReportController::class, 'attendanceExceptions'])
        ->middleware('permission:reports.view')
        ->name('management-reports.attendance-exceptions');
    Route::get('/management-reports/payroll-summary', [ManagementReportController::class, 'payrollSummary'])
        ->middleware('permission:reports.view')
        ->name('management-reports.payroll-summary');
    Route::get('/management-reports/payroll-register', [ManagementReportController::class, 'payrollRegister'])
        ->middleware('permission:reports.view')
        ->name('management-reports.payroll-register');
    Route::get('/management-reports/payslip-archive', [ManagementReportController::class, 'payslipArchive'])
        ->middleware('permission:reports.view')
        ->name('management-reports.payslip-archive');
    Route::get('/management-reports/insurance-summary', [ManagementReportController::class, 'insuranceSummary'])
        ->middleware('permission:reports.view')
        ->name('management-reports.insurance-summary');
    Route::get('/management-reports/tax-summary', [ManagementReportController::class, 'taxSummary'])
        ->middleware('permission:reports.view')
        ->name('management-reports.tax-summary');

    Route::get('projects/{project}/costing', [ProjectCostController::class, 'show'])
        ->middleware('permission:projects.view')
        ->name('projects.costing');
    Route::get('projects/{project}/costing/print', [ProjectCostController::class, 'print'])
        ->middleware('permission:projects.view')
        ->name('projects.costing.print');
    Route::post('production-orders/{productionOrder}/consume', [ProductionOrderController::class, 'consume'])
        ->middleware('permission:projects.manage')
        ->name('production-orders.consume');
    Route::resource('production-orders', ProductionOrderController::class)
        ->parameters(['production-orders' => 'productionOrder'])
        ->middleware('permission:projects.view');
    Route::resource('project-boms', BomVersionController::class)
        ->parameters(['project-boms' => 'projectBom'])
        ->middleware('permission:projects.view');
    Route::resource('projects', ProjectController::class)->except(['index', 'show'])->middleware('permission:projects.manage');
    Route::resource('projects', ProjectController::class)->only(['index', 'show'])->middleware('permission:projects.view');
    Route::get('organization-units', [OrganizationUnitController::class, 'index'])->middleware('permission:hr.view')->name('organization-units.index');
    Route::get('jobs', [JobController::class, 'index'])->middleware('permission:hr.view')->name('jobs.index');
    Route::get('positions', [PositionController::class, 'index'])->middleware('permission:hr.view')->name('positions.index');
    Route::get('employment-orders', [EmploymentOrderController::class, 'index'])->middleware('permission:employment-orders.view')->name('employment-orders.index');
    Route::post('employment-orders/{employmentOrder}/approve', [EmploymentOrderController::class, 'approve'])->middleware('permission:employment-orders.approve')->name('employment-orders.approve');
    Route::get('employment-contracts', [EmploymentContractController::class, 'index'])->middleware('permission:contracts.view')->name('employment-contracts.index');
    Route::get('employee-documents', [EmployeeDocumentController::class, 'index'])->middleware('permission:hr.view')->name('employee-documents.index');
    Route::resource('employees', EmployeeController::class)->except(['index', 'show'])->middleware('permission:employees.manage');
    Route::resource('employees', EmployeeController::class)->only(['index', 'show'])->middleware('permission:employees.view');
    Route::resource('work-logs', WorkLogController::class)->except(['index', 'show'])->middleware('permission:worklogs.manage');
    Route::resource('financial-transactions', FinancialTransactionController::class)->except(['index', 'show'])->middleware('permission:financial.manage');
    Route::get('/financial-transactions/summary', [FinancialTransactionController::class, 'summary'])
        ->middleware('permission:financial.view')
        ->name('financial-transactions.summary');

// ط¹آ¯ط·آ²ط·آ§ط·آ±ط·آ´أ¢â‚¬إ’ط¸â€،ط·آ§ط؛إ’ ط¸â€¦ط·آ§ط¸â€‍ط؛إ’
    Route::get('/financial-transactions/project/{project}',
        [FinancialTransactionController::class, 'projectReport'])
        ->middleware('permission:financial.view')
        ->name('financial-transactions.project-report');
    Route::resource('financial-transactions', FinancialTransactionController::class)->only(['index', 'show'])->middleware('permission:financial.view');

// ط¹آ¯ط·آ²ط·آ§ط·آ±ط·آ´أ¢â‚¬إ’ط¸â€،ط·آ§ط؛إ’ ط¹آ©ط·آ§ط·آ±ط¹آ©ط·آ±ط·آ¯
    Route::get('/work-logs/employee/{employee}',
        [WorkLogController::class, 'employeeReport'])
        ->middleware('permission:worklogs.view')
        ->name('work-logs.employee-report');

    Route::get('/work-logs/project/{project}',
        [WorkLogController::class, 'projectReport'])
        ->middleware('permission:worklogs.view')
        ->name('work-logs.project-report');
    Route::resource('work-logs', WorkLogController::class)->only(['index', 'show'])->middleware('permission:worklogs.view');
    Route::resource('work-shifts', WorkShiftController::class)->except(['show'])->middleware('permission:worklogs.manage');
    Route::resource('work-calendars', WorkCalendarController::class)->except(['show'])->middleware('permission:worklogs.manage');
    Route::view('work-groups/assignments', 'work-groups.assignments')->middleware('permission:worklogs.manage')->name('work-groups.assignments');
    Route::resource('work-groups', WorkGroupController::class)->except(['show'])->middleware('permission:worklogs.manage');
    Route::view('attendance/calculations', 'attendance.calculations')->middleware('permission:attendance.view')->name('attendance.calculations');
    Route::view('attendance/leaves', 'attendance.leaves')->middleware('permission:attendance.view')->name('attendance.leaves');
    Route::view('attendance/missions', 'attendance.missions')->middleware('permission:attendance.view')->name('attendance.missions');
    Route::view('attendance/summaries', 'attendance.summaries')->middleware('permission:attendance.view')->name('attendance.summaries');


// ط·آ§ط¸â€ ط·آ¨ط·آ§ط·آ±
    Route::resource('warehouses', WarehouseController::class)->except(['index', 'show'])->middleware('permission:warehouse.manage');

// Route ط·آ¨ط·آ±ط·آ§ط؛إ’ ط·آ¯ط·آ±ط؛إ’ط·آ§ط¸ظ¾ط·ع¾ ط¹آ©ط·آ§ط¸â€‍ط·آ§ط¸â€،ط·آ§ط؛إ’ ط¸â€¦ط¸ث†ط·آ¬ط¸ث†ط·آ¯ ط·آ¯ط·آ± ط·آ§ط¸â€ ط·آ¨ط·آ§ط·آ±

// ط¸â€ڑط·آ¨ط¸â€‍ ط·آ§ط·آ² ط¸â€،ط¸â€¦ط¸â€، Routeط¸â€،ط·آ§ ط·آ§ط؛إ’ط¸â€  ط·آ±ط·آ§ ط·آ§ط·آ¶ط·آ§ط¸ظ¾ط¸â€، ط¹آ©ط¸â€ ط؛إ’ط·آ¯
    Route::resource('warehouses', WarehouseController::class)->only(['index', 'show'])->middleware('permission:warehouse.view');

    // ط¸آ¾ط·آ±ط·آ¯ط·آ§ط·آ®ط·ع¾ ط·آ­ط¸â€ڑط¸ث†ط¸â€ڑ ط¸ث† ط¸â€¦ط·آ­ط·آ§ط·آ³ط·آ¨ط¸â€، ط·آ­ط¸â€ڑط¸ث†ط¸â€ڑ
    Route::get('bulk-delete', [SalaryController::class, 'bulkDeleteForm'])->middleware('permission:salaries.manage')->name('salaries.bulk-delete');
    Route::delete('salaries/destroy-multiple', [SalaryController::class, 'destroyMultiple'])->middleware('permission:salaries.manage')->name('salaries.destroy-multiple');
    Route::resource('salaries', SalaryController::class)->except(['index', 'show'])->middleware('permission:salaries.manage');
    Route::post('salaries/calculate', [SalaryController::class, 'calculate'])->middleware('permission:salaries.manage')->name('salaries.calculate');
    Route::post('salaries/{salary}/payments', [SalaryController::class, 'storePayment'])->middleware('permission:salaries.manage')->name('salaries.payments.store');
    // routes/web.php
    Route::get('financial-report', [SalaryController::class, 'financialReport'])->middleware('permission:salaries.view')->name('salaries.financial-report');
    Route::get('payroll/accounting-settings', [PayrollAccountingSettingController::class, 'index'])->middleware('permission:salaries.manage')->name('payroll.accounting-settings.index');
    Route::put('payroll/accounting-settings', [PayrollAccountingSettingController::class, 'update'])->middleware('permission:salaries.manage')->name('payroll.accounting-settings.update');
    Route::view('payroll/periods', 'payroll.periods.index')->middleware('permission:salaries.manage')->name('payroll.periods.index');
    Route::get('payslips/{payslip}/print', [PayslipController::class, 'print'])->middleware('permission:salaries.view')->name('payslips.print');
    Route::get('payslips/{payslip}/download', [PayslipController::class, 'download'])->middleware('permission:salaries.view')->name('payslips.download');
    Route::get('employee-statement/{employee}', [SalaryController::class, 'employeeStatement'])->middleware('permission:salaries.view')->name('salaries.employee-statement');
    // routes/web.php
    Route::get('/salaries/{salary}/print', [SalaryController::class, 'printSalarySlip'])->middleware('permission:salaries.view')->name('salaries.print-slip');
    Route::get('/salaries/{salary}/download', [SalaryController::class, 'downloadSalarySlip'])->middleware('permission:salaries.view')->name('salaries.download-slip');
    Route::resource('salaries', SalaryController::class)->only(['index', 'show'])->middleware('permission:salaries.view');


});

Route::get('/dashboard', DashboardController::class)->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});


use App\Http\Controllers\ExcelController;


Route::middleware(['auth', 'permission:projects.manage'])->group(function () {
    Route::get('/excel/import', [ExcelController::class, 'importView'])->name('import.view');
    Route::post('/excel/import', [ExcelController::class, 'import'])->name('import');
    Route::get('/excel/export', [ExcelController::class, 'export'])->name('export');
    Route::get('/excel/template', [ExcelController::class, 'downloadTemplate'])->name('template');
});

use App\Http\Controllers\WorkLogExcelController;

Route::middleware(['auth', 'permission:worklogs.manage'])->prefix('worklog')->group(function () {
    Route::get('/import', [WorkLogExcelController::class, 'importView'])->name('worklog.import.view');
    Route::post('/import', [WorkLogExcelController::class, 'import'])->name('worklog.import');
    Route::get('/export', [WorkLogExcelController::class, 'export'])->name('worklog.export');
    Route::get('/template', [WorkLogExcelController::class, 'downloadTemplate'])->name('worklog.template');
});

require __DIR__ . '/auth.php';
