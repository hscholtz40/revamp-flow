<?php

use App\Models\Group;
use App\Models\GroupPermission;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;

function createViewOnlyUserForModules(array $modules): User
{
    $company = Company::create([
        'name' => 'Permission Boundary Co '.uniqid(),
        'is_active' => true,
    ]);

    $user = User::factory()->create([
        'current_company_id' => $company->id,
    ]);
    $group = Group::create(['name' => 'View Only '.uniqid()]);

    foreach ($modules as $module) {
        GroupPermission::create([
            'group_id' => $group->id,
            'module' => $module,
            'can_view' => true,
            'can_list' => true,
            'can_create' => false,
            'can_edit' => false,
            'can_delete' => false,
        ]);
    }

    $user->groups()->attach($group->id);
    $user->companies()->attach($company->id);

    return $user;
}

test('view-only user is blocked from quote convert action', function () {
    $user = createViewOnlyUserForModules(['quotes']);
    $companyId = (int) $user->current_company_id;

    $customerId = (int) DB::table('customers')->insertGetId([
        'company_id' => $companyId,
        'name' => 'PB Quote Customer',
        'email' => 'pb-quote-customer@example.com',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $quoteId = (int) DB::table('quotes')->insertGetId([
        'company_id' => $companyId,
        'customer_id' => $customerId,
        'quote_number' => 'QT-PB-0001',
        'title' => 'PB Quote',
        'status' => 'draft',
        'subtotal' => 0,
        'tax_rate' => 0,
        'tax_amount' => 0,
        'total' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($user)
        ->post(route('quotes.convert-to-invoice', $quoteId))
        ->assertForbidden();
});

test('view-only user can access invoice email action (validation executes)', function () {
    $user = createViewOnlyUserForModules(['invoices']);
    $companyId = (int) $user->current_company_id;

    $customerId = (int) DB::table('customers')->insertGetId([
        'company_id' => $companyId,
        'name' => 'PB Invoice Customer',
        'email' => 'pb-invoice-customer@example.com',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $invoiceId = (int) DB::table('invoices')->insertGetId([
        'invoice_number' => 'INV-PB-0001',
        'title' => 'PB Invoice',
        'customer_id' => $customerId,
        'company_id' => $companyId,
        'status' => 'draft',
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDay()->toDateString(),
        'subtotal' => 0,
        'tax_rate' => 0,
        'tax_amount' => 0,
        'total' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($user)
        ->post(route('invoices.email', $invoiceId))
        ->assertSessionHasErrors(['email']);
});

test('view-only user is blocked from jobcard status update action', function () {
    $user = createViewOnlyUserForModules(['jobcards']);
    $companyId = (int) $user->current_company_id;

    $customerId = (int) DB::table('customers')->insertGetId([
        'company_id' => $companyId,
        'name' => 'PB Jobcard Customer',
        'email' => 'pb-jobcard-customer@example.com',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $jobcardId = (int) DB::table('jobcards')->insertGetId([
        'company_id' => $companyId,
        'customer_id' => $customerId,
        'job_number' => 'JC-PB-0001',
        'title' => 'PB Jobcard',
        'status' => 'draft',
        'subtotal' => 0,
        'tax_rate' => 0,
        'tax_amount' => 0,
        'total' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($user)
        ->patch(route('jobcards.update-status', $jobcardId), ['status' => 'completed'])
        ->assertForbidden();
});

test('view-only user is blocked from purchase order receive items action', function () {
    $user = createViewOnlyUserForModules(['purchase-orders']);
    $companyId = (int) $user->current_company_id;

    $supplierId = (int) DB::table('suppliers')->insertGetId([
        'company_id' => $companyId,
        'name' => 'PB PO Supplier',
        'email' => 'pb-po-supplier@example.com',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $poId = (int) DB::table('purchase_orders')->insertGetId([
        'company_id' => $companyId,
        'supplier_id' => $supplierId,
        'po_number' => 'PO-PB-0001',
        'order_date' => now()->toDateString(),
        'status' => 'draft',
        'subtotal' => 0,
        'tax_amount' => 0,
        'total' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($user)
        ->post(route('purchase-orders.receive-items', $poId), ['items' => []])
        ->assertForbidden();
});
