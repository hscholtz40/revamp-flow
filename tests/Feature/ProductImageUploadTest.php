<?php

use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('users can upload a product image when creating a product', function () {
    Storage::fake('public');

    $company = coverageCreateCompany();
    $user = coverageCreateUserWithPermissions($company, [
        'products' => ['list', 'view', 'create', 'edit'],
    ], [
        'email_verified_at' => now(),
    ]);

    $file = UploadedFile::fake()->image('product.jpg');

    $this->actingAs($user)
        ->post(route('products.store'), [
            'name' => 'Photo Product',
            'type' => 'product',
            'price' => 10,
            'unit' => 'piece',
            'track_stock' => true,
            'stock_quantity' => 0,
            'is_active' => true,
            'image' => $file,
        ])
        ->assertRedirect(route('products.index'));

    $product = Product::query()->where('name', 'Photo Product')->first();

    expect($product)->not->toBeNull()
        ->and($product->image_path)->not->toBeNull();

    Storage::disk('public')->assertExists($product->image_path);
    expect($product->image_url)->toBe('/storage/'.$product->image_path);
});

test('users can replace and remove a product image when editing', function () {
    Storage::fake('public');

    $company = coverageCreateCompany();
    $user = coverageCreateUserWithPermissions($company, [
        'products' => ['list', 'view', 'create', 'edit'],
    ], [
        'email_verified_at' => now(),
    ]);

    $originalPath = UploadedFile::fake()->image('original.jpg')->store("product-images/{$company->id}", 'public');
    $product = Product::create([
        'company_id' => $company->id,
        'name' => 'Editable Product',
        'type' => 'product',
        'price' => 25,
        'unit' => 'piece',
        'track_stock' => true,
        'stock_quantity' => 1,
        'is_active' => true,
        'image_path' => $originalPath,
    ]);

    $replacement = UploadedFile::fake()->image('replacement.jpg');

    $this->actingAs($user)
        ->put(route('products.update', $product), [
            'name' => $product->name,
            'type' => 'product',
            'price' => 25,
            'unit' => 'piece',
            'track_stock' => true,
            'stock_quantity' => 1,
            'is_active' => true,
            'image' => $replacement,
        ])
        ->assertRedirect(route('products.show', $product));

    $product->refresh();

    expect($product->image_path)->not->toBe($originalPath);
    Storage::disk('public')->assertMissing($originalPath);
    Storage::disk('public')->assertExists($product->image_path);

    $removedPath = $product->image_path;

    $this->actingAs($user)
        ->put(route('products.update', $product), [
            'name' => $product->name,
            'type' => 'product',
            'price' => 25,
            'unit' => 'piece',
            'track_stock' => true,
            'stock_quantity' => 1,
            'is_active' => true,
            'remove_image' => true,
        ])
        ->assertRedirect(route('products.show', $product));

    $product->refresh();

    expect($product->image_path)->toBeNull();
    Storage::disk('public')->assertMissing($removedPath);
});
