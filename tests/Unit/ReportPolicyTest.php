<?php

use App\Models\Report;
use App\Policies\ReportPolicy;

it('allows report actions for resources in the users current company', function () {
    $company = coverageCreateCompany();
    $user = coverageCreateUserWithPermissions($company, []);
    $report = Report::create([
        'company_id' => $company->id,
        'name' => 'Coverage Report',
        'entity_type' => 'invoice',
        'config' => ['columns' => ['invoice_number']],
        'created_by' => $user->id,
    ]);

    $policy = new ReportPolicy();

    expect($policy->viewAny($user))->toBeTrue()
        ->and($policy->create($user))->toBeTrue()
        ->and($policy->view($user, $report))->toBeTrue()
        ->and($policy->update($user, $report))->toBeTrue()
        ->and($policy->delete($user, $report))->toBeTrue()
        ->and($policy->export($user, $report))->toBeTrue();
});

it('denies access to reports owned by another company', function () {
    $company = coverageCreateCompany();
    $otherCompany = coverageCreateCompany(['name' => 'Other Coverage Co']);
    $user = coverageCreateUserWithPermissions($company, []);
    $report = Report::create([
        'company_id' => $otherCompany->id,
        'name' => 'Other Report',
        'entity_type' => 'invoice',
        'config' => ['columns' => ['invoice_number']],
        'created_by' => $user->id,
    ]);

    $policy = new ReportPolicy();

    expect($policy->view($user, $report))->toBeFalse()
        ->and($policy->update($user, $report))->toBeFalse()
        ->and($policy->delete($user, $report))->toBeFalse()
        ->and($policy->export($user, $report))->toBeFalse();
});
