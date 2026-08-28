<?php

use App\Models\Supplier;

test('users can save supplier bank details when creating a supplier', function () {
    $company = coverageCreateCompany();
    $user = coverageCreateUserWithPermissions($company, [
        'suppliers' => ['list', 'view', 'create', 'edit'],
    ], [
        'email_verified_at' => now(),
    ]);

    $this->actingAs($user)
        ->post(route('suppliers.store'), [
            'name' => 'Banked Supplier',
            'email' => 'pay@banked.example',
            'is_active' => true,
            'bank_name' => 'First National Bank',
            'bank_account_name' => 'Banked Supplier Pty Ltd',
            'bank_account_number' => '1234567890',
            'bank_sort_code' => '250655',
        ])
        ->assertRedirect(route('suppliers.index'));

    $supplier = Supplier::query()->where('name', 'Banked Supplier')->first();

    expect($supplier)->not->toBeNull()
        ->and($supplier->bank_name)->toBe('First National Bank')
        ->and($supplier->bank_account_name)->toBe('Banked Supplier Pty Ltd')
        ->and($supplier->bank_account_number)->toBe('1234567890')
        ->and($supplier->bank_sort_code)->toBe('250655');
});

test('users can update supplier bank details', function () {
    $company = coverageCreateCompany();
    $user = coverageCreateUserWithPermissions($company, [
        'suppliers' => ['list', 'view', 'create', 'edit'],
    ], [
        'email_verified_at' => now(),
    ]);

    $supplier = Supplier::create([
        'company_id' => $company->id,
        'name' => 'Payable Supplier',
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->put(route('suppliers.update', $supplier), [
            'name' => 'Payable Supplier',
            'is_active' => true,
            'bank_name' => 'Capitec Bank',
            'bank_account_name' => 'Payable Supplier',
            'bank_account_number' => '99887766',
            'bank_sort_code' => '470010',
        ])
        ->assertRedirect(route('suppliers.index'));

    $supplier->refresh();

    expect($supplier->bank_name)->toBe('Capitec Bank')
        ->and($supplier->bank_account_number)->toBe('99887766');
});
