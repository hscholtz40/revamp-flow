<?php

use App\Models\Company;
use App\Models\Group;
use App\Models\GroupPermission;
use App\Models\User;
use Illuminate\Support\Facades\DB;

function createCompanyForTenantIsolation(string $name): Company
{
    return Company::create([
        'name' => $name,
        'is_active' => true,
    ]);
}

function grantDocumentViewPermissions(User $user): void
{
    $group = Group::create(['name' => 'Tenant Isolation Viewers '.uniqid()]);

    foreach (['jobcards', 'quotes', 'invoices', 'purchase-orders'] as $module) {
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

function seedCustomer(int $companyId, string $suffix): int
{
    return (int) DB::table('customers')->insertGetId([
        'company_id' => $companyId,
        'name' => "Customer {$suffix}",
        'email' => "customer-{$suffix}@example.com",
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function seedSupplier(int $companyId, string $suffix): int
{
    return (int) DB::table('suppliers')->insertGetId([
        'company_id' => $companyId,
        'name' => "Supplier {$suffix}",
        'email' => "supplier-{$suffix}@example.com",
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

test('route model binding blocks cross-company document access', function () {
    $companyA = createCompanyForTenantIsolation('Tenant A');
    $companyB = createCompanyForTenantIsolation('Tenant B');

    $user = User::factory()->create([
        'current_company_id' => $companyA->id,
    ]);
    grantDocumentViewPermissions($user);

    // User has access to both companies, but current company is A.
    $user->companies()->attach([$companyA->id, $companyB->id]);

    $customerId = seedCustomer($companyB->id, 'tenant-b');
    $supplierId = seedSupplier($companyB->id, 'tenant-b');

    $jobcardId = (int) DB::table('jobcards')->insertGetId([
        'company_id' => $companyB->id,
        'customer_id' => $customerId,
        'job_number' => 'JC-TENANT-B-0001',
        'title' => 'Tenant B Jobcard',
        'status' => 'draft',
        'subtotal' => 0,
        'tax_rate' => 0,
        'tax_amount' => 0,
        'total' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $quoteId = (int) DB::table('quotes')->insertGetId([
        'company_id' => $companyB->id,
        'customer_id' => $customerId,
        'quote_number' => 'QT-TENANT-B-0001',
        'title' => 'Tenant B Quote',
        'status' => 'draft',
        'subtotal' => 0,
        'tax_rate' => 0,
        'tax_amount' => 0,
        'total' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $invoiceId = (int) DB::table('invoices')->insertGetId([
        'invoice_number' => 'INV-TENANT-B-0001',
        'title' => 'Tenant B Invoice',
        'customer_id' => $customerId,
        'company_id' => $companyB->id,
        'status' => 'draft',
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(7)->toDateString(),
        'subtotal' => 0,
        'tax_rate' => 0,
        'tax_amount' => 0,
        'total' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $purchaseOrderId = (int) DB::table('purchase_orders')->insertGetId([
        'company_id' => $companyB->id,
        'supplier_id' => $supplierId,
        'po_number' => 'PO-TENANT-B-0001',
        'order_date' => now()->toDateString(),
        'status' => 'draft',
        'subtotal' => 0,
        'tax_amount' => 0,
        'total' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($user)->get(route('jobcards.show', $jobcardId))->assertNotFound();
    $this->actingAs($user)->get(route('quotes.show', $quoteId))->assertNotFound();
    $this->actingAs($user)->get(route('invoices.show', $invoiceId))->assertNotFound();
    $this->actingAs($user)->get(route('purchase-orders.show', $purchaseOrderId))->assertNotFound();
});
