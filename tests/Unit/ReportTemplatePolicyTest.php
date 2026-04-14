<?php

use App\Models\ReportTemplate;
use App\Policies\ReportTemplatePolicy;

it('allows users to access company-owned and global report templates', function () {
    $company = coverageCreateCompany();
    $user = coverageCreateUserWithPermissions($company, []);

    $companyTemplate = ReportTemplate::create([
        'company_id' => $company->id,
        'name' => 'Company Template',
        'entity_type' => 'invoice',
        'config' => ['columns' => ['invoice_number']],
        'filters' => ['status' => 'draft'],
        'created_by' => $user->id,
    ]);

    $globalTemplate = ReportTemplate::create([
        'company_id' => null,
        'name' => 'Global Template',
        'entity_type' => 'invoice',
        'config' => ['columns' => ['invoice_number']],
        'filters' => [],
        'is_default' => true,
        'created_by' => $user->id,
    ]);

    $policy = new ReportTemplatePolicy();

    expect($policy->viewAny($user))->toBeTrue()
        ->and($policy->create($user))->toBeTrue()
        ->and($policy->view($user, $companyTemplate))->toBeTrue()
        ->and($policy->update($user, $companyTemplate))->toBeTrue()
        ->and($policy->delete($user, $companyTemplate))->toBeTrue()
        ->and($policy->view($user, $globalTemplate))->toBeTrue()
        ->and($policy->update($user, $globalTemplate))->toBeTrue()
        ->and($policy->delete($user, $globalTemplate))->toBeTrue();
});

it('denies access to another company template', function () {
    $company = coverageCreateCompany();
    $otherCompany = coverageCreateCompany(['name' => 'Other Template Co']);
    $user = coverageCreateUserWithPermissions($company, []);

    $template = ReportTemplate::create([
        'company_id' => $otherCompany->id,
        'name' => 'Other Company Template',
        'entity_type' => 'quote',
        'config' => ['columns' => ['quote_number']],
        'filters' => [],
        'created_by' => $user->id,
    ]);

    $policy = new ReportTemplatePolicy();

    expect($policy->view($user, $template))->toBeFalse()
        ->and($policy->update($user, $template))->toBeFalse()
        ->and($policy->delete($user, $template))->toBeFalse();
});
