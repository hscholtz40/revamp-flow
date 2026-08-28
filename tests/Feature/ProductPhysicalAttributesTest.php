<?php

use App\Models\Product;

test('users can save product physical attributes when creating a product', function () {
    $company = coverageCreateCompany();
    $user = coverageCreateUserWithPermissions($company, [
        'products' => ['list', 'view', 'create', 'edit'],
    ], [
        'email_verified_at' => now(),
    ]);

    $this->actingAs($user)
        ->post(route('products.store'), [
            'name' => 'Physical Product',
            'type' => 'product',
            'price' => 99.99,
            'unit' => 'piece',
            'track_stock' => true,
            'stock_quantity' => 5,
            'is_active' => true,
            'weight' => 1.25,
            'length' => 30,
            'width' => 20,
            'height' => 10,
            'color' => 'Blue',
            'size' => 'Large',
        ])
        ->assertRedirect(route('products.index'));

    $product = Product::query()->where('name', 'Physical Product')->first();

    expect($product)->not->toBeNull()
        ->and((float) $product->weight)->toBe(1.25)
        ->and((float) $product->length)->toBe(30.0)
        ->and((float) $product->width)->toBe(20.0)
        ->and((float) $product->height)->toBe(10.0)
        ->and($product->color)->toBe('Blue')
        ->and($product->size)->toBe('Large');
});
