<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { getCsrfToken } from '@/lib/csrf';
import { GripVertical, Settings2, X } from 'lucide-vue-next';
import { computed, nextTick, onUnmounted, ref, watch } from 'vue';

interface ColumnPref {
    id: number;
    label: string;
    filterKey: string;
    visible: boolean;
    order: number;
}

const page = usePage();
const isOpen = ref(false);
const hasTable = ref(false);
const columns = ref<ColumnPref[]>([]);
const dragIndex = ref<number | null>(null);
const isSaving = ref(false);
const saveTimer = ref<ReturnType<typeof setTimeout> | null>(null);
const loadTimer = ref<ReturnType<typeof setTimeout> | null>(null);
const columnFilters = ref<Record<string, string>>({});
const pendingColumnFilters = ref<Record<string, string>>({});

const pageKey = computed(() => String(page.component ?? ''));
const pageUrl = computed(() => String((page as any).url ?? ''));
const isIndexView = computed(() => pageKey.value.endsWith('/Index'));

function getMainTable(): HTMLTableElement | null {
    return document.querySelector('[data-list-view-table="true"]') as HTMLTableElement | null;
}

function normalizeFilterKey(input: string): string {
    const key = (input || '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '_')
        .replace(/^_+|_+$/g, '')
        .replace(/_+/g, '_');
    return key || 'column';
}

function parseColumnFiltersFromUrl(url: string): Record<string, string> {
    const queryPart = url.includes('?') ? url.split('?')[1] : '';
    const params = new URLSearchParams(queryPart || '');
    const parsed: Record<string, string> = {};
    params.forEach((value, key) => {
        if (!key.startsWith('colf_')) return;
        const filterKey = key.slice(5);
        if (!filterKey) return;
        const trimmed = (value || '').trim();
        if (!trimmed) return;
        parsed[filterKey] = trimmed;
    });
    return parsed;
}

function extractColumnsFromTable(table: HTMLTableElement): ColumnPref[] {
    const headerRow = table.querySelector('thead tr');
    if (!headerRow) return [];

    const headerCells = Array.from(headerRow.children) as HTMLElement[];
    return headerCells.map((cell, idx) => {
        const label = (cell.dataset.colLabel || cell.textContent || `Column ${idx + 1}`)
            .replace(/[↕↑↓]/g, '')
            .replace(/\s+/g, ' ')
            .trim();
        const filterKey = cell.dataset.colFilterKey || normalizeFilterKey(label);
        return {
            id: idx,
        // Use source textContent (not rendered innerText) so Tailwind uppercase styles
        // don't force labels to appear in all-caps in the column editor.
            label,
            filterKey,
            visible: cell.dataset.colDefaultVisible !== 'false',
            order: idx,
        };
    });
}

function applyColumnsToTable(table: HTMLTableElement, prefs: ColumnPref[]) {
    const rows = Array.from(table.querySelectorAll('tr')) as HTMLElement[];
    const sorted = [...prefs].sort((a, b) => a.order - b.order);
    const orderedIds = sorted.map((c) => c.id);
    const visibleById = new Map(sorted.map((c) => [c.id, c.visible]));
    const filterKeyById = new Map(sorted.map((c) => [c.id, c.filterKey]));
    const orderedSet = new Set(orderedIds);

    rows.forEach((row) => {
        const cells = Array.from(row.children) as HTMLElement[];
        if (cells.length === 0) return;

        cells.forEach((cell, idx) => {
            if (cell.dataset.colPrefId === undefined) {
                cell.dataset.colPrefId = String(idx);
            }
            const cellId = Number(cell.dataset.colPrefId ?? idx);
            if (Number.isFinite(cellId) && !cell.dataset.colFilterKey && filterKeyById.has(cellId)) {
                cell.dataset.colFilterKey = filterKeyById.get(cellId) || `column_${cellId}`;
            }
        });

        const cellMap = new Map<number, HTMLElement>(
            cells
                .map((cell) => [Number(cell.dataset.colPrefId), cell] as const)
                .filter(([id]) => Number.isFinite(id))
        );

        const orderedCells = orderedIds
            .map((id) => cellMap.get(id))
            .filter((cell): cell is HTMLElement => Boolean(cell));
        const extraCells = cells.filter((cell) => {
            const id = Number(cell.dataset.colPrefId);
            return !orderedSet.has(id);
        });

        [...orderedCells, ...extraCells].forEach((cell) => row.appendChild(cell));

        Array.from(row.children).forEach((cell) => {
            const id = Number((cell as HTMLElement).dataset.colPrefId);
            if (Number.isFinite(id) && visibleById.has(id)) {
                (cell as HTMLElement).style.display = visibleById.get(id) ? '' : 'none';
            } else {
                (cell as HTMLElement).style.display = '';
            }
        });
    });
}

function ensureColumnFilterRow(table: HTMLTableElement) {
    const thead = table.querySelector('thead');
    const headerRow = thead?.querySelector('tr');
    if (!thead || !headerRow) return;

    let filterRow = thead.querySelector('tr[data-col-filter-row="true"]') as HTMLTableRowElement | null;
    if (!filterRow) {
        filterRow = document.createElement('tr');
        filterRow.dataset.colFilterRow = 'true';
        filterRow.className = 'bg-gray-50';
        thead.appendChild(filterRow);
    }

    const headerCells = Array.from(headerRow.children) as HTMLElement[];
    const needsRebuild = filterRow.children.length !== headerCells.length;
    if (needsRebuild) {
        filterRow.innerHTML = '';
        headerCells.forEach((headerCell, index) => {
            const id = Number(headerCell.dataset.colPrefId ?? index);
            const filterKey = headerCell.dataset.colFilterKey || `column_${id}`;
            const cell = document.createElement('th');
            cell.className = 'px-2 py-2';
            cell.dataset.colPrefId = String(id);
            cell.dataset.colFilterKey = filterKey;
            const input = document.createElement('input');
            input.type = 'search';
            input.value = pendingColumnFilters.value[filterKey] ?? columnFilters.value[filterKey] ?? '';
            input.placeholder = 'Type and press Enter...';
            input.className = 'w-full rounded border border-gray-300 px-2 py-1 text-xs';
            input.addEventListener('input', (event) => {
                const target = event.target as HTMLInputElement;
                pendingColumnFilters.value[filterKey] = target.value;
            });
            input.addEventListener('keydown', (event) => {
                if (event.key !== 'Enter') return;
                event.preventDefault();
                const target = event.target as HTMLInputElement;
                applySingleColumnFilter(filterKey, target.value);
            });
            input.addEventListener('search', (event) => {
                const target = event.target as HTMLInputElement;
                // Clicking the native "x" clear button should immediately refresh.
                if (!target.value.trim()) {
                    applySingleColumnFilter(filterKey, '');
                }
            });
            cell.appendChild(input);
            filterRow!.appendChild(cell);
        });
    } else {
        const filterCells = Array.from(filterRow.children) as HTMLElement[];
        filterCells.forEach((cell) => {
            const filterKey = cell.dataset.colFilterKey || '';
            const input = cell.querySelector('input') as HTMLInputElement | null;
            if (input && filterKey) {
                input.value = pendingColumnFilters.value[filterKey] ?? columnFilters.value[filterKey] ?? '';
            }
        });
    }
}

function applySingleColumnFilter(filterKey: string, rawValue: string) {
    const trimmed = (rawValue || '').trim();
    const nextColumnFilters = { ...columnFilters.value };
    const nextPendingFilters = { ...pendingColumnFilters.value };

    if (trimmed) {
        nextColumnFilters[filterKey] = trimmed;
        nextPendingFilters[filterKey] = trimmed;
    } else {
        delete nextColumnFilters[filterKey];
        delete nextPendingFilters[filterKey];
    }

    columnFilters.value = nextColumnFilters;
    pendingColumnFilters.value = nextPendingFilters;
    queueFilterRequest();
}

function clearInlineRowVisibility(table: HTMLTableElement) {
    const rows = Array.from(table.querySelectorAll('tbody tr')) as HTMLElement[];
    rows.forEach((row) => {
        row.style.display = '';
    });
}

function queueFilterRequest() {
    const currentUrl = pageUrl.value || '';
    const [pathPart, queryPart = ''] = currentUrl.split('?');
    const path = pathPart || window.location.pathname;
    const params = new URLSearchParams(queryPart);

    Array.from(params.keys()).forEach((key) => {
        if (key.startsWith('colf_') || key === 'page') {
            params.delete(key);
        }
    });

    Object.entries(columnFilters.value).forEach(([filterKey, value]) => {
        const trimmed = (value || '').trim();
        if (trimmed) {
            params.set(`colf_${filterKey}`, trimmed);
        }
    });

    const payload: Record<string, string> = {};
    params.forEach((value, key) => {
        payload[key] = value;
    });

    router.get(path, payload, {
        preserveState: false,
        preserveScroll: true,
        replace: true,
    });
}

function applyCurrentPreferences() {
    if (!isIndexView.value || columns.value.length === 0) return;
    const table = getMainTable();
    if (!table) return;
    applyColumnsToTable(table, columns.value);
    ensureColumnFilterRow(table);
    applyColumnsToTable(table, columns.value);
    // Server-side filtering controls row data; clear any old inline hides.
    clearInlineRowVisibility(table);
}

function mergeWithStored(base: ColumnPref[], stored: ColumnPref[]): ColumnPref[] {
    if (!stored.length) return base;

    const storedById = new Map(stored.map((c) => [c.id, c]));
    return base.map((col) => {
        const pref = storedById.get(col.id);
        if (!pref) return col;
        return {
            ...col,
            visible: pref.visible,
            order: pref.order,
            label: col.label,
        };
    }).sort((a, b) => a.order - b.order).map((col, index) => ({ ...col, order: index }));
}

async function loadPreferences() {
    if (!isIndexView.value) {
        hasTable.value = false;
        columns.value = [];
        return;
    }

    const table = getMainTable();
    if (!table) {
        hasTable.value = false;
        columns.value = [];
        return;
    }

    const baseColumns = extractColumnsFromTable(table);
    if (!baseColumns.length) {
        hasTable.value = false;
        columns.value = [];
        return;
    }

    hasTable.value = true;
    columnFilters.value = parseColumnFiltersFromUrl(pageUrl.value);
    pendingColumnFilters.value = { ...columnFilters.value };

    try {
        const params = new URLSearchParams({ page_key: pageKey.value });
        const res = await fetch(`/list-view-preferences?${params.toString()}`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        const payload = res.ok ? await res.json() : { columns: [] };
        const stored = Array.isArray(payload.columns) ? payload.columns as ColumnPref[] : [];
        columns.value = mergeWithStored(baseColumns, stored);
    } catch {
        columns.value = baseColumns;
    }

    await nextTick();
    applyCurrentPreferences();
}

function scheduleLoadPreferences(retries = 5) {
    if (loadTimer.value) clearTimeout(loadTimer.value);
    loadTimer.value = setTimeout(async () => {
        await loadPreferences();
        if (!hasTable.value && retries > 0) {
            scheduleLoadPreferences(retries - 1);
        }
    }, 80);
}

async function savePreferences() {
    if (!isIndexView.value || !hasTable.value) return;
    isSaving.value = true;
    try {
        await fetch('/list-view-preferences', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            body: JSON.stringify({
                page_key: pageKey.value,
                columns: columns.value.map((col, index) => ({
                    ...col,
                    order: index,
                })),
            }),
        });
    } finally {
        isSaving.value = false;
    }
}

function queueSave() {
    if (saveTimer.value) clearTimeout(saveTimer.value);
    saveTimer.value = setTimeout(() => {
        savePreferences();
    }, 300);
}

function updateColumns(next: ColumnPref[]) {
    columns.value = next.map((col, index) => ({ ...col, order: index }));
    applyCurrentPreferences();
    queueSave();
}

function toggleVisibility(id: number) {
    updateColumns(columns.value.map((col) =>
        col.id === id ? { ...col, visible: !col.visible } : col
    ));
}

function resetDefaults() {
    columnFilters.value = {};
    pendingColumnFilters.value = {};
    queueFilterRequest();
    updateColumns(
        [...columns.value]
            .sort((a, b) => a.id - b.id)
            .map((col, index) => ({ ...col, visible: true, order: index }))
    );
}

function onDragStart(index: number) {
    dragIndex.value = index;
}

function onDragOver(event: DragEvent) {
    event.preventDefault();
}

function onDrop(targetIndex: number) {
    if (dragIndex.value === null || dragIndex.value === targetIndex) return;
    const reordered = [...columns.value];
    const [moved] = reordered.splice(dragIndex.value, 1);
    reordered.splice(targetIndex, 0, moved);
    dragIndex.value = null;
    updateColumns(reordered);
}

watch(
    () => `${pageKey.value}|${pageUrl.value}`,
    () => {
        isOpen.value = false;
        scheduleLoadPreferences();
    },
    { immediate: true }
);

onUnmounted(() => {
    if (saveTimer.value) clearTimeout(saveTimer.value);
    if (loadTimer.value) clearTimeout(loadTimer.value);
});
</script>

<template>
    <div v-if="isIndexView && hasTable" class="relative">
        <button
            type="button"
            class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"
            @click="isOpen = true"
        >
            <Settings2 class="h-4 w-4" />
            Edit Columns
        </button>

        <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-md rounded-xl bg-white shadow-xl">
                <div class="flex items-center justify-between border-b px-4 py-3">
                    <h3 class="text-base font-semibold text-gray-900">Edit Columns</h3>
                    <button type="button" class="text-gray-400 hover:text-gray-600" @click="isOpen = false">
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <div class="max-h-[60vh] overflow-auto p-3">
                    <p class="mb-3 text-xs text-gray-500">Drag to reorder. Uncheck to hide columns.</p>
                    <div
                        v-for="(column, index) in columns"
                        :key="column.id"
                        draggable="true"
                        class="mb-2 flex items-center gap-3 rounded-lg border border-gray-200 bg-white px-3 py-2"
                        @dragstart="onDragStart(index)"
                        @dragover="onDragOver"
                        @drop="onDrop(index)"
                    >
                        <GripVertical class="h-4 w-4 text-gray-400" />
                        <input
                            :id="`col-${column.id}`"
                            type="checkbox"
                            class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                            :checked="column.visible"
                            @change="toggleVisibility(column.id)"
                        />
                        <label :for="`col-${column.id}`" class="flex-1 truncate text-sm text-gray-700">
                            {{ column.label || `Column ${column.id + 1}` }}
                        </label>
                    </div>
                </div>

                <div class="flex items-center justify-between border-t px-4 py-3">
                    <button
                        type="button"
                        class="rounded-md border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50"
                        @click="resetDefaults"
                    >
                        Reset Defaults
                    </button>
                    <div class="flex items-center gap-3">
                        <span v-if="isSaving" class="text-xs text-gray-500">Saving...</span>
                        <button
                            type="button"
                            class="rounded-md bg-blue-600 px-3 py-1.5 text-sm text-white hover:bg-blue-700"
                            @click="isOpen = false"
                        >
                            Done
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
