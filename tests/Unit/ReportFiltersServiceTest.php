<?php

use App\Models\Report;
use App\Models\ReportTemplate;
use App\Services\ReportFiltersService;
use Illuminate\Http\Request;

test('report filters service merges template filters with runtime overrides', function () {
    $report = new Report;
    $report->setRelation('template', new ReportTemplate([
        'filters' => [
            'customer_id' => [4],
            'product_id' => [9],
            'date_from' => '2026-01-01',
            'status' => ['draft'],
        ],
    ]));

    $request = Request::create('/reports/demo', 'GET', [
        'customer_id' => [7],
        'date_to' => '2026-01-31',
    ]);

    $filters = app(ReportFiltersService::class)->mergeTemplateAndRequestFilters($report, $request);

    expect($filters)->toBe([
        'customer_id' => [7],
        'product_id' => [9],
        'status' => ['draft'],
        'date_from' => '2026-01-01',
        'date_to' => '2026-01-31',
    ]);
});

test('report filters service ignores blank export overrides when filled only is enabled', function () {
    $report = new Report;
    $report->setRelation('template', new ReportTemplate([
        'filters' => [
            'date_from' => '2026-02-01',
            'status' => ['paid'],
        ],
    ]));

    $request = Request::create('/reports/demo/export', 'GET', [
        'date_from' => '',
        'status' => [],
    ]);

    $filters = app(ReportFiltersService::class)->mergeTemplateAndRequestFilters($report, $request, true);

    expect($filters)->toBe([
        'status' => ['paid'],
        'date_from' => '2026-02-01',
    ]);
});
