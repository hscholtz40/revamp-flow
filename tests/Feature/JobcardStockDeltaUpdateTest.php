<?php

use App\Models\Company;
use App\Models\Customer;
use App\Models\Group;
use App\Models\GroupPermission;
use App\Models\Jobcard;
use App\Models\JobcardLineItem;
use App\Models\Product;
use App\Models\User;

function createJobcardEditorForStockDeltaTests(): array
{
    $company = Company::create([
        'name' => 'Jobcard Stock Delta Co '.uniqid(),
        'is_active' => true,
    ]);

    $user = User::factory()->create([
        'current_company_id' => $company->id,
    ]);

    $group = Group::create([
        'name' => 'Jobcard Stock Delta Permissions '.uniqid(),
    ]);

    GroupPermission::create([
        'group_id' => $group->id,
        'module' => 'jobcards',
        'can_view' => true,
        'can_list' => true,
        'can_create' => true,
        'can_edit' => true,
        'can_delete' => true,
        'can_edit_completed' => true,
    ]);

    $user->groups()->attach($group->id);
    $user->companies()->attach($company->id);

    return [$company, $user];
}

test('jobcard updates apply stock deltas for changed and newly added line items', function () {
    [$company, $user] = createJobcardEditorForStockDeltaTests();
    $this->actingAs($user);

    $customer = Customer::create([
        'company_id' => $company->id,
        'name' => 'Jobcard Delta Customer',
        'email' => 'jobcard-delta-customer@example.com',
    ]);

    $productA = Product::create([
        'company_id' => $company->id,
        'name' => 'Tracked Product A',
        'price' => 100,
        'stock_quantity' => 8,
        'track_stock' => true,
        'is_active' => true,
    ]);

    $productB = Product::create([
        'company_id' => $company->id,
        'name' => 'Tracked Product B',
        'price' => 50,
        'stock_quantity' => 5,
        'track_stock' => true,
        'is_active' => true,
    ]);

    $jobcard = Jobcard::create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'job_number' => 'JC-STOCK-'.uniqid(),
        'title' => 'Stock Delta Jobcard',
        'status' => 'on_site',
        'subtotal' => 200,
        'discount_amount' => 0,
        'discount_percentage' => 0,
        'tax_rate' => 0,
        'tax_amount' => 0,
        'total' => 200,
    ]);

    $lineItem = JobcardLineItem::create([
        'jobcard_id' => $jobcard->id,
        'product_id' => $productA->id,
        'description' => 'Tracked Product A',
        'quantity' => 2,
        'unit_price' => 100,
        'discount_amount' => 0,
        'discount_percentage' => 0,
        'total' => 200,
        'sort_order' => 0,
    ]);

    $this->put(route('jobcards.update', $jobcard), [
        'customer_id' => $customer->id,
        'title' => 'Stock Delta Jobcard',
        'status' => 'on_site',
        'tax_rate' => 0,
        'line_items' => [
            [
                'id' => $lineItem->id,
                'product_id' => $productA->id,
                'description' => 'Tracked Product A',
                'quantity' => 4,
                'unit_price' => 100,
                'discount_amount' => 0,
                'discount_percentage' => 0,
            ],
            [
                'product_id' => $productB->id,
                'description' => 'Tracked Product B',
                'quantity' => 3,
                'unit_price' => 50,
                'discount_amount' => 0,
                'discount_percentage' => 0,
            ],
        ],
    ])->assertRedirect(route('jobcards.show', $jobcard));

    expect($productA->fresh()->stock_quantity)->toBe(6);
    expect($productB->fresh()->stock_quantity)->toBe(2);

    $this->put(route('jobcards.update', $jobcard->fresh()), [
        'customer_id' => $customer->id,
        'title' => 'Stock Delta Jobcard',
        'status' => 'on_site',
        'tax_rate' => 0,
        'line_items' => [
            [
                'id' => $lineItem->id,
                'product_id' => $productA->id,
                'description' => 'Tracked Product A',
                'quantity' => 1,
                'unit_price' => 100,
                'discount_amount' => 0,
                'discount_percentage' => 0,
            ],
        ],
    ])->assertRedirect(route('jobcards.show', $jobcard));

    expect($productA->fresh()->stock_quantity)->toBe(9);
    expect($productB->fresh()->stock_quantity)->toBe(5);
});
