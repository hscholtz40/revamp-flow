<?php

use App\Models\Customer;
use App\Models\GoogleIntegrationSettings;
use App\Models\Jobcard;
use Illuminate\Support\Carbon;

it('blocks ai endpoints when ai is disabled', function () {
    $company = coverageCreateCompany();
    $user = coverageCreateUserWithPermissions($company, [
        'jobcards' => ['list', 'view', 'create', 'edit'],
    ], [
        'email_verified_at' => Carbon::now(),
    ]);

    GoogleIntegrationSettings::record()->update([
        'openai_api_key' => 'sk-test-disabled',
        'ai_enabled' => false,
    ]);

    $response = $this->actingAs($user)->postJson('/ai/draft', [
        'prompt' => 'Draft text',
    ]);

    $response->assertStatus(403);
});

it('returns company-scoped assistant results', function () {
    $companyA = coverageCreateCompany(['name' => 'A']);
    $companyB = coverageCreateCompany(['name' => 'B']);
    $user = coverageCreateUserWithPermissions($companyA, [
        'jobcards' => ['list', 'view', 'create', 'edit'],
        'customers' => ['list', 'view', 'create', 'edit'],
    ], [
        'email_verified_at' => Carbon::now(),
    ]);

    GoogleIntegrationSettings::record()->update([
        'openai_api_key' => 'sk-test-enabled',
        'ai_enabled' => true,
        'ai_admin_only' => false,
        'ai_daily_user_limit' => 50,
        'ai_daily_company_limit' => 500,
    ]);

    Customer::create([
        'company_id' => $companyA->id,
        'name' => 'Scoped Customer Alpha',
        'email' => 'alpha@example.test',
    ]);
    Customer::create([
        'company_id' => $companyB->id,
        'name' => 'Scoped Customer Beta',
        'email' => 'beta@example.test',
    ]);

    $customerA = Customer::create([
        'company_id' => $companyA->id,
        'name' => 'Scoped Job Customer A',
        'email' => 'job-a@example.test',
    ]);
    $customerB = Customer::create([
        'company_id' => $companyB->id,
        'name' => 'Scoped Job Customer B',
        'email' => 'job-b@example.test',
    ]);

    Jobcard::create([
        'company_id' => $companyA->id,
        'customer_id' => $customerA->id,
        'job_number' => 'JC-A-100',
        'status' => 'new',
        'title' => 'Scoped Jobcard Alpha',
    ]);
    Jobcard::create([
        'company_id' => $companyB->id,
        'customer_id' => $customerB->id,
        'job_number' => 'JC-B-999',
        'status' => 'new',
        'title' => 'Scoped Jobcard Beta',
    ]);

    $response = $this->actingAs($user)->postJson('/ai/assistant', [
        'query' => 'Scoped',
    ]);

    $response->assertOk();
    $response->assertJsonPath('results.customers.0.name', 'Scoped Customer Alpha');
    $response->assertJsonMissing(['name' => 'Scoped Customer Beta']);
    $response->assertJsonMissing(['job_number' => 'JC-B-999']);
});
