<?php

use App\Support\ColumnFilters;
use Illuminate\Http\Request;

it('extracts only non-empty prefixed column filters', function () {
    $request = Request::create('/invoices', 'GET', [
        'search' => 'invoice',
        'colf_customer_name' => ' ACME ',
        'colf_status' => 'paid',
        'colf_blank' => '   ',
        'sort' => 'created_at',
    ]);

    expect(ColumnFilters::fromRequest($request)->all())->toBe([
        'customer_name' => 'ACME',
        'status' => 'paid',
    ]);
});

it('returns an empty collection when no column filters are present', function () {
    $request = Request::create('/quotes', 'GET', [
        'search' => 'test',
        'status' => 'draft',
    ]);

    expect(ColumnFilters::fromRequest($request)->all())->toBe([]);
});
