<?php



use App\Http\Controllers\Auth\AuthenticatedSessionController;

use Illuminate\Support\Facades\Route;



Route::middleware('guest')->prefix('crm')->name('crm.')->group(function () {

    Route::get('login', [AuthenticatedSessionController::class, 'create'])

        ->defaults('app', 'crm')

        ->name('login');



    Route::post('login', [AuthenticatedSessionController::class, 'store'])

        ->defaults('app', 'crm')

        ->name('login.store');

});



Route::middleware(['auth', 'verified', 'active.crm'])->prefix('crm')->name('crm.')->group(function () {

    Route::get('/', function () {

        return redirect()->route('crm.dashboard');

    })->name('home');



    Route::view('/dashboard', 'crm.dashboard.index')

        ->middleware('permission:crm.dashboard.view')

        ->name('dashboard');



    Route::view('/customers', 'crm.customers.index')

        ->middleware('permission:crm.customers.view')

        ->name('customers.index');



    Route::view('/customers/{party}', 'crm.customers.show')

        ->middleware('permission:crm.customers.view')

        ->whereNumber('party')

        ->name('customers.show');



    Route::middleware('permission:crm.customers.view')->group(function () {

        Route::get('/invoices/{invoice}', [\App\Http\Controllers\Crm\CrmCustomerInvoiceController::class, 'show'])

            ->whereNumber('invoice')

            ->name('invoices.show');



        Route::get('/invoices/{invoice}/print', [\App\Http\Controllers\Crm\CrmCustomerInvoiceController::class, 'print'])

            ->whereNumber('invoice')

            ->name('invoices.print');



        Route::get('/invoices/{invoice}/pdf', [\App\Http\Controllers\Crm\CrmCustomerInvoiceController::class, 'downloadPdf'])

            ->whereNumber('invoice')

            ->name('invoices.pdf');



        Route::get('/invoices/{invoice}/excel', [\App\Http\Controllers\Crm\CrmCustomerInvoiceController::class, 'downloadExcel'])

            ->whereNumber('invoice')

            ->name('invoices.excel');



        Route::get('/invoices/{invoice}/edit', [\App\Http\Controllers\Crm\CrmCustomerInvoiceController::class, 'edit'])

            ->whereNumber('invoice')

            ->name('invoices.edit');



        Route::put('/invoices/{invoice}', [\App\Http\Controllers\Crm\CrmCustomerInvoiceController::class, 'update'])

            ->whereNumber('invoice')

            ->name('invoices.update');

    });



    Route::view('/contacts', 'crm.contacts.index')

        ->middleware('permission:crm.contacts.view')

        ->name('contacts.index');



    Route::view('/leads', 'crm.leads.index')

        ->middleware('permission:crm.leads.view')

        ->name('leads.index');



    Route::view('/leads/{lead}', 'crm.leads.show')

        ->middleware('permission:crm.leads.view')

        ->whereNumber('lead')

        ->name('leads.show');



    Route::post('/leads/{lead}/attachments', [\App\Http\Controllers\Crm\CrmAttachmentController::class, 'storeForLead'])

        ->middleware('permission:crm.leads.view')

        ->whereNumber('lead')

        ->name('leads.attachments.store');



    Route::post('/opportunities/{opportunity}/attachments', [\App\Http\Controllers\Crm\CrmAttachmentController::class, 'storeForOpportunity'])

        ->middleware('permission:crm.opportunities.view')

        ->whereNumber('opportunity')

        ->name('opportunities.attachments.store');



    Route::get('/attachments/{attachment}', [\App\Http\Controllers\Crm\CrmAttachmentController::class, 'show'])

        ->whereNumber('attachment')

        ->name('attachments.show');



    Route::view('/opportunities', 'crm.opportunities.index')

        ->middleware('permission:crm.opportunities.view')

        ->name('opportunities.index');



    Route::view('/pipeline', 'crm.pipeline.index')

        ->middleware('permission:crm.opportunities.view')

        ->name('pipeline.index');



    Route::view('/pipeline/opportunities/{opportunity}/log-call', 'crm.pipeline.log-call')

        ->middleware(['permission:crm.opportunities.view', 'permission:crm.activities.create'])

        ->whereNumber('opportunity')

        ->name('pipeline.log-call');



    Route::middleware('permission:crm.opportunities.view')->group(function () {

        Route::get('/proforma/{invoice}', [\App\Http\Controllers\Crm\CrmProformaController::class, 'show'])

            ->whereNumber('invoice')

            ->name('proforma.show');



        Route::get('/proforma/{invoice}/edit', [\App\Http\Controllers\Crm\CrmProformaController::class, 'edit'])

            ->whereNumber('invoice')

            ->name('proforma.edit');



        Route::put('/proforma/{invoice}', [\App\Http\Controllers\Crm\CrmProformaController::class, 'update'])

            ->whereNumber('invoice')

            ->name('proforma.update');



        Route::get('/proforma/{invoice}/print', [\App\Http\Controllers\Crm\CrmProformaController::class, 'print'])

            ->whereNumber('invoice')

            ->name('proforma.print');



        Route::get('/proforma/{invoice}/pdf', [\App\Http\Controllers\Crm\CrmProformaController::class, 'downloadPdf'])

            ->whereNumber('invoice')

            ->name('proforma.pdf');



        Route::get('/proforma/{invoice}/excel', [\App\Http\Controllers\Crm\CrmProformaController::class, 'downloadExcel'])

            ->whereNumber('invoice')

            ->name('proforma.excel');



        Route::get('/opportunities/{opportunity}/proforma/compose', [\App\Http\Controllers\Crm\CrmProformaController::class, 'compose'])

            ->whereNumber('opportunity')

            ->name('proforma.compose');



        Route::post('/opportunities/{opportunity}/proforma', [\App\Http\Controllers\Crm\CrmProformaController::class, 'store'])

            ->whereNumber('opportunity')

            ->name('proforma.store');

    });



    Route::view('/activities', 'crm.activities.index')

        ->middleware('permission:crm.activities.view')

        ->name('activities.index');



    Route::view('/tasks', 'crm.tasks.index')

        ->middleware('permission:crm.tasks.view')

        ->name('tasks.index');



    Route::view('/calendar', 'crm.calendar.index')

        ->middleware('permission:crm.activities.view')

        ->name('calendar.index');



    Route::view('/reports', 'crm.reports.index')

        ->middleware('permission:crm.reports.view')

        ->name('reports.index');



    Route::view('/settings', 'crm.settings.index')

        ->middleware('permission:crm.settings.manage')

        ->name('settings.index');



    Route::view('/search', 'crm.search.index')

        ->middleware('permission:crm.dashboard.view')

        ->name('search.index');



    Route::view('/sold-devices', 'crm.sold-devices.index')

        ->middleware('permission:crm.sold_devices.view')

        ->name('sold-devices.index');

});

