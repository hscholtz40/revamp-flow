<?php

use App\Support\DocumentLineGroupResolver;
use Illuminate\Support\Collection;

it('resolves line groups with subtotals for quotes and invoices', function () {
    $lineGroups = collect([
        (object) ['id' => 1, 'name' => 'Labour', 'sort_order' => 0],
        (object) ['id' => 2, 'name' => 'Materials', 'sort_order' => 1],
    ]);

    $lineItems = collect([
        (object) ['id' => 10, 'line_group_id' => 1, 'description' => 'Install pump', 'total' => 100.0],
        (object) ['id' => 11, 'line_group_id' => 1, 'description' => 'Call-out', 'total' => 50.0],
        (object) ['id' => 12, 'line_group_id' => 2, 'description' => 'Pipe fittings', 'total' => 75.5],
        (object) ['id' => 13, 'line_group_id' => null, 'description' => 'Rounding adjustment', 'total' => 0.1],
    ]);

    $resolved = DocumentLineGroupResolver::resolve($lineGroups, $lineItems);

    expect($resolved)->toHaveCount(2)
        ->and($resolved[0]->name)->toBe('Labour')
        ->and($resolved[0]->subtotal)->toBe(150.0)
        ->and($resolved[1]->name)->toBe('Materials')
        ->and($resolved[1]->subtotal)->toBe(75.5);
});

it('places ungrouped items into an items group', function () {
    $lineGroups = collect([
        (object) ['id' => 1, 'name' => 'Labour', 'sort_order' => 0],
    ]);

    $lineItems = collect([
        (object) ['id' => 10, 'line_group_id' => 1, 'description' => 'Work', 'total' => 40.0],
        (object) ['id' => 11, 'line_group_id' => 99, 'description' => 'Loose item', 'total' => 10.0],
    ]);

    $resolved = DocumentLineGroupResolver::resolve($lineGroups, $lineItems);

    expect($resolved)->toHaveCount(2)
        ->and($resolved[1]->name)->toBe('Items')
        ->and($resolved[1]->subtotal)->toBe(10.0);
});
