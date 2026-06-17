import { toast } from 'vue-sonner';
import type { DispatchBoardPayload } from '@/types/dispatch-board';

export function csrfToken(): string {
    return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';
}

export interface DispatchUserLocation {
    user_id: number;
    name: string;
    lat: number;
    lng: number;
    recorded_at?: string | null;
    recorded_at_local_display?: string | null;
    recorded_at_ago?: string | null;
    last_updated_label?: string | null;
    is_test?: boolean;
}

export async function fetchDispatchUserLocations(): Promise<{
    locations: DispatchUserLocation[];
    timezone: string | null;
} | null> {
    const response = await fetch('/dispatch/user-locations', {
        credentials: 'same-origin',
        cache: 'no-store',
        headers: { Accept: 'application/json' },
    });
    if (!response.ok) {
        return null;
    }
    const data = (await response.json()) as {
        user_locations?: DispatchUserLocation[];
        timezone?: string;
    };
    return {
        locations: data.user_locations ?? [],
        timezone: data.timezone ?? null,
    };
}

export async function fetchDispatchBoard(params: URLSearchParams): Promise<DispatchBoardPayload | null> {
    const response = await fetch(`/dispatch/board-data?${params.toString()}`, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
    });
    if (!response.ok) {
        toast.error('Failed to load dispatch board.');
        return null;
    }
    return (await response.json()) as DispatchBoardPayload;
}

export async function patchJobcardAssign(
    jobcardId: number,
    body: Record<string, unknown>,
): Promise<{ ok: boolean; data?: unknown; message?: string }> {
    const response = await fetch(`/dispatch/jobcards/${jobcardId}/assign`, {
        method: 'PATCH',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify(body),
    });
    const data = await response.json().catch(() => null);
    if (!response.ok) {
        const message = (data as { message?: string })?.message ?? 'Failed to update assignment.';
        toast.error(message);
        return { ok: false, message };
    }
    toast.success('Jobcard updated.');
    return { ok: true, data };
}

export async function patchJobcardSchedule(
    jobcardId: number,
    scheduled_start_at: string,
    scheduled_end_at: string | null,
    estimated_duration_minutes?: number | null,
): Promise<boolean> {
    const body: Record<string, unknown> = {
        scheduled_start_at,
        scheduled_end_at,
    };
    if (estimated_duration_minutes != null) {
        body.estimated_duration_minutes = estimated_duration_minutes;
    }
    const response = await fetch(`/dispatch/cards/jobcard/${jobcardId}/schedule`, {
        method: 'PATCH',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify(body),
    });
    if (!response.ok) {
        toast.error('Failed to reschedule jobcard.');
        return false;
    }
    toast.success('Schedule updated.');
    return true;
}

export async function patchJobcardStatus(jobcardId: number, status: string): Promise<boolean> {
    const response = await fetch(`/dispatch/cards/jobcard/${jobcardId}/status`, {
        method: 'PATCH',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify({ status }),
    });
    if (!response.ok) {
        toast.error('Failed to update status.');
        return false;
    }
    toast.success('Status updated.');
    return true;
}
