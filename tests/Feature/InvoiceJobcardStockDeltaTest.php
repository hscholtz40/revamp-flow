<?php

use App\Models\Company;
use App\Models\Customer;
use App\Models\Group;
use App\Models\GroupPermission;
use App\Models\Invoice;
use App\Models\Jobcard;
use App\Models\JobcardLineItem;
use App\Models\Product;
use App\Models\User;

function createInvoiceEditorForStockDeltaTests(): array
{
    $company = Company::create([
        'name' => 'Invoice Stock Delta Co '.uniqid(),
        'is_active' => true,
    ]);

    $user = User::factory()->create([
        'current_company_id' => $company->id,
    ]);

    $group = Group::create([
        'name' => 'Invoice Stock Delta Permissions '.uniqid(),
    ]);

    GroupPermission::create([
        'group_id' => $group->id,
        'module' => 'invoices',
        'can_view' => true,
        'can_list' => true,
        'can_create' => true,
        'can_edit' => true,
        'can_delete' => true,
    ]);

    $user->groups()->attach($group->id);
    $user->companies()->attach($company->id);

    return [$company, $user];
}

function createJobcardSourceForInvoiceDeltaTests(Company $company): array
{
    $customer = Customer::create([
        'company_id' => $company->id,
        'name' => 'Invoice Delta Customer',
        'email' => 'invoice-delta-customer@example.com',
        'terms' => 'COD',
    ]);

    $product = Product::create([
        'company_id' => $company->id,
        'name' => 'Tracked Delta Product',
        'price' => 100,
        'stock_quantity' => 8,
        'track_stock' => true,
        'is_active' => true,
    ]);

    $jobcard = Jobcard::create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'job_number' => 'JC-DELTA-'.uniqid(),
        'title' => 'Delta Source Jobcard',
        'status' => 'in_progress',
        'subtotal' => 200,
        'discount_amount' => 0,
        'discount_percentage' => 0,
        'tax_rate' => 0,
        'tax_amount' => 0,
        'total' => 200,
    ]);

    JobcardLineItem::create([
        'jobcard_id' => $jobcard->id,
        'product_id' => $product->id,
        'description' => 'Tracked Delta Product',
        'quantity' => 2,
        'unit_price' => 100,
        'discount_amount' => 0,
        'discount_percentage' => 0,
        'total' => 200,
        'sort_order' => 0,
    ]);

    return [$customer, $product, $jobcard];
}

function invoicePayloadForJobcardDeltaTests(Customer $customer, Jobcard $jobcard, int $quantity): array
{
    return [
        'customer_id' => $customer->id,
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->toDateString(),
        'tax_rate' => 0,
        'source_type' => 'jobcard',
        'source_id' => $jobcard->id,
        'line_items' => [[
            'product_id' => $jobcard->lineItems()->value('product_id'),
            'description' => 'Tracked Delta Product',
            'quantity' => $quantity,
            'unit_price' => 100,
            'discount_amount' => 0,
            'discount_percentage' => 0,
        ]],
    ];
}

test('invoice creation from a jobcard only applies stock delta beyond the jobcard quantity', function () {
    [$company, $user] = createInvoiceEditorForStockDeltaTests();
    [$customer, $product, $jobcard] = createJobcardSourceForInvoiceDeltaTests($company);

    $this->actingAs($user)
        ->post(route('invoices.store'), invoicePayloadForJobcardDeltaTests($customer, $jobcard, 2))
        ->assertRedirect();

    expect($product->fresh()->stock_quantity)->toBe(8);
});

test('updating a jobcard sourced invoice applies only the delta change against the source jobcard', function () {
    [$company, $user] = createInvoiceEditorForStockDeltaTests();
    [$customer, $product, $jobcard] = createJobcardSourceForInvoiceDeltaTests($company);

    $this->actingAs($user)
        ->post(route('invoices.store'), invoicePayloadForJobcardDeltaTests($customer, $jobcard, 2))
        ->assertRedirect();

    $invoice = Invoice::query()->latest('id')->firstOrFail();

    expect($product->fresh()->stock_quantity)->toBe(8);

    $this->actingAs($user)
        ->put(route('invoices.update', $invoice), [
            'customer_id' => $customer->id,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'tax_rate' => 0,
            'line_items' => [[
                'product_id' => $product->id,
                'description' => 'Tracked Delta Product',
                'quantity' => 4,
                'unit_price' => 100,
                'discount_amount' => 0,
                'discount_percentage' => 0,
            ]],
        ])
        ->assertRedirect();

    expect($product->fresh()->stock_quantity)->toBe(6);

    $this->actingAs($user)
        ->put(route('invoices.update', $invoice->fresh()), [
            'customer_id' => $customer->id,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'tax_rate' => 0,
            'line_items' => [[
                'product_id' => $product->id,
                'description' => 'Tracked Delta Product',
                'quantity' => 1,
                'unit_price' => 100,
                'discount_amount' => 0,
                'discount_percentage' => 0,
            ]],
        ])
        ->assertRedirect();

    expect($product->fresh()->stock_quantity)->toBe(9);
});
