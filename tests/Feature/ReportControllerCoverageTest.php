<?php

use App\Models\Report;
use App\Models\ReportTemplate;
use Illuminate\Support\Facades\DB;

it('loads the reports index and create pages with company-scoped data', function () {
    $company = coverageCreateCompany();
    $user = coverageCreateUserWithPermissions($company, [
        'reports' => ['list', 'create', 'view'],
    ]);
    $customer = coverageSeedCustomer($company, ['name' => 'Report Customer']);
    coverageSeedProduct($company, ['name' => 'Report Product']);

    ReportTemplate::create([
        'company_id' => $company->id,
        'name' => 'Invoice Template',
        'entity_type' => 'invoice',
        'config' => ['columns' => ['number', 'total']],
        'filters' => ['customer_id' => $customer->id],
        'created_by' => $user->id,
    ]);

    $report = Report::create([
        'company_id' => $company->id,
        'name' => 'Invoice Report',
        'entity_type' => 'invoice',
        'config' => ['columns' => ['number', 'total']],
        'created_by' => $user->id,
    ]);

    $this->actingAs($user)->get(route('reports.index'))
        ->assertOk();

    $this->actingAs($user)->get(route('reports.create', [
        'entity_type' => 'invoice',
    ]))->assertOk();

    $this->actingAs($user)->get(route('reports.show', $report))
        ->assertOk();
});

it('stores reports and returns report data for matching invoices', function () {
    $company = coverageCreateCompany();
    $user = coverageCreateUserWithPermissions($company, [
        'reports' => ['list', 'create', 'view'],
    ]);
    $customer = coverageSeedCustomer($company);

    DB::table('invoices')->insert([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'invoice_number' => 'INV-REPORT-0001',
        'title' => 'Coverage Invoice',
        'status' => 'draft',
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

    $response = $this->actingAs($user)->post(route('reports.store'), [
        'name' => 'Stored Coverage Report',
        'entity_type' => 'invoice',
        'config' => [
            'columns' => ['number', 'status', 'formatted_total'],
        ],
    ]);

    $report = Report::query()->where('name', 'Stored Coverage Report')->firstOrFail();

    $response->assertRedirect(route('reports.show', $report));

    $this->actingAs($user)->getJson(route('reports.data', $report))
        ->assertOk()
        ->assertJsonPath('count', 1);
});

it('saves and deletes report templates when not in use', function () {
    $company = coverageCreateCompany();
    $user = coverageCreateUserWithPermissions($company, [
        'reports' => ['list', 'create', 'delete'],
    ]);

    $this->actingAs($user)->post(route('reports.save-template'), [
        'name' => 'Coverage Template',
        'entity_type' => 'invoice',
        'description' => 'Template description',
        'config' => ['columns' => ['number', 'formatted_total']],
        'filters' => ['status' => 'draft'],
        'is_default' => true,
    ])->assertRedirect(route('reports.index'));

    $template = ReportTemplate::query()->where('name', 'Coverage Template')->firstOrFail();

    $this->actingAs($user)->delete(route('reports.templates.destroy', $template))
        ->assertRedirect(route('reports.index'));

    expect(ReportTemplate::find($template->id))->toBeNull();
});
