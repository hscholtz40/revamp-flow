<?php

use App\Support\ProductImageStorage;

test('product image storage resolves managed paths to public urls', function () {
    expect(ProductImageStorage::url('product-images/1/example.jpg'))
        ->toBe('/storage/product-images/1/example.jpg');
});

test('product image storage preserves external urls', function () {
    expect(ProductImageStorage::url('https://cdn.example.com/item.jpg'))
        ->toBe('https://cdn.example.com/item.jpg');
});

test('product image storage identifies managed paths', function () {
    expect(ProductImageStorage::isManagedPath('product-images/1/example.jpg'))->toBeTrue()
        ->and(ProductImageStorage::isManagedPath('https://cdn.example.com/item.jpg'))->toBeFalse()
        ->and(ProductImageStorage::isManagedPath('/images/example.jpg'))->toBeFalse();
});
