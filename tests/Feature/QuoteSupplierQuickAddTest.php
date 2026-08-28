<?php

test('users with supplier create permission can quick create suppliers for quote line items via json', function () {
    $company = coverageCreateCompany();
    $user = coverageCreateUserWithPermissions($company, [
        'suppliers' => ['list', 'view', 'create', 'edit'],
    ], [
        'email_verified_at' => now(),
    ]);

    $this->actingAs($user)
        ->postJson('/suppliers', [
            'name' => 'Quote Quick Add Supplier',
            'email' => 'quick-supplier@example.com',
            'phone' => '0123456789',
            'vat_number' => 'VAT123',
            'is_active' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('name', 'Quote Quick Add Supplier')
        ->assertJsonPath('email', 'quick-supplier@example.com')
        ->assertJsonPath('phone', '0123456789')
        ->assertJsonPath('vat_number', 'VAT123');
});
