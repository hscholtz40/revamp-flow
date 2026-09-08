<?php

use App\Http\Controllers\Api\LicenseValidationController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\CrmController;
use App\Http\Controllers\Api\V1\DispatchController;
use App\Http\Controllers\Api\V1\DocumentsController;
use App\Http\Controllers\Api\V1\FilesController;
use App\Http\Controllers\Api\V1\JobcardController;
use App\Http\Controllers\Api\V1\MessagingController;
use App\Http\Controllers\Api\V1\NotesApiController;
use App\Http\Controllers\Api\V1\NotificationsController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\ReportingController;
use App\Http\Controllers\Api\V1\TaskController;
use App\Http\Controllers\Api\V1\TeamsController;
use App\Http\Controllers\Api\V1\TimesheetController;
use App\Http\Controllers\Api\V1\TrackingController;
use App\Http\Controllers\Api\V1\JobQueryController;
use App\Http\Controllers\Api\QuoteIntegrationController;
use App\Http\Controllers\QueriesController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| These routes are stateless and intended for external integrations.
|
*/

Route::post('/api/licenses/validate', [LicenseValidationController::class, 'validate'])
    ->middleware('license.api.auth')
    ->name('api.licenses.validate');

Route::post('/api/licenses/report-version', [LicenseValidationController::class, 'reportVersion'])
    ->middleware('license.api.auth')
    ->name('api.licenses.report-version');

// Public query submission from external sites (e.g. the Revamp landing page).
// Authenticated by a shared API key (X-Api-Key header).
Route::post('/api/queries', [QueriesController::class, 'apiStore'])
    ->middleware(['query.api.auth', 'throttle:20,1'])
    ->name('api.queries.store');

// External quote integration (Revamp): dispatch a quote to the nearest licensed
// contractors and report which contractor accepted. Shared API key auth.
Route::post('/api/quotes/dispatch', [QuoteIntegrationController::class, 'dispatchQuote'])
    ->middleware(['query.api.auth', 'throttle:30,1'])
    ->name('api.quotes.dispatch');

Route::get('/api/quotes/{externalQuoteId}/status', [QuoteIntegrationController::class, 'status'])
    ->middleware(['query.api.auth', 'throttle:60,1'])
    ->name('api.quotes.status');

Route::prefix('/api/v1')->name('api.v1.')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login'])->name('auth.login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');

        Route::get('/jobcards', [JobcardController::class, 'index'])->middleware('api.module.permission:jobcards,list');
        Route::post('/jobcards', [JobcardController::class, 'store'])->middleware('api.module.permission:jobcards,create');
        Route::get('/jobcards/{jobcard}', [JobcardController::class, 'show'])->middleware('api.module.permission:jobcards,view');
        Route::patch('/jobcards/{jobcard}', [JobcardController::class, 'update'])->middleware('api.module.permission:jobcards,edit');
        Route::delete('/jobcards/{jobcard}', [JobcardController::class, 'destroy'])->middleware('api.module.permission:jobcards,delete');
        Route::patch('/jobcards/{jobcard}/status', [JobcardController::class, 'updateStatus'])->middleware('api.module.permission:jobcards,edit');
        Route::post('/jobcards/{jobcard}/time-entries/convert', [JobcardController::class, 'convertTimeEntries'])->middleware('api.module.permission:jobcards,edit');
        Route::post('/jobcards/{jobcard}/attachments', [JobcardController::class, 'storeAttachments'])->middleware('api.module.permission:jobcards,edit');
        Route::patch('/jobcards/{jobcard}/attachments/{attachment}', [JobcardController::class, 'updateAttachment'])->middleware('api.module.permission:jobcards,edit');
        Route::delete('/jobcards/{jobcard}/attachments/{attachment}', [JobcardController::class, 'destroyAttachment'])->middleware('api.module.permission:jobcards,edit');

        // Contractor job queries (mobile app): list and accept/decline dispatched jobs.
        Route::get('/job-queries', [JobQueryController::class, 'index'])->middleware('api.module.permission:queries,list');
        Route::post('/job-queries/{query}/accept', [JobQueryController::class, 'accept'])->middleware('api.module.permission:queries,edit');
        Route::post('/job-queries/{query}/decline', [JobQueryController::class, 'decline'])->middleware('api.module.permission:queries,edit');
        Route::post('/job-queries/{query}/convert-to-jobcard', [JobQueryController::class, 'convertToJobcard'])->middleware('api.module.permission:queries,edit');

        Route::get('/messages/conversations', [MessagingController::class, 'index'])->middleware('api.module.permission:messages,list');
        Route::post('/messages/conversations', [MessagingController::class, 'storeConversation'])->middleware('api.module.permission:messages,create');
        Route::get('/messages/conversations/{conversation}', [MessagingController::class, 'show'])->middleware('api.module.permission:messages,view');
        Route::post('/messages/conversations/{conversation}/messages', [MessagingController::class, 'storeMessage'])->middleware('api.module.permission:messages,create');
        Route::patch('/messages/conversations/{conversation}/read', [MessagingController::class, 'markRead'])->middleware('api.module.permission:messages,view');

        Route::middleware('dispatch.enabled')->group(function () {
            Route::get('/dispatch/board', [DispatchController::class, 'board'])->middleware('api.module.permission:dispatch,list');
            Route::patch('/dispatch/jobcards/{jobcard}/assign', [DispatchController::class, 'assignJobcard'])->middleware('api.module.permission:dispatch,edit');
            Route::patch('/dispatch/tasks/{task}/assign', [DispatchController::class, 'assignTask'])->middleware('api.module.permission:dispatch,edit');
            Route::patch('/dispatch/reorder', [DispatchController::class, 'reorder'])->middleware('api.module.permission:dispatch,edit');
            Route::patch('/dispatch/bulk-update', [DispatchController::class, 'bulkUpdate'])->middleware('api.module.permission:dispatch,edit');
            Route::get('/dispatch/conflicts', [DispatchController::class, 'conflicts'])->middleware('api.module.permission:dispatch,list');
            Route::get('/dispatch/my-schedule', [DispatchController::class, 'mySchedule'])->middleware('api.module.permission:dispatch,list');
            Route::post('/dispatch/routes/generate', [DispatchController::class, 'generateRoute'])->middleware('api.module.permission:dispatch,create');
            Route::get('/dispatch/routes', [DispatchController::class, 'routes'])->middleware('api.module.permission:dispatch,list');
            Route::get('/dispatch/routes/{routePlan}', [DispatchController::class, 'showRoute'])->middleware('api.module.permission:dispatch,view');
        });

        Route::get('/tracking/vehicles', [TrackingController::class, 'vehicles']);
        Route::post('/tracking/vehicles', [TrackingController::class, 'storeVehicle']);
        Route::patch('/tracking/vehicles/{vehicle}', [TrackingController::class, 'updateVehicle']);
        Route::get('/tracking/pings/latest', [TrackingController::class, 'latest']);
        Route::get('/tracking/pings/history', [TrackingController::class, 'history']);
        Route::post('/tracking/pings', [TrackingController::class, 'ingest']);

        Route::get('/tasks', [TaskController::class, 'index'])->middleware('api.module.permission:tasks,list');
        Route::post('/tasks', [TaskController::class, 'store'])->middleware('api.module.permission:tasks,create');
        Route::get('/tasks/{task}', [TaskController::class, 'show'])->middleware('api.module.permission:tasks,view');
        Route::patch('/tasks/{task}', [TaskController::class, 'update'])->middleware('api.module.permission:tasks,edit');
        Route::delete('/tasks/{task}', [TaskController::class, 'destroy'])->middleware('api.module.permission:tasks,delete');
        Route::post('/tasks/{task}/notes', [TaskController::class, 'addNote'])->middleware('api.module.permission:tasks,edit');

        Route::get('/customers', [CrmController::class, 'customers'])->middleware('api.module.permission:customers,list');
        Route::post('/customers', [CrmController::class, 'storeCustomer'])->middleware('api.module.permission:customers,create');
        Route::get('/customers/{customer}', [CrmController::class, 'showCustomer'])->middleware('api.module.permission:customers,view');
        Route::patch('/customers/{customer}', [CrmController::class, 'updateCustomer'])->middleware('api.module.permission:customers,edit');
        Route::delete('/customers/{customer}', [CrmController::class, 'destroyCustomer'])->middleware('api.module.permission:customers,delete');
        Route::get('/customers/search', [CrmController::class, 'searchCustomers'])->middleware('api.module.permission:customers,list');
        Route::post('/customers/quick-create', [CrmController::class, 'quickCreateCustomer'])->middleware('api.module.permission:customers,create');
        Route::post('/customers/{customer}/send-email', [CrmController::class, 'sendCustomerEmail'])->middleware('api.module.permission:customers,view');
        Route::post('/customers/{customer}/send-sms', [CrmController::class, 'sendCustomerSms'])->middleware('api.module.permission:customers,view');

        Route::get('/contacts', [CrmController::class, 'contacts'])->middleware('api.module.permission:contacts,list');
        Route::post('/contacts', [CrmController::class, 'storeContact'])->middleware('api.module.permission:contacts,create');
        Route::get('/contacts/{contact}', [CrmController::class, 'showContact'])->middleware('api.module.permission:contacts,view');
        Route::patch('/contacts/{contact}', [CrmController::class, 'updateContact'])->middleware('api.module.permission:contacts,edit');
        Route::delete('/contacts/{contact}', [CrmController::class, 'destroyContact'])->middleware('api.module.permission:contacts,delete');
        Route::get('/contacts/search', [CrmController::class, 'searchContacts'])->middleware('api.module.permission:contacts,list');
        Route::post('/contacts/quick-create', [CrmController::class, 'quickCreateContact'])->middleware('api.module.permission:contacts,create');

        Route::get('/products', [CatalogController::class, 'products'])->middleware('api.module.permission:products,list');
        Route::post('/products', [CatalogController::class, 'storeProduct'])->middleware('api.module.permission:products,create');
        Route::get('/products/{product}', [CatalogController::class, 'showProduct'])->middleware('api.module.permission:products,view');
        Route::patch('/products/{product}', [CatalogController::class, 'updateProduct'])->middleware('api.module.permission:products,edit');
        Route::delete('/products/{product}', [CatalogController::class, 'destroyProduct'])->middleware('api.module.permission:products,delete');

        Route::get('/suppliers', [CatalogController::class, 'suppliers'])->middleware('api.module.permission:suppliers,list');
        Route::post('/suppliers', [CatalogController::class, 'storeSupplier'])->middleware('api.module.permission:suppliers,create');
        Route::get('/suppliers/{supplier}', [CatalogController::class, 'showSupplier'])->middleware('api.module.permission:suppliers,view');
        Route::patch('/suppliers/{supplier}', [CatalogController::class, 'updateSupplier'])->middleware('api.module.permission:suppliers,edit');
        Route::delete('/suppliers/{supplier}', [CatalogController::class, 'destroySupplier'])->middleware('api.module.permission:suppliers,delete');
        Route::get('/suppliers/search', [CatalogController::class, 'searchSuppliers'])->middleware('api.module.permission:suppliers,list');

        Route::get('/stock-movements', [CatalogController::class, 'stockMovements'])->middleware('api.module.permission:stock-movements,list');
        Route::post('/stock-movements', [CatalogController::class, 'storeStockMovement'])->middleware('api.module.permission:stock-movements,create');
        Route::get('/stock-movements/{movement}', [CatalogController::class, 'showStockMovement'])->middleware('api.module.permission:stock-movements,view');

        Route::get('/purchase-orders', [DocumentsController::class, 'purchaseOrders'])->middleware('api.module.permission:purchase-orders,list');
        Route::post('/purchase-orders', [DocumentsController::class, 'storePurchaseOrder'])->middleware('api.module.permission:purchase-orders,create');
        Route::get('/purchase-orders/{po}', [DocumentsController::class, 'showPurchaseOrder'])->middleware('api.module.permission:purchase-orders,view');
        Route::patch('/purchase-orders/{po}', [DocumentsController::class, 'updatePurchaseOrder'])->middleware('api.module.permission:purchase-orders,edit');
        Route::delete('/purchase-orders/{po}', [DocumentsController::class, 'destroyPurchaseOrder'])->middleware('api.module.permission:purchase-orders,delete');
        Route::post('/purchase-orders/{po}/receive', [DocumentsController::class, 'receivePurchaseOrder'])->middleware('api.module.permission:purchase-orders,edit');

        Route::get('/quotes', [DocumentsController::class, 'quotes'])->middleware('api.module.permission:quotes,list');
        Route::post('/quotes', [DocumentsController::class, 'storeQuote'])->middleware('api.module.permission:quotes,create');
        Route::get('/quotes/{quote}', [DocumentsController::class, 'showQuote'])->middleware('api.module.permission:quotes,view');
        Route::patch('/quotes/{quote}', [DocumentsController::class, 'updateQuote'])->middleware('api.module.permission:quotes,edit');
        Route::delete('/quotes/{quote}', [DocumentsController::class, 'destroyQuote'])->middleware('api.module.permission:quotes,delete');
        Route::patch('/quotes/{quote}/status', [DocumentsController::class, 'updateQuoteStatus'])->middleware('api.module.permission:quotes,edit');
        Route::post('/quotes/{quote}/convert-to-invoice', [DocumentsController::class, 'convertQuoteToInvoice'])->middleware('api.module.permission:invoices,create');
        Route::post('/quotes/{quote}/convert-to-jobcard', [DocumentsController::class, 'convertQuoteToJobcard'])->middleware('api.module.permission:jobcards,create');

        Route::get('/invoices', [DocumentsController::class, 'invoices'])->middleware('api.module.permission:invoices,list');
        Route::post('/invoices', [DocumentsController::class, 'storeInvoice'])->middleware('api.module.permission:invoices,create');
        Route::get('/invoices/{invoice}', [DocumentsController::class, 'showInvoice'])->middleware('api.module.permission:invoices,view');
        Route::patch('/invoices/{invoice}', [DocumentsController::class, 'updateInvoice'])->middleware('api.module.permission:invoices,edit');
        Route::delete('/invoices/{invoice}', [DocumentsController::class, 'destroyInvoice'])->middleware('api.module.permission:invoices,delete');
        Route::patch('/invoices/{invoice}/status', [DocumentsController::class, 'updateInvoiceStatus'])->middleware('api.module.permission:invoices,edit');
        Route::post('/invoices/{invoice}/payments', [DocumentsController::class, 'addInvoicePayment'])->middleware('api.module.permission:invoices,view');

        Route::get('/credit-notes', [DocumentsController::class, 'creditNotes'])->middleware('api.module.permission:credit-notes,list');
        Route::post('/credit-notes', [DocumentsController::class, 'storeCreditNote'])->middleware('api.module.permission:credit-notes,create');
        Route::get('/credit-notes/{creditNote}', [DocumentsController::class, 'showCreditNote'])->middleware('api.module.permission:credit-notes,view');
        Route::patch('/credit-notes/{creditNote}', [DocumentsController::class, 'updateCreditNote'])->middleware('api.module.permission:credit-notes,edit');
        Route::delete('/credit-notes/{creditNote}', [DocumentsController::class, 'destroyCreditNote'])->middleware('api.module.permission:credit-notes,delete');
        Route::patch('/credit-notes/{creditNote}/status', [DocumentsController::class, 'updateCreditNoteStatus'])->middleware('api.module.permission:credit-notes,edit');

        Route::get('/time-entries', [TimesheetController::class, 'index'])->middleware('api.module.permission:timesheet,list');
        Route::post('/time-entries', [TimesheetController::class, 'store'])->middleware('api.module.permission:timesheet,create');
        Route::patch('/time-entries/{timeEntry}', [TimesheetController::class, 'update'])->middleware('api.module.permission:timesheet,edit');
        Route::delete('/time-entries/{timeEntry}', [TimesheetController::class, 'destroy'])->middleware('api.module.permission:timesheet,delete');
        Route::post('/time-entries/start-timer', [TimesheetController::class, 'startTimer'])->middleware('api.module.permission:timesheet,create');
        Route::post('/time-entries/pause-timer', [TimesheetController::class, 'pauseTimer'])->middleware('api.module.permission:timesheet,edit');
        Route::post('/time-entries/resume-timer', [TimesheetController::class, 'resumeTimer'])->middleware('api.module.permission:timesheet,edit');
        Route::post('/time-entries/stop-timer', [TimesheetController::class, 'stopTimer'])->middleware('api.module.permission:timesheet,edit');

        Route::get('/reports', [ReportingController::class, 'index'])->middleware('api.module.permission:reports,list');
        Route::get('/reports/{report}/data', [ReportingController::class, 'data'])->middleware('api.module.permission:reports,view');

        Route::get('/notes', [NotesApiController::class, 'index']);
        Route::post('/notes', [NotesApiController::class, 'store']);
        Route::delete('/notes/{note}', [NotesApiController::class, 'destroy']);

        Route::get('/teams', [TeamsController::class, 'index'])->middleware('api.module.permission:jobcards,list');
        Route::get('/users/assignable', [TeamsController::class, 'assignableUsers'])->middleware('api.module.permission:jobcards,list');

        Route::post('/uploads', [FilesController::class, 'upload']);
        Route::get('/files/{id}', [FilesController::class, 'show']);
        Route::delete('/files/{id}', [FilesController::class, 'destroy']);

        Route::get('/notifications', [NotificationsController::class, 'index']);
        Route::patch('/notifications/{id}/read', [NotificationsController::class, 'markRead']);
        Route::post('/notifications/read-all', [NotificationsController::class, 'markAllRead']);
        Route::post('/devices/register-push-token', [NotificationsController::class, 'registerDevice']);
        Route::get('/devices', [NotificationsController::class, 'listDevices']);
    });
});
