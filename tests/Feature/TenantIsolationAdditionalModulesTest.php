<?php

use App\Models\Company;
use App\Models\Group;
use App\Models\GroupPermission;
use App\Models\User;
use Illuminate\Support\Facades\DB;

function tiamCreateCompany(string $name): Company
{
    return Company::create([
        'name' => $name,
        'is_active' => true,
    ]);
}

function tiamGrantModuleView(User $user, array $modules): void
{
    $group = Group::create(['name' => 'Tenant Isolation Additional '.uniqid()]);

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
}

test('route model binding blocks cross-company access for additional major modules', function () {
    $companyA = tiamCreateCompany('Additional Tenant A');
    $companyB = tiamCreateCompany('Additional Tenant B');

    $user = User::factory()->create([
        'current_company_id' => $companyA->id,
    ]);
    $user->companies()->attach([$companyA->id, $companyB->id]);
    tiamGrantModuleView($user, ['customers', 'contacts', 'products', 'suppliers', 'stock-movements', 'reports']);

    $customerId = (int) DB::table('customers')->insertGetId([
        'company_id' => $companyB->id,
        'name' => 'Cross Customer',
        'email' => 'cross-customer-2@example.com',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $contactId = (int) DB::table('contacts')->insertGetId([
        'company_id' => $companyB->id,
        'customer_id' => $customerId,
        'name' => 'Cross Contact',
        'email' => 'cross-contact-2@example.com',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $productId = (int) DB::table('products')->insertGetId([
        'company_id' => $companyB->id,
        'name' => 'Cross Product',
        'type' => 'product',
        'sku' => 'CROSS-PROD-2',
        'price' => 10,
        'cost' => 5,
        'track_stock' => true,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $supplierId = (int) DB::table('suppliers')->insertGetId([
        'company_id' => $companyB->id,
        'name' => 'Cross Supplier',
        'email' => 'cross-supplier-2@example.com',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $stockMovementId = (int) DB::table('stock_movements')->insertGetId([
        'company_id' => $companyB->id,
        'product_id' => $productId,
        'type' => 'in',
        'quantity' => 1,
        'stock_before' => 0,
        'stock_after' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $reportId = (int) DB::table('reports')->insertGetId([
        'company_id' => $companyB->id,
        'name' => 'Cross Report',
        'entity_type' => 'invoice',
        'config' => json_encode(['columns' => []], JSON_THROW_ON_ERROR),
        'filters' => json_encode([], JSON_THROW_ON_ERROR),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($user)->get(route('customers.show', $customerId))->assertNotFound();
    $this->actingAs($user)->get(route('contacts.show', $contactId))->assertNotFound();
    $this->actingAs($user)->get(route('products.show', $productId))->assertNotFound();
    $this->actingAs($user)->get(route('suppliers.show', $supplierId))->assertNotFound();
    $this->actingAs($user)->get(route('stock-movements.show', $stockMovementId))->assertNotFound();
    $this->actingAs($user)->get(route('reports.show', $reportId))->assertNotFound();
});
