<?php

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;

it('stores a purchase order through the controller', function () {
    $company = coverageCreateCompany();
    $user = coverageCreateUserWithPermissions($company, [
        'purchase-orders' => ['list', 'view', 'create', 'edit', 'delete'],
    ]);
    $supplier = coverageSeedSupplier($company);
    $product = coverageSeedProduct($company);
    $taxRate = coverageSeedTaxRate($company);

    $response = $this->actingAs($user)->post(route('purchase-orders.store'), [
        'supplier_id' => $supplier->id,
        'order_date' => now()->toDateString(),
        'expected_delivery_date' => now()->addDays(2)->toDateString(),
        'line_groups' => [
            ['id' => 1, 'name' => 'Items'],
        ],
        'items' => [
            [
                'product_id' => $product->id,
                'quantity' => 2,
                'unit_cost' => 90,
                'description' => 'Coverage item',
                'tax_rate_id' => $taxRate->id,
                'line_group_id' => 1,
            ],
        ],
    ]);

    $purchaseOrder = PurchaseOrder::query()->where('supplier_id', $supplier->id)->latest('id')->firstOrFail();

    $response->assertRedirect(route('purchase-orders.show', $purchaseOrder));

    expect($purchaseOrder->status)->toBe('draft')
        ->and((float) $purchaseOrder->total)->toBeGreaterThan(0);
});

it('updates and marks a purchase order as received', function () {
    $company = coverageCreateCompany();
    $user = coverageCreateUserWithPermissions($company, [
        'purchase-orders' => ['list', 'view', 'create', 'edit', 'delete'],
    ]);
    $supplier = coverageSeedSupplier($company);
    $product = coverageSeedProduct($company, ['track_stock' => false]);
    $taxRate = coverageSeedTaxRate($company);

    $this->actingAs($user)->post(route('purchase-orders.store'), [
        'supplier_id' => $supplier->id,
        'order_date' => now()->toDateString(),
        'line_groups' => [
            ['id' => 1, 'name' => 'Items'],
        ],
        'items' => [
            [
                'product_id' => $product->id,
                'quantity' => 1,
                'unit_cost' => 50,
                'description' => 'Original item',
                'tax_rate_id' => $taxRate->id,
                'line_group_id' => 1,
            ],
        ],
    ]);

    $purchaseOrder = PurchaseOrder::query()->latest('id')->firstOrFail();

    $this->actingAs($user)->put(route('purchase-orders.update', $purchaseOrder), [
        'supplier_id' => $supplier->id,
        'order_date' => now()->toDateString(),
        'notes' => 'Updated note',
        'line_groups' => [
            ['id' => 1, 'name' => 'Updated Items'],
        ],
        'items' => [
            [
                'product_id' => $product->id,
                'quantity' => 3,
                'unit_cost' => 75,
                'description' => 'Updated item',
                'tax_rate_id' => $taxRate->id,
                'line_group_id' => 1,
            ],
        ],
    ])->assertRedirect(route('purchase-orders.show', $purchaseOrder));

    $this->from(route('purchase-orders.show', $purchaseOrder))
        ->actingAs($user)
        ->put(route('purchase-orders.update-status', $purchaseOrder), [
            'status' => 'received',
        ])->assertRedirect(route('purchase-orders.show', $purchaseOrder));

    $purchaseOrder->refresh();

    expect($purchaseOrder->status)->toBe('received')
        ->and($purchaseOrder->received_date)->not->toBeNull();
});

it('prevents editing a purchase order once stock has been received', function () {
    $company = coverageCreateCompany();
    $user = coverageCreateUserWithPermissions($company, [
        'purchase-orders' => ['list', 'view', 'create', 'edit', 'delete'],
    ]);
    $supplier = coverageSeedSupplier($company);
    $product = coverageSeedProduct($company, ['track_stock' => false]);

    $purchaseOrder = PurchaseOrder::create([
        'company_id' => $company->id,
        'supplier_id' => $supplier->id,
        'po_number' => 'PO-COVERAGE-0001',
        'order_date' => now()->toDateString(),
        'status' => 'received',
        'received_date' => now(),
        'subtotal' => 100,
        'tax_amount' => 0,
        'total' => 100,
        'user_id' => $user->id,
    ]);

    $lineGroupId = $purchaseOrder->lineGroups()->create([
        'name' => 'Items',
        'sort_order' => 0,
    ])->id;

    PurchaseOrderItem::create([
        'purchase_order_id' => $purchaseOrder->id,
        'line_group_id' => $lineGroupId,
        'product_id' => $product->id,
        'quantity' => 2,
        'unit_cost' => 50,
        'quantity_received' => 1,
        'description' => 'Received item',
    ]);

    $this->actingAs($user)->get(route('purchase-orders.edit', $purchaseOrder))
        ->assertRedirect(route('purchase-orders.show', $purchaseOrder));
});
