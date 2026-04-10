<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;

final class ColumnFilters
{
    public static function fromRequest(Request $request): Collection
    {
        return collect($request->query())
            ->filter(fn ($value, $key) => str_starts_with((string) $key, 'colf_'))
            ->mapWithKeys(function ($value, $key) {
                $trimmed = trim((string) $value);

                return [substr((string) $key, 5) => $trimmed];
            })
            ->filter(fn ($value) => $value !== '');
    }
}
