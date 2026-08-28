export function isRoundingAdjustmentDescription(description: string | null | undefined): boolean {
    return String(description ?? '').trim().toLowerCase() === 'rounding adjustment';
}

export function sumLineItemTotals(
    items: Array<{ total?: number | string | null }>,
): number {
    return items.reduce((sum, item) => sum + (Number(item.total) || 0), 0);
}

type LineGroupLike = { id?: number | string; name?: string | null; sort_order?: number | null };
type LineItemLike = {
    id?: number | string;
    line_group_id?: number | string | null;
    sort_order?: number | null;
    total?: number | string | null;
    description?: string | null;
};

export type ResolvedLineGroup<TItem extends LineItemLike = LineItemLike> = {
    groupId: number | string;
    groupName: string;
    items: TItem[];
    subtotal: number;
};

export function resolveLineGroupsWithTotals<TItem extends LineItemLike>(
    lineItems: TItem[],
    lineGroups: LineGroupLike[],
    options?: {
        excludeRounding?: boolean;
        getItemTotal?: (item: TItem) => number;
    },
): ResolvedLineGroup<TItem>[] {
    const excludeRounding = options?.excludeRounding ?? true;
    const getItemTotal = options?.getItemTotal ?? ((item: TItem) => Number(item.total) || 0);

    const baseItems = excludeRounding
        ? lineItems.filter((item) => !isRoundingAdjustmentDescription(item.description))
        : [...lineItems];

    const groups = [...lineGroups].sort(
        (a, b) => Number(a.sort_order ?? 0) - Number(b.sort_order ?? 0),
    );
    const fallbackGroupId = groups[0]?.id ?? 1;
    const usedIds = new Set<number | string>();

    const grouped = groups
        .map((group) => {
            const items = baseItems.filter(
                (item) => (item.line_group_id ?? fallbackGroupId) === group.id,
            );
            items.forEach((item) => {
                if (item.id !== undefined) {
                    usedIds.add(item.id);
                }
            });

            return {
                groupId: group.id ?? -1,
                groupName: group.name || 'Items',
                items,
                subtotal: items.reduce((sum, item) => sum + getItemTotal(item), 0),
            };
        })
        .filter((group) => group.items.length > 0);

    const ungroupedItems = baseItems.filter((item) => item.id === undefined || !usedIds.has(item.id));
    if (ungroupedItems.length > 0) {
        grouped.push({
            groupId: -1,
            groupName: grouped.length === 0 ? 'Items' : 'Ungrouped',
            items: ungroupedItems,
            subtotal: ungroupedItems.reduce((sum, item) => sum + getItemTotal(item), 0),
        });
    }

    if (grouped.length === 0 && baseItems.length > 0) {
        grouped.push({
            groupId: -1,
            groupName: 'Items',
            items: baseItems,
            subtotal: baseItems.reduce((sum, item) => sum + getItemTotal(item), 0),
        });
    }

    return grouped;
}
