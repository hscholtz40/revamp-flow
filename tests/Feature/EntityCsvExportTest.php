<?php

use App\Models\Contact;
use App\Models\Supplier;

test('customers contacts suppliers and products can export csv', function () {
    $company = coverageCreateCompany();
    $user = coverageCreateUserWithPermissions($company, [
        'customers' => ['list', 'view'],
        'contacts' => ['list', 'view'],
        'suppliers' => ['list', 'view'],
        'products' => ['list', 'view'],
    ]);

    $customer = coverageSeedCustomer($company, [
        'name' => 'Export Customer',
        'email' => 'export-customer@example.com',
        'account_code' => 'EXP-001',
    ]);

    Contact::create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'name' => 'Export Contact',
        'email' => 'export-contact@example.com',
        'phone' => '0210000000',
        'is_primary' => true,
    ]);

    Supplier::create([
        'company_id' => $company->id,
        'name' => 'Export Supplier',
        'email' => 'export-supplier@example.com',
        'is_active' => true,
    ]);

    coverageSeedProduct($company, [
        'name' => 'Export Product',
        'sku' => 'EXP-PROD-1',
        'type' => 'product',
        'unit' => 'piece',
    ]);

    $customersCsv = $this->actingAs($user)->get(route('customers.export'));
    $customersCsv->assertOk();
    expect($customersCsv->streamedContent())->toContain('Export Customer');

    $contactsCsv = $this->actingAs($user)->get(route('contacts.export'));
    $contactsCsv->assertOk();
    expect($contactsCsv->streamedContent())->toContain('Export Contact');

    $suppliersCsv = $this->actingAs($user)->get(route('suppliers.export'));
    $suppliersCsv->assertOk();
    expect($suppliersCsv->streamedContent())->toContain('Export Supplier');

    $productsCsv = $this->actingAs($user)->get(route('products.export'));
    $productsCsv->assertOk();
    expect($productsCsv->streamedContent())->toContain('Export Product');
});
