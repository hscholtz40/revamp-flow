<?php

use App\Models\Company;
use App\Models\Group;
use App\Models\GroupPermission;
use App\Models\User;
use Illuminate\Support\Facades\DB;

function svCreateCompany(string $name): Company
{
    return Company::create([
        'name' => $name,
        'is_active' => true,
    ]);
}

function svGrantCreatePermissions(User $user): void
{
    $group = Group::create(['name' => 'Scoped Validation Creators '.uniqid()]);

    foreach (['quotes', 'invoices', 'purchase-orders', 'timesheet'] as $module) {
        GroupPermission::create([
            'group_id' => $group->id,
            'module' => $module,
            'can_view' => true,
            'can_list' => true,
            'can_create' => true,
            'can_edit' => true,
            'can_delete' => false,
        ]);
    }

    $user->groups()->attach($group->id);
}

test('quote store rejects foreign-company customer id', function () {
    $companyA = svCreateCompany('Scoped Company A');
    $companyB = svCreateCompany('Scoped Company B');

    $user = User::factory()->create(['current_company_id' => $companyA->id]);
    $user->companies()->attach($companyA->id);
    svGrantCreatePermissions($user);

    $foreignCustomerId = (int) DB::table('customers')->insertGetId([
        'company_id' => $companyB->id,
        'name' => 'Foreign Customer',
        'email' => 'foreign-customer@example.com',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->actingAs($user)->post(route('quotes.store'), [
        'customer_id' => $foreignCustomerId,
        'title' => 'Scoped validation quote',
        'status' => 'draft',
        'line_items' => [
            [
                'description' => 'Line',
                'quantity' => 1,
                'unit_price' => 100,
            ],
        ],
    ]);

    $response->assertSessionHasErrors(['customer_id']);
});

test('invoice store rejects foreign-company customer id', function () {
    $companyA = svCreateCompany('Invoice Scoped A');
    $companyB = svCreateCompany('Invoice Scoped B');

    $user = User::factory()->create(['current_company_id' => $companyA->id]);
    $user->companies()->attach($companyA->id);
    svGrantCreatePermissions($user);

    $foreignCustomerId = (int) DB::table('customers')->insertGetId([
        'company_id' => $companyB->id,
        'name' => 'Foreign Invoice Customer',
        'email' => 'foreign-invoice-customer@example.com',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->actingAs($user)->post(route('invoices.store'), [
        'customer_id' => $foreignCustomerId,
        'invoice_date' => now()->toDateString(),
        'tax_rate' => 15,
        'line_items' => [
            [
                'description' => 'Line',
                'quantity' => 1,
                'unit_price' => 50,
            ],
        ],
    ]);

    $response->assertSessionHasErrors(['customer_id']);
});

test('purchase order store rejects foreign-company supplier id', function () {
    $companyA = svCreateCompany('PO Scoped A');
    $companyB = svCreateCompany('PO Scoped B');

    $user = User::factory()->create(['current_company_id' => $companyA->id]);
    $user->companies()->attach($companyA->id);
    svGrantCreatePermissions($user);

    $foreignSupplierId = (int) DB::table('suppliers')->insertGetId([
        'company_id' => $companyB->id,
        'name' => 'Foreign Supplier',
        'email' => 'foreign-supplier@example.com',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->actingAs($user)->post(route('purchase-orders.store'), [
        'supplier_id' => $foreignSupplierId,
        'order_date' => now()->toDateString(),
        'items' => [
            [
                'description' => 'Item',
                'quantity' => 1,
                'unit_cost' => 25,
            ],
        ],
    ]);

    $response->assertSessionHasErrors(['supplier_id']);
});

test('time entry store rejects foreign-company jobcard id', function () {
    $companyA = svCreateCompany('Time Entry Scoped A');
    $companyB = svCreateCompany('Time Entry Scoped B');

    $user = User::factory()->create(['current_company_id' => $companyA->id]);
    $user->companies()->attach($companyA->id);
    svGrantCreatePermissions($user);

    $customerB = (int) DB::table('customers')->insertGetId([
        'company_id' => $companyB->id,
        'name' => 'Customer B',
        'email' => 'customer-b-sv@example.com',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $foreignJobcardId = (int) DB::table('jobcards')->insertGetId([
        'company_id' => $companyB->id,
        'customer_id' => $customerB,
        'job_number' => 'JC-SV-FOREIGN-0001',
        'title' => 'Foreign Jobcard',
        'status' => 'draft',
        'subtotal' => 0,
        'tax_rate' => 0,
        'tax_amount' => 0,
        'total' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->actingAs($user)->post(route('time-entries.store'), [
        'jobcard_id' => $foreignJobcardId,
        'date' => now()->toDateString(),
        'duration_minutes' => 60,
    ]);

    $response->assertSessionHasErrors(['jobcard_id']);
});
