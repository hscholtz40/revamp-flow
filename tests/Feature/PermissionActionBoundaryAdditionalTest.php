<?php

use App\Models\Group;
use App\Models\GroupPermission;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;

function pabaCreateViewOnlyUser(array $modules): User
{
    $company = Company::create([
        'name' => 'Permission Additional Co '.uniqid(),
        'is_active' => true,
    ]);

    $user = User::factory()->create([
        'current_company_id' => $company->id,
    ]);
    $group = Group::create(['name' => 'View Only Additional '.uniqid()]);

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

test('view-only user is blocked from customer update action', function () {
    $user = pabaCreateViewOnlyUser(['customers']);
    $companyId = (int) $user->current_company_id;

    $customerId = (int) DB::table('customers')->insertGetId([
        'company_id' => $companyId,
        'name' => 'PABA Customer',
        'email' => 'paba-customer@example.com',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($user)
        ->put(route('customers.update', $customerId), ['name' => 'Blocked'])
        ->assertForbidden();
});

test('view-only user is blocked from contact update action', function () {
    $user = pabaCreateViewOnlyUser(['contacts']);
    $companyId = (int) $user->current_company_id;

    $customerId = (int) DB::table('customers')->insertGetId([
        'company_id' => $companyId,
        'name' => 'PABA Contact Customer',
        'email' => 'paba-contact-customer@example.com',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $contactId = (int) DB::table('contacts')->insertGetId([
        'company_id' => $companyId,
        'customer_id' => $customerId,
        'name' => 'PABA Contact',
        'email' => 'paba-contact@example.com',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($user)
        ->put(route('contacts.update', $contactId), ['name' => 'Blocked'])
        ->assertForbidden();
});

test('view-only user is blocked from product update action', function () {
    $user = pabaCreateViewOnlyUser(['products']);
    $companyId = (int) $user->current_company_id;

    $productId = (int) DB::table('products')->insertGetId([
        'company_id' => $companyId,
        'name' => 'PABA Product',
        'type' => 'product',
        'sku' => 'PABA-PROD-0001',
        'price' => 10,
        'cost' => 5,
        'track_stock' => true,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($user)
        ->put(route('products.update', $productId), ['name' => 'Blocked'])
        ->assertForbidden();
});

test('view-only user is blocked from stock movement create action', function () {
    $user = pabaCreateViewOnlyUser(['stock-movements']);

    $this->actingAs($user)
        ->post(route('stock-movements.store'), [])
        ->assertForbidden();
});

test('view-only user is blocked from credit note status update action', function () {
    $user = pabaCreateViewOnlyUser(['credit-notes']);
    $companyId = (int) $user->current_company_id;

    $customerId = (int) DB::table('customers')->insertGetId([
        'company_id' => $companyId,
        'name' => 'PABA CreditNote Customer',
        'email' => 'paba-cn-customer@example.com',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $invoiceId = (int) DB::table('invoices')->insertGetId([
        'invoice_number' => 'INV-PABA-0001',
        'title' => 'PABA Invoice',
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

    $creditNoteId = (int) DB::table('credit_notes')->insertGetId([
        'company_id' => $companyId,
        'invoice_id' => $invoiceId,
        'customer_id' => $customerId,
        'credit_note_number' => 'CN-PABA-0001',
        'credit_note_date' => now()->toDateString(),
        'status' => 'draft',
        'subtotal' => 0,
        'tax_amount' => 0,
        'total' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($user)
        ->patch(route('credit-notes.update-status', $creditNoteId), ['status' => 'approved'])
        ->assertForbidden();
});

test('view-only user is blocked from report update action', function () {
    $user = pabaCreateViewOnlyUser(['reports']);
    $companyId = (int) $user->current_company_id;

    $reportId = (int) DB::table('reports')->insertGetId([
        'company_id' => $companyId,
        'name' => 'PABA Report',
        'entity_type' => 'invoice',
        'config' => json_encode(['columns' => []], JSON_THROW_ON_ERROR),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($user)
        ->put(route('reports.update', $reportId), ['name' => 'Blocked'])
        ->assertForbidden();
});
