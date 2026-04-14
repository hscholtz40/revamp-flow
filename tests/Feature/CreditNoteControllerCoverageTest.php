<?php

use App\Models\CreditNote;
use Illuminate\Support\Facades\DB;

it('stores a credit note through the controller', function () {
    $company = coverageCreateCompany();
    $user = coverageCreateUserWithPermissions($company, [
        'credit-notes' => ['list', 'view', 'create', 'edit', 'delete'],
    ]);
    $customer = coverageSeedCustomer($company);
    $product = coverageSeedProduct($company);
    $taxRate = coverageSeedTaxRate($company);
    $account = coverageSeedChartOfAccount($company);

    $response = $this->actingAs($user)->post(route('credit-notes.store'), [
        'customer_id' => $customer->id,
        'credit_note_date' => now()->toDateString(),
        'title' => 'Coverage Credit Note',
        'line_groups' => [
            ['id' => 1, 'name' => 'Items'],
        ],
        'line_items' => [
            [
                'product_id' => $product->id,
                'description' => 'Coverage refund line',
                'quantity' => 1,
                'unit_price' => 120,
                'tax_rate_id' => $taxRate->id,
                'account_id' => $account->id,
                'line_group_id' => 1,
            ],
        ],
    ]);

    $creditNote = CreditNote::query()->where('title', 'Coverage Credit Note')->firstOrFail();

    $response->assertRedirect(route('credit-notes.show', $creditNote));

    expect($creditNote->customer_id)->toBe($customer->id)
        ->and((float) $creditNote->total)->toBeGreaterThan(0);
});

it('searches invoices and products within the current company', function () {
    $company = coverageCreateCompany();
    $user = coverageCreateUserWithPermissions($company, [
        'credit-notes' => ['list', 'view', 'create', 'edit', 'delete'],
    ]);
    $customer = coverageSeedCustomer($company, ['name' => 'Search Customer']);
    $product = coverageSeedProduct($company, ['name' => 'Search Product', 'sku' => 'SEARCH-001']);

    DB::table('invoices')->insert([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'invoice_number' => 'INV-CN-0001',
        'title' => 'Searchable Invoice',
        'status' => 'sent',
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(7)->toDateString(),
        'subtotal' => 100,
        'tax_rate' => 0,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total' => 100,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($user)->getJson(route('credit-notes.search.invoices', [
        'q' => 'INV-CN',
        'customer_id' => $customer->id,
    ]))->assertOk()
        ->assertJsonCount(1);

    $this->actingAs($user)->getJson(route('credit-notes.search.products', [
        'q' => 'SEARCH',
    ]))->assertOk()
        ->assertJsonFragment([
            'id' => $product->id,
            'name' => 'Search Product',
        ]);
});

it('updates credit note status and deletes the credit note', function () {
    $company = coverageCreateCompany();
    $user = coverageCreateUserWithPermissions($company, [
        'credit-notes' => ['list', 'view', 'create', 'edit', 'delete'],
    ]);
    $customer = coverageSeedCustomer($company);
    $creditNote = CreditNote::create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'credit_note_number' => 'CN-COVERAGE-0001',
        'credit_note_date' => now()->toDateString(),
        'status' => 'draft',
        'subtotal' => 50,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total' => 50,
        'remaining_credit' => 50,
    ]);

    $this->from(route('credit-notes.show', $creditNote))
        ->actingAs($user)
        ->patch(route('credit-notes.update-status', $creditNote), [
            'status' => 'submitted',
        ])->assertRedirect(route('credit-notes.show', $creditNote));

    $this->actingAs($user)->delete(route('credit-notes.destroy', $creditNote))
        ->assertRedirect(route('credit-notes.index'));

    expect(CreditNote::find($creditNote->id))->toBeNull();
});
