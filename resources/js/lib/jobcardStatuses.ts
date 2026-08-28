export const JOBCARD_STATUS_KEYS = [
    'new',
    'needs_scheduling',
    'scheduled',
    'dispatched',
    'purchase_order_sent_on_dispatch',
    'order_received',
    'stock_checked',
    'delivery_scheduled',
    'delivered',
    'accepted',
    'en_route',
    'on_site',
    'paused',
    'waiting_for_parts',
    'needs_follow_up',
    'emergency',
    'completed',
    'cancelled',
] as const;

export type JobcardStatusKey = (typeof JOBCARD_STATUS_KEYS)[number];

export const JOBCARD_STATUS_DEFAULT_LABELS: Record<JobcardStatusKey, string> = {
    new: 'New',
    needs_scheduling: 'Needs scheduling',
    scheduled: 'Scheduled',
    dispatched: 'Dispatched',
    purchase_order_sent_on_dispatch: 'Purchase order sent on dispatch',
    order_received: 'Order received',
    stock_checked: 'Stock checked',
    delivery_scheduled: 'Delivery scheduled',
    delivered: 'Delivered',
    accepted: 'Accepted',
    en_route: 'En route',
    on_site: 'On site',
    paused: 'Paused',
    waiting_for_parts: 'Waiting for parts',
    needs_follow_up: 'Needs follow-up',
    emergency: 'Emergency',
    completed: 'Completed',
    cancelled: 'Cancelled',
};

export const JOBCARD_STATUS_BADGE_CLASSES: Record<JobcardStatusKey, string> = {
    new: 'bg-gray-100 text-gray-800',
    needs_scheduling: 'bg-amber-100 text-amber-800',
    scheduled: 'bg-blue-100 text-blue-800',
    dispatched: 'bg-indigo-100 text-indigo-800',
    purchase_order_sent_on_dispatch: 'bg-indigo-100 text-indigo-800',
    order_received: 'bg-sky-100 text-sky-800',
    stock_checked: 'bg-cyan-100 text-cyan-800',
    delivery_scheduled: 'bg-blue-100 text-blue-800',
    delivered: 'bg-emerald-100 text-emerald-800',
    accepted: 'bg-blue-100 text-blue-800',
    en_route: 'bg-indigo-100 text-indigo-800',
    on_site: 'bg-purple-100 text-purple-800',
    paused: 'bg-orange-100 text-orange-800',
    waiting_for_parts: 'bg-yellow-100 text-yellow-800',
    needs_follow_up: 'bg-fuchsia-100 text-fuchsia-800',
    emergency: 'bg-red-100 text-red-800',
    completed: 'bg-green-100 text-green-800',
    cancelled: 'bg-red-100 text-red-800',
};

export const JOBCARD_STATUS_BAR_COLORS: Record<JobcardStatusKey, string> = {
    new: '#64748b',
    needs_scheduling: '#f59e0b',
    scheduled: '#3b82f6',
    dispatched: '#4f46e5',
    purchase_order_sent_on_dispatch: '#6366f1',
    order_received: '#0ea5e9',
    stock_checked: '#06b6d4',
    delivery_scheduled: '#2563eb',
    delivered: '#059669',
    accepted: '#06b6d4',
    en_route: '#0ea5e9',
    on_site: '#8b5cf6',
    paused: '#f97316',
    waiting_for_parts: '#eab308',
    needs_follow_up: '#d946ef',
    emergency: '#dc2626',
    completed: '#10b981',
    cancelled: '#ef4444',
};

export function isJobcardStatusKey(value: string): value is JobcardStatusKey {
    return (JOBCARD_STATUS_KEYS as readonly string[]).includes(value);
}

export function jobcardStatusBadgeClass(status: string): string {
    if (isJobcardStatusKey(status)) {
        return JOBCARD_STATUS_BADGE_CLASSES[status];
    }

    return 'bg-gray-100 text-gray-800';
}

export function jobcardStatusBarColor(status: string): string {
    if (isJobcardStatusKey(status)) {
        return JOBCARD_STATUS_BAR_COLORS[status];
    }

    return '#64748b';
}
