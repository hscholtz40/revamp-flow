export type JobcardBoardStatus =
    | 'new'
    | 'needs_scheduling'
    | 'scheduled'
    | 'dispatched'
    | 'purchase_order_sent_on_dispatch'
    | 'order_received'
    | 'stock_checked'
    | 'delivery_scheduled'
    | 'delivered'
    | 'accepted'
    | 'en_route'
    | 'on_site'
    | 'paused'
    | 'waiting_for_parts'
    | 'needs_follow_up'
    | 'emergency'
    | 'completed'
    | 'cancelled';

export type DispatchQueueTab = 'unscheduled' | 'needs_follow_up' | 'waiting_for_parts' | 'emergency' | 'all';

export interface DispatchLookup {
    id: number;
    name: string;
}

export interface DispatchJobcard {
    id: number;
    job_number?: string | null;
    title?: string | null;
    status?: JobcardBoardStatus | string | null;
    priority?: 'low' | 'normal' | 'high' | 'urgent' | string | null;
    assigned_to_user_id?: number | null;
    assigned_to_team_id?: number | null;
    email?: string | null;
    phone?: string | null;
    service_address?: string | null;
    description?: string | null;
    notes?: string | null;
    start_date?: string | null;
    due_date?: string | null;
    estimated_duration_minutes?: number | null;
    scheduled_start_at?: string | null;
    scheduled_end_at?: string | null;
    assigned_user?: DispatchLookup | null;
    assigned_team?: DispatchLookup | null;
    customer?: {
        id: number;
        name: string;
        address?: string | null;
        city?: string | null;
        country?: string | null;
    } | null;
    contact?: {
        id: number;
        name?: string | null;
        phone?: string | null;
        email?: string | null;
    } | null;
}

export interface DispatchConflict {
    reason: string;
    first: Record<string, unknown>;
    second: Record<string, unknown>;
}

export interface DispatchBoardPayload {
    date?: string | null;
    tab?: string | null;
    scheduled_jobcards: DispatchJobcard[];
    unscheduled_jobcards: DispatchJobcard[];
    jobcards: DispatchJobcard[];
    /** Present when loading legacy (non-day) board data for Kanban/calendar. */
    tasks?: unknown[];
    users: DispatchLookup[];
    teams: DispatchLookup[];
    conflicts: DispatchConflict[];
}
