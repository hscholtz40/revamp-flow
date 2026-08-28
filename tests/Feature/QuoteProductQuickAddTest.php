<?php

test('users with product create permission can quick create products for quote line items via json', function () {
    $company = coverageCreateCompany();
    $user = coverageCreateUserWithPermissions($company, [
        'products' => ['list', 'view', 'create', 'edit'],
    ], [
        'email_verified_at' => now(),
    ]);

    $this->actingAs($user)
        ->postJson('/products', [
            'name' => 'Quote Quick Add Product',
            'type' => 'product',
            'price' => 150,
            'cost' => 90,
            'unit' => 'piece',
            'track_stock' => true,
            'stock_quantity' => 0,
            'min_stock_level' => 0,
            'is_active' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('name', 'Quote Quick Add Product')
        ->assertJsonPath('price', 150)
        ->assertJsonPath('cost', 90);
});
