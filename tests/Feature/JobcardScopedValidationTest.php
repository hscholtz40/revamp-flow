<?php

use App\Models\Company;
use App\Models\Group;
use App\Models\GroupPermission;
use App\Models\User;
use Illuminate\Support\Facades\DB;

test('jobcard store rejects foreign-company customer id', function () {
    $companyA = Company::create([
        'name' => 'Jobcard Scoped A',
        'is_active' => true,
    ]);
    $companyB = Company::create([
        'name' => 'Jobcard Scoped B',
        'is_active' => true,
    ]);

    $user = User::factory()->create(['current_company_id' => $companyA->id]);
    $user->companies()->attach($companyA->id);

    $group = Group::create(['name' => 'Jobcard Scoped Creators '.uniqid()]);
    GroupPermission::create([
        'group_id' => $group->id,
        'module' => 'jobcards',
        'can_view' => true,
        'can_list' => true,
        'can_create' => true,
        'can_edit' => true,
        'can_delete' => false,
    ]);
    $user->groups()->attach($group->id);

    $foreignCustomerId = (int) DB::table('customers')->insertGetId([
        'company_id' => $companyB->id,
        'name' => 'Foreign Jobcard Customer',
        'email' => 'foreign-jobcard-customer@example.com',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->actingAs($user)->post(route('jobcards.store'), [
        'customer_id' => $foreignCustomerId,
        'title' => 'Scoped validation jobcard',
        'status' => 'draft',
        'line_items' => [[
            'description' => 'Line item',
            'quantity' => 1,
            'unit_price' => 99,
        ]],
    ]);

    $response->assertSessionHasErrors(['customer_id']);
});
