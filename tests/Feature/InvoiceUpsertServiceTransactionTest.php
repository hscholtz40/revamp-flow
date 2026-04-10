<?php

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Services\InvoiceUpsertService;

test('invoice upsert service rolls back persisted records when a downstream callback fails', function () {
    $company = Company::create([
        'name' => 'Invoice Service Tx Company',
        'is_active' => true,
    ]);

    $customer = Customer::create([
        'company_id' => $company->id,
        'name' => 'Invoice Service Customer',
        'email' => 'invoice-service@example.com',
        'terms' => 'COD',
    ]);

    $service = app(InvoiceUpsertService::class);

    expect(fn () => $service->createForCompany(
        validated: [
            'customer_id' => $customer->id,
            'invoice_date' => now()->toDateString(),
            'tax_rate' => 0,
            'line_items' => [[
                'description' => 'Transactional line',
                'quantity' => 1,
                'unit_price' => 125,
            ]],
        ],
        companyId: $company->id,
        invoiceNumber: 'INV-TX-ROLLBACK',
        dueDate: now()->toDateString(),
        salespersonId: 1,
        defaultAccountId: null,
        sourceJobcard: null,
        syncStockAdjustments: function () {
            throw new RuntimeException('Stop after persistence');
        },
        ensureRoundingLine: function () {}
    ))->toThrow(RuntimeException::class);

    expect(Invoice::count())->toBe(0);
    expect(InvoiceLineItem::count())->toBe(0);
});
