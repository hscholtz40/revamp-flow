<?php

namespace App\Support;

use Illuminate\Support\Collection;

class DocumentLineGroupResolver
{
    public static function isRoundingAdjustmentLine(?string $description): bool
    {
        return strtolower(trim((string) $description)) === 'rounding adjustment';
    }

    /**
     * @param  Collection<int, mixed>  $lineGroups
     * @param  Collection<int, mixed>  $lineItems
     * @return Collection<int, object{name: string, items: Collection<int, mixed>, subtotal: float}>
     */
    public static function resolve(Collection $lineGroups, Collection $lineItems, bool $excludeRounding = true): Collection
    {
        $items = $excludeRounding
            ? $lineItems->filter(fn ($item) => ! self::isRoundingAdjustmentLine($item->description ?? null))
            : $lineItems;

        $resolvedGroups = collect();
        $renderedItemIds = collect();

        foreach ($lineGroups->sortBy('sort_order') as $group) {
            $groupItems = $items->where('line_group_id', $group->id);
            if ($groupItems->isNotEmpty()) {
                $resolvedGroups->push((object) [
                    'name' => $group->name,
                    'items' => $groupItems,
                    'subtotal' => (float) $groupItems->sum(fn ($item) => (float) ($item->total ?? 0)),
                ]);
                $renderedItemIds = $renderedItemIds->merge($groupItems->pluck('id'));
            }
        }

        $ungroupedItems = $items->filter(fn ($item) => ! $renderedItemIds->contains($item->id));

        if ($resolvedGroups->isEmpty() && $items->isNotEmpty()) {
            $resolvedGroups->push((object) [
                'name' => 'Items',
                'items' => $items,
                'subtotal' => (float) $items->sum(fn ($item) => (float) ($item->total ?? 0)),
            ]);
        } elseif ($ungroupedItems->isNotEmpty()) {
            $resolvedGroups->push((object) [
                'name' => 'Items',
                'items' => $ungroupedItems,
                'subtotal' => (float) $ungroupedItems->sum(fn ($item) => (float) ($item->total ?? 0)),
            ]);
        }

        return $resolvedGroups;
    }
}
