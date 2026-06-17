<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { computed, nextTick, onBeforeUnmount, reactive, ref, watch } from 'vue';
import type { DispatchConflict, DispatchJobcard, DispatchQueueTab } from '@/types/dispatch-board';
import {
    fetchDispatchBoard,
    fetchDispatchUserLocations,
    patchJobcardAssign,
    patchJobcardSchedule,
    patchJobcardStatus,
    type DispatchUserLocation,
} from '@/pages/dispatch/composables/useDispatchApi';
import DispatchFilters from '@/pages/dispatch/components/DispatchFilters.vue';
import UnscheduledJobcardList from '@/pages/dispatch/components/UnscheduledJobcardList.vue';
import TechnicianTimelineBoard from '@/pages/dispatch/components/TechnicianTimelineBoard.vue';
import DispatchJobcardDrawer from '@/pages/dispatch/components/DispatchJobcardDrawer.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import {
    formatUtcInstantInTimezone,
    formatUtcRelativeAgo,
    type DateTimeFormatProps,
} from '@/composables/useDateTimeFormat';
import { formatDispatchScheduleInstant, parseScheduleInstant } from '@/lib/dispatchScheduleFormat';
import { loadGoogleMapsJavaScriptApi } from '@/lib/googleMapsLoader';

interface LookupItem {
    id: number;
    name: string;
}

interface ScheduledItem {
    id: number;
    title?: string | null;
    job_number?: string | null;
    status?: string | null;
    priority?: string | null;
    assigned_to_user_id?: number | null;
    assigned_to_team_id?: number | null;
    estimated_duration_minutes?: number | null;
    notes?: string | null;
    start_date?: string | null;
    due_date?: string | null;
    email?: string | null;
    phone?: string | null;
    contact?: { id: number; name?: string | null; phone?: string | null; email?: string | null } | null;
    assigned_user?: { id: number; name: string } | null;
    assigned_team?: { id: number; name: string } | null;
    customer?: { id: number; name: string; address?: string | null; city?: string | null; country?: string | null } | null;
    service_address?: string | null;
    description?: string | null;
    scheduled_start_at?: string | null;
    scheduled_end_at?: string | null;
}

type BoardStatus =
    | 'new'
    | 'needs_scheduling'
    | 'scheduled'
    | 'dispatched'
    | 'accepted'
    | 'en_route'
    | 'on_site'
    | 'paused'
    | 'waiting_for_parts'
    | 'needs_follow_up'
    | 'emergency'
    | 'completed'
    | 'cancelled';
type TaskStatus = 'new' | 'scheduled' | 'accepted' | 'completed' | 'cancelled';
type BoardEntityType = 'jobcard' | 'task';

/** Task `status` values allowed by `/dispatch/cards/task/{id}/status` — Kanban lanes outside this set are jobcard-only. */
const TASK_KANBAN_STATUSES = new Set<BoardStatus>(['new', 'scheduled', 'accepted', 'completed', 'cancelled']);

const isTaskAllowedLane = (lane: BoardStatus) => TASK_KANBAN_STATUSES.has(lane);

interface BoardCard extends ScheduledItem {
    entityType: BoardEntityType;
}

interface UserPinMeta {
    location: DispatchUserLocation;
    marker: any;
    nearestAddress: string;
}

const props = defineProps<{
    jobcards: ScheduledItem[];
    tasks: ScheduledItem[];
    routes: Array<{ id: number; route_date: string; provider?: string | null }>;
    users: LookupItem[];
    teams: LookupItem[];
    google_maps_api_key?: string;
    /** Map ID for vector map styling; drawer pins use legacy `google.maps.Marker` so they render even when Advanced Markers are unavailable. */
    google_maps_map_id?: string;
    user_locations?: DispatchUserLocation[];
    /** IANA timezone for converting UTC `recorded_at` pings (e.g. Africa/Johannesburg). */
    company_timezone?: string;
}>();

const page = usePage();
const companyTimezone = ref(props.company_timezone ?? 'UTC');

const locationDateTimeFormat = computed((): DateTimeFormatProps => {
    const shared = page.props.dateTimeFormat as DateTimeFormatProps | undefined;
    return {
        timezone: companyTimezone.value || shared?.timezone || 'UTC',
        date_format: shared?.date_format || 'dd/mm/yyyy',
        time_format: shared?.time_format || '24h',
    };
});

const formatLocationDateTime = (iso: string) => formatUtcInstantInTimezone(iso, locationDateTimeFormat.value);

const currentUserId = computed(() => String(page.props?.auth?.user?.id ?? 'guest'));
const googleMapsApiKey = computed(() => props.google_maps_api_key ?? '');
const googleMapsMapId = computed(() => props.google_maps_map_id?.trim() || 'DEMO_MAP_ID');
const userLocations = ref<DispatchUserLocation[]>(props.user_locations ?? []);
let userLocationPollTimer: ReturnType<typeof setInterval> | null = null;

const refreshUserLocations = async (): Promise<boolean> => {
    const result = await fetchDispatchUserLocations();
    if (result === null) {
        return false;
    }
    if (result.timezone) {
        companyTimezone.value = result.timezone;
    }
    userLocations.value = result.locations;

    const selected = selectedMapUser.value;
    if (selected) {
        const updated = result.locations.find((loc) => loc.user_id === selected.location.user_id);
        if (updated) {
            selectedMapUser.value = {
                ...selected,
                location: updated,
            };
        }
    }

    if (showDispatchJobMapModal.value && boardDrawerMap.value) {
        const maps = (window as Window & { google?: { maps?: any } }).google?.maps;
        if (maps) {
            await renderBoardDrawerMarkers(maps);
        }
    }

    return true;
};
const filters = ref({
    assigned_to_user_id: '',
    assigned_to_team_id: '',
});
const viewMode = ref<'board' | 'kanban'>('board');
const loading = ref(false);
const feedback = ref<string>('');
const board = ref({
    jobcards: props.jobcards ?? [],
    tasks: props.tasks ?? [],
    users: props.users ?? [],
    teams: props.teams ?? [],
    conflicts: [] as Array<{ reason: string; first: ScheduledItem; second: ScheduledItem }>,
    scheduled_jobcards: [] as DispatchJobcard[],
    unscheduled_jobcards: [] as DispatchJobcard[],
});
const boardDate = ref(new Date().toISOString().slice(0, 10));
const boardSearch = ref('');
const boardTab = ref<DispatchQueueTab>('unscheduled');
const boardPriority = ref('');
const showTimelineAllHours = ref(false);
const selectedDispatchJobcard = ref<DispatchJobcard | null>(null);
const selectedDrawerJobAddress = computed(() => {
    const j = selectedDispatchJobcard.value;
    if (!j) return '';
    if ((j.service_address ?? '').trim()) return j.service_address!.trim();
    const c = j.customer;
    if (!c) return '';
    return [c.address, c.city, c.country]
        .map((x) => (x ?? '').trim())
        .filter((x) => x.length > 0)
        .join(', ');
});
const boardStatuses: Array<{ value: BoardStatus; label: string }> = [
    { value: 'new', label: 'New' },
    { value: 'needs_scheduling', label: 'Needs scheduling' },
    { value: 'scheduled', label: 'Scheduled' },
    { value: 'dispatched', label: 'Dispatched' },
    { value: 'accepted', label: 'Accepted' },
    { value: 'en_route', label: 'En route' },
    { value: 'on_site', label: 'On site' },
    { value: 'paused', label: 'Paused' },
    { value: 'waiting_for_parts', label: 'Waiting for parts' },
    { value: 'needs_follow_up', label: 'Needs follow-up' },
    { value: 'emergency', label: 'Emergency' },
    { value: 'completed', label: 'Completed' },
    { value: 'cancelled', label: 'Cancelled' },
];
const laneVisibleCount = reactive<Record<BoardStatus, number>>({
    new: 20,
    needs_scheduling: 20,
    scheduled: 20,
    dispatched: 20,
    accepted: 20,
    en_route: 20,
    on_site: 20,
    paused: 20,
    waiting_for_parts: 20,
    needs_follow_up: 20,
    emergency: 20,
    completed: 20,
    cancelled: 20,
});
const draggedCard = ref<BoardCard | null>(null);
const kanbanScrollRef = ref<HTMLElement | null>(null);
const KANBAN_AUTOSCROLL_EDGE_PX = 64;

const onKanbanAutoScrollDragOver = (e: DragEvent) => {
    if (!draggedCard.value || !kanbanScrollRef.value) {
        return;
    }
    const el = kanbanScrollRef.value;
    const rect = el.getBoundingClientRect();
    const maxScroll = el.scrollWidth - el.clientWidth;
    if (maxScroll <= 0) {
        return;
    }
    const x = e.clientX;
    const edge = KANBAN_AUTOSCROLL_EDGE_PX;
    if (x < rect.left + edge) {
        const depth = Math.min(1, (rect.left + edge - x) / edge);
        const step = Math.max(10, Math.round(8 + depth * 40));
        el.scrollLeft = Math.max(0, el.scrollLeft - step);
    } else if (x > rect.right - edge) {
        const depth = Math.min(1, (x - (rect.right - edge)) / edge);
        const step = Math.max(10, Math.round(8 + depth * 40));
        el.scrollLeft = Math.min(maxScroll, el.scrollLeft + step);
    }
};
const laneHoverStatus = ref<BoardStatus | null>(null);

const normalizedStatus = (card: BoardCard): BoardStatus => {
    if (card.entityType === 'jobcard') {
        const value = card.status ?? 'new';
        if (
            value === 'new' ||
            value === 'needs_scheduling' ||
            value === 'scheduled' ||
            value === 'dispatched' ||
            value === 'accepted' ||
            value === 'en_route' ||
            value === 'on_site' ||
            value === 'paused' ||
            value === 'waiting_for_parts' ||
            value === 'needs_follow_up' ||
            value === 'emergency' ||
            value === 'completed' ||
            value === 'cancelled'
        ) {
            return value;
        }
        return 'new';
    }

    if (card.status === 'accepted') return 'accepted';
    if (card.status === 'scheduled') return 'scheduled';
    if (card.status === 'completed') return 'completed';
    if (card.status === 'cancelled') return 'cancelled';
    return 'new';
};
const parseDateValue = (dateString?: string | null): number => {
    if (!dateString) return Number.MAX_SAFE_INTEGER;
    const parsed = Date.parse(dateString);
    return Number.isNaN(parsed) ? Number.MAX_SAFE_INTEGER : parsed;
};
const combinedCards = computed<BoardCard[]>(() => [
    ...(board.value.jobcards ?? []).map((jobcard) => ({ ...jobcard, entityType: 'jobcard' as const })),
    ...(board.value.tasks ?? []).map((task) => ({ ...task, entityType: 'task' as const })),
]);
const sortedLaneCards = (status: BoardStatus) =>
    combinedCards.value
        .filter((card) => normalizedStatus(card) === status)
        .sort((left, right) => {
            const dateDelta = parseDateValue(left.scheduled_start_at) - parseDateValue(right.scheduled_start_at);
            if (dateDelta !== 0) return dateDelta;
            return left.id - right.id;
        });
const visibleLaneCards = (status: BoardStatus) => sortedLaneCards(status).slice(0, laneVisibleCount[status]);

const conflictJobcardIds = computed(() => {
    const set = new Set<number>();
    for (const c of board.value.conflicts as DispatchConflict[]) {
        const f = c.first as { type?: string; id?: number };
        const s = c.second as { type?: string; id?: number };
        if (f?.type === 'jobcard' && f.id) set.add(f.id);
        if (s?.type === 'jobcard' && s.id) set.add(s.id);
    }
    return set;
});

const timelineScheduledJobcards = computed(() => {
    if (viewMode.value === 'board' && board.value.scheduled_jobcards?.length) {
        return board.value.scheduled_jobcards;
    }
    return (board.value.jobcards as DispatchJobcard[]).filter((j) => !!j.scheduled_start_at);
});
const toApiValue = (value: string) => (value ? Number(value) : null);
const csrfToken = () => (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';
const selectedMapUser = ref<UserPinMeta | null>(null);
const showMapUserPinModal = ref(false);
const mapTechnicianFilterUserId = ref('');
const technicianSuggestions = ref<Array<{ user_id: number; name: string; score: number; reason: string }>>([]);
const technicianSuggestionsLoading = ref(false);
type EtaTrafficLevel = 'low' | 'medium' | 'high';
type EtaSummary = { distanceText: string; durationText: string; trafficLevel: EtaTrafficLevel | null };

const selectedUserEta = ref<EtaSummary | null>(null);
const etaLoading = ref(false);
const reverseGeocodeCache = ref<Record<string, string>>({});
const boardDrawerMapContainerRef = ref<HTMLElement | null>(null);
const boardDrawerMap = ref<any | null>(null);
const boardDrawerJobcardMarker = ref<any | null>(null);
const boardDrawerUserMarkers = ref<any[]>([]);
const boardDrawerMapError = ref('');
const boardDrawerInfoWindow = ref<any>(null);
/** Invalidates async InfoWindow content updates when another marker is clicked. */
let boardDrawerIwUpdateSerial = 0;
/** Remount the map container when opening the modal so each session gets a clean DOM node for `google.maps.Map`. */
const dispatchMapContainerKey = ref(0);
const showDispatchJobMapModal = ref(false);
/** Bumps on each drawer map render so out-of-order Geocoder callbacks do not restore a prior jobcard pin. */
let boardDrawerGeocodeGeneration = 0;
const lineupDayDate = computed(() => {
    const parsed = new Date(boardDate.value + 'T12:00:00');
    return Number.isNaN(parsed.getTime()) ? null : parsed;
});
const getLineupCardsForUserId = (userId: number): BoardCard[] => {
    if (!lineupDayDate.value) return [];
    const target = lineupDayDate.value;
    return combinedCards.value
        .filter((card) => card.assigned_user?.id === userId)
        .filter((card) => {
            if (!card.scheduled_start_at) return false;
            const parsed = new Date(card.scheduled_start_at);
            if (Number.isNaN(parsed.getTime())) return false;
            return parsed.toDateString() === target.toDateString();
        })
        .sort((left, right) => parseDateValue(left.scheduled_start_at) - parseDateValue(right.scheduled_start_at));
};
const etaDestinationAddress = computed(() => selectedDrawerJobAddress.value);
const selectedUserDailyLineup = computed(() => {
    const uid = selectedMapUser.value?.location.user_id;
    if (uid == null) return [];
    return getLineupCardsForUserId(uid);
});
const mapTechnicianOptions = computed(() =>
    userLocations.value
        .map((loc) => ({
            userId: String(loc.user_id),
            name: (loc.name ?? '').trim() || `Technician #${loc.user_id}`,
        }))
        .filter((loc, index, all) => all.findIndex((x) => x.userId === loc.userId) === index)
        .sort((left, right) => left.name.localeCompare(right.name)),
);
const filteredMapUserLocations = computed(() => {
    const filterValue = mapTechnicianFilterUserId.value.trim();
    if (!filterValue) return userLocations.value;
    return userLocations.value.filter((loc) => String(loc.user_id) === filterValue);
});
const dispatchPrefsStorageKey = computed(() => `dispatch:preferences:${currentUserId.value}`);
const hasLoadedDispatchPreferences = ref(false);
const loadDispatchPreferences = () => {
    if (typeof window === 'undefined') return;
    const raw = window.localStorage.getItem(dispatchPrefsStorageKey.value);
    if (!raw) return;

    try {
        const parsed = JSON.parse(raw) as Partial<{
            viewMode: 'board' | 'kanban' | 'calendar';
            boardDate?: string;
            boardTab?: DispatchQueueTab;
            boardSearch?: string;
            boardPriority?: string;
            assigned_to_user_id: string;
            assigned_to_team_id: string;
        }>;
        if (parsed.viewMode === 'board' || parsed.viewMode === 'kanban') {
            viewMode.value = parsed.viewMode;
        } else if (parsed.viewMode === 'calendar') {
            viewMode.value = 'board';
        }
        if (typeof parsed.boardDate === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(parsed.boardDate)) boardDate.value = parsed.boardDate;
        if (
            parsed.boardTab === 'unscheduled' ||
            parsed.boardTab === 'needs_follow_up' ||
            parsed.boardTab === 'waiting_for_parts' ||
            parsed.boardTab === 'emergency' ||
            parsed.boardTab === 'all'
        ) {
            boardTab.value = parsed.boardTab;
        }
        if (typeof parsed.boardSearch === 'string') boardSearch.value = parsed.boardSearch;
        if (typeof parsed.boardPriority === 'string') boardPriority.value = parsed.boardPriority;
        if (typeof parsed.assigned_to_user_id === 'string') filters.value.assigned_to_user_id = parsed.assigned_to_user_id;
        if (typeof parsed.assigned_to_team_id === 'string') filters.value.assigned_to_team_id = parsed.assigned_to_team_id;
    } catch {
        // Ignore corrupted local preference payloads.
    }
};
const saveDispatchPreferences = () => {
    if (typeof window === 'undefined') return;
    const payload = {
        viewMode: viewMode.value,
        boardDate: boardDate.value,
        boardTab: boardTab.value,
        boardSearch: boardSearch.value,
        boardPriority: boardPriority.value,
        assigned_to_user_id: filters.value.assigned_to_user_id,
        assigned_to_team_id: filters.value.assigned_to_team_id,
    };
    window.localStorage.setItem(dispatchPrefsStorageKey.value, JSON.stringify(payload));
};
/**
 * Advanced markers in a drawer can render before the map has dimensions/tiles; wait for idle (or a short fallback).
 */
const waitForMapReadyThenResize = async (maps: any, map: any) => {
    if (!map || !maps?.event) {
        await new Promise<void>((r) => requestAnimationFrame(() => r()));
        return;
    }
    await Promise.race([
        new Promise<void>((resolve) => {
            maps.event.addListenerOnce(map, 'idle', () => resolve());
        }),
        new Promise<void>((resolve) => setTimeout(resolve, 500)),
    ]);
    maps.event.trigger(map, 'resize');
};
const reverseGeocode = async (lat: number, lng: number) => {
    const cacheKey = `${lat.toFixed(6)},${lng.toFixed(6)}`;
    if (reverseGeocodeCache.value[cacheKey]) return reverseGeocodeCache.value[cacheKey];
    const maps = (window as Window & { google?: any }).google?.maps;
    if (!maps) return 'Address unavailable';

    const geocoder = new maps.Geocoder();
    const response = await geocoder.geocode({ location: { lat, lng } });
    const nearest = response?.results?.[0]?.formatted_address ?? 'Address unavailable';
    reverseGeocodeCache.value[cacheKey] = nearest;
    return nearest;
};
const formatEtaDistanceMeters = (meters: number) => {
    if (meters < 1000) return `${Math.round(meters)} m`;
    const km = meters / 1000;
    return km < 10 ? `${km.toFixed(1)} km` : `${Math.round(km)} km`;
};
const formatEtaDurationMillis = (ms: number) => {
    const totalMinutes = Math.max(1, Math.round(ms / 60000));
    if (totalMinutes < 60) return `${totalMinutes} min`;
    const hours = Math.floor(totalMinutes / 60);
    const minutes = totalMinutes % 60;
    return minutes ? `${hours} hr ${minutes} min` : `${hours} hr`;
};

/** Routes API speed segments: NORMAL → low, SLOW → medium, TRAFFIC_JAM → high. */
const worstSpeedRank = (intervals: google.maps.routes.SpeedReadingInterval[] | undefined): number => {
    if (!intervals?.length) return 0;
    let r = 0;
    for (const iv of intervals) {
        const s = iv.speed;
        if (s === 'TRAFFIC_JAM') r = Math.max(r, 3);
        else if (s === 'SLOW') r = Math.max(r, 2);
        else if (s === 'NORMAL') r = Math.max(r, 1);
    }
    return r;
};

const rankToTrafficLevel = (rank: number): EtaTrafficLevel | null => {
    if (rank >= 3) return 'high';
    if (rank === 2) return 'medium';
    if (rank === 1) return 'low';
    return null;
};

/** Prefer polyline speed intervals; otherwise approximate from traffic-aware vs static duration. */
const deriveTrafficLevelFromRoute = (route: google.maps.routes.Route): EtaTrafficLevel | null => {
    let rank = worstSpeedRank(route.travelAdvisory?.speedReadingIntervals);
    for (const leg of route.legs ?? []) {
        rank = Math.max(rank, worstSpeedRank(leg.travelAdvisory?.speedReadingIntervals));
    }
    const fromIntervals = rankToTrafficLevel(rank);
    if (fromIntervals) return fromIntervals;

    const d = route.durationMillis;
    const s = route.staticDurationMillis;
    if (d == null || s == null || s <= 0 || !Number.isFinite(d) || !Number.isFinite(s)) {
        return null;
    }
    const delay = d - s;
    if (delay <= 0) return 'low';
    const ratio = delay / s;
    if (ratio < 0.08) return 'low';
    if (ratio < 0.28) return 'medium';
    return 'high';
};

const formatEtaTrafficLabel = (level: EtaTrafficLevel) =>
    level === 'low' ? 'Low' : level === 'medium' ? 'Medium' : 'High';
const etaTrafficBadgeClass = (level: EtaTrafficLevel) =>
    level === 'low'
        ? 'bg-emerald-100 text-emerald-900 ring-emerald-200'
        : level === 'medium'
          ? 'bg-amber-100 text-amber-900 ring-amber-200'
          : 'bg-rose-100 text-rose-900 ring-rose-200';

const buildDrivingEtaFromRoute = (route: google.maps.routes.Route): EtaSummary | null => {
    const localized = route.localizedValues;
    let distanceText = localized?.distance?.trim() ?? '';
    let durationText = localized?.duration?.trim() ?? '';
    if (!distanceText && typeof route.distanceMeters === 'number') {
        distanceText = formatEtaDistanceMeters(route.distanceMeters);
    }
    if (!durationText && typeof route.durationMillis === 'number') {
        durationText = formatEtaDurationMillis(route.durationMillis);
    }
    if (!distanceText || !durationText) return null;
    return {
        distanceText,
        durationText,
        trafficLevel: deriveTrafficLevelFromRoute(route),
    };
};

/** Avoid nested `legs.*` paths — invalid masks make `computeRoutes` throw and ETA shows unavailable. */
const computeRoutesEtaFieldMaskFull = [
    'localizedValues',
    'distanceMeters',
    'durationMillis',
    'staticDurationMillis',
    'travelAdvisory',
] as const;
const computeRoutesEtaFieldMaskNoAdvisory = [
    'localizedValues',
    'distanceMeters',
    'durationMillis',
    'staticDurationMillis',
] as const;
const computeRoutesEtaFieldMaskMinimal = ['localizedValues', 'distanceMeters', 'durationMillis'] as const;

/**
 * Routes API field masks are strict; retry with smaller masks if Google rejects the request.
 */
const computeDrivingRouteWithRetries = async (
    maps: any,
    origin: google.maps.LatLngLiteral,
    destination: string,
): Promise<google.maps.routes.Route | null> => {
    const dest = destination.trim();
    if (!dest) return null;
    const { Route, RoutingPreference } = await maps.importLibrary('routes');
    const base = {
        origin,
        destination: dest,
        travelMode: 'DRIVING' as const,
        routingPreference: RoutingPreference.TRAFFIC_AWARE,
    };
    const run = async (fields: readonly string[]) => {
        const { routes } = await Route.computeRoutes({ ...base, fields: [...fields] });
        return routes?.[0] ?? null;
    };
    try {
        return await run(computeRoutesEtaFieldMaskFull);
    } catch {
        try {
            return await run(computeRoutesEtaFieldMaskNoAdvisory);
        } catch {
            try {
                return await run(computeRoutesEtaFieldMaskMinimal);
            } catch {
                return null;
            }
        }
    }
};

const escapeHtml = (value: string) =>
    value.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

const formatIwDateTime = (value?: string | null) => {
    if (!value) return 'Unscheduled';
    return formatDispatchScheduleInstant(value);
};

const formatLocationLastUpdatedLabel = (loc: DispatchUserLocation) => {
    if (typeof loc.last_updated_label === 'string' && loc.last_updated_label.trim() !== '') {
        return loc.last_updated_label;
    }
    if (loc.is_test) {
        return 'Test location (not from GPS)';
    }
    if (loc.recorded_at_ago && loc.recorded_at_local_display) {
        return `Last updated ${loc.recorded_at_ago} (${loc.recorded_at_local_display})`;
    }
    if (!loc.recorded_at) {
        return 'Last updated: unknown';
    }
    return `Last updated ${formatUtcRelativeAgo(loc.recorded_at)} (${formatLocationDateTime(loc.recorded_at)})`;
};

const boardLineupEstMinutes = (item: BoardCard): number => {
    if (typeof item.estimated_duration_minutes === 'number' && item.estimated_duration_minutes >= 5) {
        return item.estimated_duration_minutes;
    }
    const s = parseScheduleInstant(item.scheduled_start_at);
    const e = parseScheduleInstant(item.scheduled_end_at);
    if (s && e && e.getTime() > s.getTime()) {
        return Math.max(5, Math.round((e.getTime() - s.getTime()) / 60000));
    }
    return 60;
};

const formatBoardLineupRow = (item: BoardCard): string => {
    const when = formatDispatchScheduleInstant(item.scheduled_start_at);
    const ref =
        item.entityType === 'jobcard'
            ? item.job_number || `Jobcard #${item.id}`
            : item.title || `Task #${item.id}`;
    const est = boardLineupEstMinutes(item);
    return `${when} - ${ref} - Est. ${est} Min`;
};

/** Opens Google Maps (app or web) with driving directions between two points. */
const googleMapsDrivingDirectionsUrl = (originLat: number, originLng: number, destination: string) => {
    const params = new URLSearchParams({
        api: '1',
        origin: `${originLat},${originLng}`,
        destination: destination.trim(),
        travelmode: 'driving',
    });
    return `https://www.google.com/maps/dir/?${params.toString()}`;
};

/**
 * Teardrop map-pin as an SVG data URL (large, shadowed, readable over dense basemap POIs).
 * `glyph` is one or two characters shown in the pin head.
 */
const mapPinSvgDataUrl = (opts: { fill: string; stroke: string; glyph: string; glyphFill: string }) => {
    const raw = opts.glyph.replace(/\s/g, '');
    const g = raw.length > 2 ? raw.slice(0, 2) : raw || '?';
    const fontSize = g.length > 1 ? 11 : 15;
    const esc = (s: string) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
    const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 56 80" width="56" height="80">
<defs><filter id="pds" x="-40%" y="-40%" width="180%" height="180%"><feDropShadow dx="0" dy="3" stdDeviation="2.5" flood-color="#000" flood-opacity="0.5"/></filter></defs>
<path filter="url(#pds)" fill="${opts.fill}" stroke="${opts.stroke}" stroke-width="2.5" stroke-linejoin="round"
d="M28 5 C14 5 5 16 5 30 c0 16 11 34 23 50 12-16 23-34 23-50 C51 16 42 5 28 5z"/>
<circle cx="28" cy="30" r="13.5" fill="#ffffff"/>
<text x="28" y="35" text-anchor="middle" font-family="system-ui,Segoe UI,Helvetica,sans-serif" font-size="${fontSize}" font-weight="400" fill="${opts.glyphFill}">${esc(g)}</text>
</svg>`;
    return `data:image/svg+xml;charset=UTF-8,${encodeURIComponent(svg)}`;
};

const closeBoardDrawerInfoWindow = () => {
    boardDrawerInfoWindow.value?.close?.();
};

const openBoardDrawerInfoWindow = (maps: any, marker: any, content: HTMLElement | string) => {
    if (!boardDrawerMap.value) return;
    if (!boardDrawerInfoWindow.value) {
        boardDrawerInfoWindow.value = new maps.InfoWindow({ maxWidth: 360 });
    } else {
        boardDrawerInfoWindow.value.close();
    }
    boardDrawerInfoWindow.value.setContent(content);
    boardDrawerInfoWindow.value.open({
        map: boardDrawerMap.value,
        anchor: marker,
    });
};

const fetchDrivingEtaSummary = async (maps: any, lat: number, lng: number, destination: string): Promise<EtaSummary | null> => {
    const route = await computeDrivingRouteWithRetries(maps, { lat, lng }, destination);
    if (!route) return null;
    return buildDrivingEtaFromRoute(route);
};

const buildJobcardInfoWindowHtml = (j: DispatchJobcard) => {
    const parts: string[] = [];
    parts.push(`<div class="font-semibold text-slate-900">${escapeHtml(j.job_number || `Jobcard #${j.id}`)}</div>`);
    if (j.title) {
        parts.push(`<div class="text-sm text-slate-800">${escapeHtml(j.title)}</div>`);
    }
    if (j.customer?.name) {
        parts.push(
            `<div class="mt-1 text-xs"><span class="text-slate-500">Customer:</span> ${escapeHtml(j.customer.name)}</div>`,
        );
    }
    const site =
        (j.service_address ?? '').trim() ||
        [j.customer?.address, j.customer?.city, j.customer?.country].map((x) => (x ?? '').trim()).filter(Boolean).join(', ');
    if (site) {
        parts.push(`<div class="mt-1 text-xs"><span class="text-slate-500">Site:</span> ${escapeHtml(site)}</div>`);
    }
    parts.push(
        `<div class="mt-1 text-xs"><span class="text-slate-500">Status:</span> ${escapeHtml(String(j.status ?? '—').replace(/_/g, ' '))}</div>`,
    );
    if (j.scheduled_start_at) {
        const end = j.scheduled_end_at ? ` – ${escapeHtml(formatIwDateTime(j.scheduled_end_at))}` : '';
        parts.push(
            `<div class="mt-1 text-xs"><span class="text-slate-500">Scheduled:</span> ${escapeHtml(formatIwDateTime(j.scheduled_start_at))}${end}</div>`,
        );
    }
    if (j.assigned_user?.name || j.assigned_team?.name) {
        parts.push(
            `<div class="mt-1 text-xs"><span class="text-slate-500">Assigned:</span> ${escapeHtml(j.assigned_user?.name || j.assigned_team?.name || '')}</div>`,
        );
    }
    return `<div class="dispatch-map-infowindow max-w-[320px] text-slate-800">${parts.join('')}</div>`;
};

const updateSelectedUserEta = async () => {
    selectedUserEta.value = null;
    if (!selectedMapUser.value || !etaDestinationAddress.value) return;
    const maps = (window as Window & { google?: any }).google?.maps;
    if (!maps?.importLibrary) return;

    etaLoading.value = true;
    try {
        const route = await computeDrivingRouteWithRetries(maps, {
            lat: selectedMapUser.value.location.lat,
            lng: selectedMapUser.value.location.lng,
        }, etaDestinationAddress.value);
        if (!route) {
            selectedUserEta.value = null;
            return;
        }
        const summary = buildDrivingEtaFromRoute(route);
        selectedUserEta.value = summary;
    } catch {
        selectedUserEta.value = null;
    } finally {
        etaLoading.value = false;
    }
};

const haversineKm = (a: { lat: number; lng: number }, b: { lat: number; lng: number }) => {
    const toRad = (deg: number) => (deg * Math.PI) / 180;
    const dLat = toRad(b.lat - a.lat);
    const dLng = toRad(b.lng - a.lng);
    const lat1 = toRad(a.lat);
    const lat2 = toRad(b.lat);
    const x =
        Math.sin(dLat / 2) ** 2 + Math.cos(lat1) * Math.cos(lat2) * Math.sin(dLng / 2) ** 2;
    return 6371 * 2 * Math.atan2(Math.sqrt(x), Math.sqrt(1 - x));
};

const boundsSpanKm = (maps: any, bounds: any) => {
    const sw = bounds.getSouthWest();
    const ne = bounds.getNorthEast();
    return haversineKm({ lat: sw.lat(), lng: sw.lng() }, { lat: ne.lat(), lng: ne.lng() });
};

/** Avoid world-zoom when simulator GPS and job site are continents apart. */
const frameBoardDrawerMap = (maps: any, bounds: any, hasJobSite: boolean) => {
    if (!boardDrawerMap.value) {
        return;
    }
    const techs = filteredMapUserLocations.value;
    const spanKm = boundsSpanKm(maps, bounds);

    if (spanKm > 400 && techs.length > 0 && hasJobSite) {
        boardDrawerMapError.value = `Technician GPS is far from the job site (~${Math.round(spanKm).toLocaleString()} km). Map centered on technician location.`;
        if (techs.length === 1) {
            boardDrawerMap.value.setCenter({ lat: techs[0].lat, lng: techs[0].lng });
            boardDrawerMap.value.setZoom(12);
        } else {
            const techBounds = new maps.LatLngBounds();
            techs.forEach((loc) => techBounds.extend({ lat: loc.lat, lng: loc.lng }));
            boardDrawerMap.value.fitBounds(techBounds, 60);
        }
        return;
    }

    boardDrawerMapError.value = '';
    if (techs.length === 0 && hasJobSite) {
        boardDrawerMap.value.setCenter(bounds.getCenter());
        boardDrawerMap.value.setZoom(14);
    } else if (techs.length === 1 && !hasJobSite) {
        boardDrawerMap.value.setCenter({ lat: techs[0].lat, lng: techs[0].lng });
        boardDrawerMap.value.setZoom(12);
    } else {
        try {
            boardDrawerMap.value.fitBounds(bounds, 60);
        } catch {
            boardDrawerMap.value.setCenter(bounds.getCenter());
        }
    }
};

const clearBoardDrawerMarkers = () => {
    closeBoardDrawerInfoWindow();
    if (boardDrawerJobcardMarker.value) {
        boardDrawerJobcardMarker.value.setMap?.(null);
        boardDrawerJobcardMarker.value = null;
    }
    boardDrawerUserMarkers.value.forEach((m) => {
        m.setMap?.(null);
    });
    boardDrawerUserMarkers.value = [];
};

const renderBoardDrawerMarkers = async (maps: any) => {
    if (!boardDrawerMap.value) {
        return;
    }
    clearBoardDrawerMarkers();

    /** Legacy markers render without Advanced Marker / Map ID capability; Advanced Markers often stay invisible if `mapId` is wrong or `isAdvancedMarkersAvailable` is false. */
    const { Marker } = (await maps.importLibrary('marker')) as {
        Marker: new (options: Record<string, unknown>) => { setMap: (m: unknown) => void };
    };
    const coreLib = (await maps.importLibrary('core')) as {
        Size: new (w: number, h: number) => { width?: number; height?: number };
        Point: new (x: number, y: number) => { x?: number; y?: number };
    };

    const pinIcon = (dataUrl: string) => ({
        url: dataUrl,
        scaledSize: new coreLib.Size(48, 68),
        anchor: new coreLib.Point(24, 68),
    });

    const generation = ++boardDrawerGeocodeGeneration;
    const bounds = new maps.LatLngBounds();
    let hasPoint = false;

    filteredMapUserLocations.value.forEach((loc) => {
        const position = { lat: loc.lat, lng: loc.lng };
        bounds.extend(position);
        hasPoint = true;
        const labelText = ((loc.name ?? '').trim().charAt(0) || '?').toUpperCase();
        const marker = new Marker({
            map: boardDrawerMap.value,
            position,
            title: loc.name,
            icon: pinIcon(
                mapPinSvgDataUrl({
                    fill: '#334155',
                    stroke: '#ffffff',
                    glyph: labelText,
                    glyphFill: '#0f172a',
                }),
            ),
            zIndex: 1000,
        });
        maps.event.addListener(marker, 'click', () => {
            boardDrawerIwUpdateSerial += 1;
            const serial = boardDrawerIwUpdateSerial;
            const locSnapshot = userLocations.value.find((l) => l.user_id === loc.user_id) ?? { ...loc };
            const jobAddr = selectedDrawerJobAddress.value;

            const lineup = getLineupCardsForUserId(locSnapshot.user_id);
            const lineupItems =
                lineup.length === 0
                    ? '<li class="text-slate-500">No jobs lined up for the board date.</li>'
                    : lineup
                          .slice(0, 12)
                          .map((item) => `<li class="truncate">${escapeHtml(formatBoardLineupRow(item))}</li>`)
                          .join('');

            const el = document.createElement('div');
            el.className = 'dispatch-map-infowindow max-w-[320px] text-slate-800';
            el.innerHTML = `
                <div class="font-semibold text-slate-900">${escapeHtml(locSnapshot.name)}</div>
                <div data-iw-updated class="mt-0.5 text-xs text-slate-500"></div>
                <div class="mt-1 font-mono text-[11px] text-slate-600">${locSnapshot.lat.toFixed(5)}, ${locSnapshot.lng.toFixed(5)}</div>
                <div data-iw-addr class="mt-1 text-xs text-slate-700">Loading address…</div>
                ${jobAddr ? `<div data-iw-eta class="mt-1 text-xs text-slate-700">ETA to job: calculating…</div>` : ''}
                ${
                    jobAddr
                        ? `<div class="mt-2 rounded border border-indigo-200 bg-indigo-50 px-2 py-1.5">
                    <p class="text-[11px] font-medium text-indigo-950">Driving directions</p>
                    <p class="mt-0.5 text-[11px] text-slate-600">From this technician to the open jobcard’s job address.</p>
                    <a data-iw-dir class="mt-1 inline-block text-xs font-semibold text-indigo-800 underline decoration-indigo-400 underline-offset-2 hover:text-indigo-950" href="#" target="_blank" rel="noopener noreferrer">Open turn-by-turn in Google Maps</a>
                </div>`
                        : ''
                }
                <div class="mt-2 border-t border-slate-200 pt-1">
                    <div class="text-xs font-semibold text-slate-700">Board date lineup</div>
                    <ul class="mt-1 max-h-36 list-disc overflow-y-auto pl-4 text-xs">${lineupItems}</ul>
                </div>
                <div class="mt-2">
                    <button type="button" data-iw-details class="rounded border border-slate-300 px-2 py-1 text-xs font-medium text-slate-800 hover:bg-slate-50">Full details…</button>
                </div>
            `;

            const updatedEl = el.querySelector('[data-iw-updated]');
            if (updatedEl) {
                updatedEl.textContent = formatLocationLastUpdatedLabel(locSnapshot);
            }

            openBoardDrawerInfoWindow(maps, marker, el);

            if (jobAddr) {
                const dirA = el.querySelector('[data-iw-dir]') as HTMLAnchorElement | null;
                if (dirA) {
                    dirA.href = googleMapsDrivingDirectionsUrl(locSnapshot.lat, locSnapshot.lng, jobAddr);
                }
            }

            void reverseGeocode(locSnapshot.lat, locSnapshot.lng).then((addr) => {
                if (serial !== boardDrawerIwUpdateSerial) return;
                const addrEl = el.querySelector('[data-iw-addr]');
                if (addrEl) addrEl.textContent = `Near: ${addr}`;
            });

            if (jobAddr) {
                void fetchDrivingEtaSummary(maps, locSnapshot.lat, locSnapshot.lng, jobAddr).then((eta) => {
                    if (serial !== boardDrawerIwUpdateSerial) return;
                    const etaEl = el.querySelector('[data-iw-eta]');
                    if (!etaEl) return;
                    if (!eta) {
                        etaEl.textContent = 'ETA to job: unavailable';
                        return;
                    }
                    const etaText = `ETA to job: ${eta.durationText} (${eta.distanceText})`;
                    if (eta.trafficLevel == null) {
                        etaEl.textContent = etaText;
                        return;
                    }
                    const trafficLabel = formatEtaTrafficLabel(eta.trafficLevel);
                    const trafficClass = etaTrafficBadgeClass(eta.trafficLevel);
                    etaEl.innerHTML = `${escapeHtml(etaText)} · Traffic: <span class="inline-flex items-center rounded-full px-1.5 py-0.5 text-[10px] font-semibold ring-1 ${trafficClass}">${escapeHtml(trafficLabel)}</span>`;
                });
            }

            const detailsBtn = el.querySelector('[data-iw-details]') as HTMLButtonElement | null;
            detailsBtn?.addEventListener('click', () => {
                void reverseGeocode(locSnapshot.lat, locSnapshot.lng).then((nearestAddress) => {
                    closeBoardDrawerInfoWindow();
                    selectedMapUser.value = {
                        location: locSnapshot,
                        marker,
                        nearestAddress,
                    };
                    showMapUserPinModal.value = true;
                });
            });
        });
        boardDrawerUserMarkers.value.push(marker);
    });

    const addr = selectedDrawerJobAddress.value;
    if (!addr) {
        if (hasPoint && boardDrawerMap.value) {
            frameBoardDrawerMap(maps, bounds, false);
        } else {
            boardDrawerMapError.value = mapTechnicianFilterUserId.value
                ? 'No map pins for the selected technician.'
                : 'No technician GPS locations in the last 30 minutes. Add a service address on the jobcard to plot the job site.';
        }
        return;
    }

    const geocoder = new maps.Geocoder();
    geocoder.geocode({ address: addr }, (results: any, status: string) => {
        if (generation !== boardDrawerGeocodeGeneration) {
            return;
        }
        if (!boardDrawerMap.value) {
            return;
        }
        if (status === 'OK' && results?.[0]) {
            const position = results[0].geometry.location;
            bounds.extend(position);
            hasPoint = true;
            const jc = selectedDispatchJobcard.value;
            boardDrawerJobcardMarker.value = new Marker({
                map: boardDrawerMap.value,
                position,
                title: jc?.job_number || 'Jobcard location',
                icon: pinIcon(
                    mapPinSvgDataUrl({
                        fill: '#1d4ed8',
                        stroke: '#ffffff',
                        glyph: 'JC',
                        glyphFill: '#1e3a8a',
                    }),
                ),
                zIndex: 1100,
            });
            if (jc) {
                maps.event.addListener(boardDrawerJobcardMarker.value, 'click', () => {
                    const current = selectedDispatchJobcard.value;
                    if (!current) return;
                    openBoardDrawerInfoWindow(maps, boardDrawerJobcardMarker.value, buildJobcardInfoWindowHtml(current));
                });
            }
        }

        if (!hasPoint) {
            boardDrawerMapError.value =
                'Could not plot this job (address lookup failed and no recent technician GPS locations). Check the service address.';
            return;
        }

        const hasJobSite = status === 'OK' && !!results?.[0];
        if (filteredMapUserLocations.value.length === 0 && hasJobSite) {
            boardDrawerMapError.value = '';
            boardDrawerMap.value.setCenter(results[0].geometry.location);
            boardDrawerMap.value.setZoom(14);
        } else if (filteredMapUserLocations.value.length === 1 && !hasJobSite) {
            boardDrawerMapError.value = '';
            boardDrawerMap.value.setCenter({
                lat: filteredMapUserLocations.value[0].lat,
                lng: filteredMapUserLocations.value[0].lng,
            });
            boardDrawerMap.value.setZoom(12);
        } else {
            frameBoardDrawerMap(maps, bounds, hasJobSite);
        }
        if (boardDrawerMap.value && maps?.event) {
            maps.event.trigger(boardDrawerMap.value, 'resize');
        }
    });
};

const ensureBoardDrawerMap = async () => {
    boardDrawerMapError.value = '';
    if (!showDispatchJobMapModal.value || viewMode.value !== 'board' || !selectedDispatchJobcard.value) {
        return;
    }
    await nextTick();
    await nextTick();
    if (!boardDrawerMapContainerRef.value) {
        return;
    }
    if (!googleMapsApiKey.value) {
        boardDrawerMapError.value = 'Google Maps API key is missing. Configure it under Administration → Other Integrations.';
        return;
    }

    try {
        const maps = await loadGoogleMapsJavaScriptApi(googleMapsApiKey.value);
        if (!maps || !boardDrawerMapContainerRef.value) {
            boardDrawerMapError.value = 'Unable to initialize Google Maps.';
            return;
        }

        if (!boardDrawerMap.value) {
            boardDrawerMap.value = new maps.Map(boardDrawerMapContainerRef.value, {
                center: { lat: -26.2041, lng: 28.0473 },
                zoom: 6,
                mapId: googleMapsMapId.value,
                mapTypeControl: false,
                streetViewControl: false,
                fullscreenControl: true,
            });
        } else {
            boardDrawerMap.value.setOptions({
                mapId: googleMapsMapId.value,
                mapTypeControl: false,
                streetViewControl: false,
            });
        }

        await waitForMapReadyThenResize(maps, boardDrawerMap.value);
        await renderBoardDrawerMarkers(maps);
    } catch (e) {
        boardDrawerMapError.value =
            e instanceof Error ? e.message : 'Google Maps failed to load.';
    }
};

const showCreateTaskModal = ref(false);
const taskForm = reactive({
    title: '',
    description: '',
    status: 'new' as TaskStatus,
    assigned_to_user_id: '',
    assigned_to_team_id: '',
    scheduled_start_at: '',
    scheduled_end_at: '',
});
const taskFormErrors = ref<Record<string, string>>({});
const creatingTask = ref(false);
const parseFlexibleDateTime = (input?: string | null): Date | null => {
    if (!input) return null;
    const normalized = input.includes(' ') ? input.replace(' ', 'T') : input;
    const parsed = new Date(normalized);
    if (Number.isNaN(parsed.getTime())) return null;
    return parsed;
};
const formatDatetimeLocal = (date: Date) => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hour = String(date.getHours()).padStart(2, '0');
    const minute = String(date.getMinutes()).padStart(2, '0');
    return `${year}-${month}-${day}T${hour}:${minute}`;
};
const toDatetimeLocal = (input?: string | null) => {
    const parsed = parseFlexibleDateTime(input);
    if (!parsed) return '';
    return formatDatetimeLocal(parsed);
};
const openCreateTaskModal = (defaults?: { start?: string; end?: string }) => {
    const parsedStart = parseFlexibleDateTime(defaults?.start);
    const parsedEnd = parseFlexibleDateTime(defaults?.end);
    const start = parsedStart ?? null;
    const end = parsedEnd ?? (start ? new Date(start.getTime() + 60 * 60000) : null);

    taskForm.title = '';
    taskForm.description = '';
    taskForm.status = 'new';
    taskForm.assigned_to_user_id = filters.value.assigned_to_user_id || '';
    taskForm.assigned_to_team_id = filters.value.assigned_to_team_id || '';
    taskForm.scheduled_start_at = start ? formatDatetimeLocal(start) : '';
    taskForm.scheduled_end_at = end ? formatDatetimeLocal(end) : '';
    taskFormErrors.value = {};
    showCreateTaskModal.value = true;
};
const closeCreateTaskModal = () => {
    showCreateTaskModal.value = false;
    taskFormErrors.value = {};
};

const mergeJobcardFromAssignResponse = (raw: Record<string, unknown>) => {
    const sel = selectedDispatchJobcard.value;
    if (!sel) {
        return;
    }
    const au = raw.assigned_user as { id: number; name: string } | null | undefined;
    const at = raw.assigned_team as { id: number; name: string } | null | undefined;
    selectedDispatchJobcard.value = {
        ...sel,
        assigned_to_user_id: (raw.assigned_to_user_id as number | null) ?? null,
        assigned_to_team_id: (raw.assigned_to_team_id as number | null) ?? null,
        assigned_user: au ?? null,
        assigned_team: at ?? null,
        status: (raw.status as string) ?? sel.status,
        scheduled_start_at: (raw.scheduled_start_at as string | null) ?? sel.scheduled_start_at,
        scheduled_end_at: (raw.scheduled_end_at as string | null) ?? sel.scheduled_end_at,
        service_address: (raw.service_address as string | null) ?? sel.service_address,
        priority: (raw.priority as string) ?? sel.priority,
        estimated_duration_minutes:
            (raw.estimated_duration_minutes as number | null) ?? sel.estimated_duration_minutes,
    };
};

const syncSelectedDispatchJobcardFromBoard = () => {
    const current = selectedDispatchJobcard.value;
    if (!current?.id) {
        return;
    }
    const id = current.id;
    const scheduled = board.value.scheduled_jobcards ?? [];
    const unscheduled = board.value.unscheduled_jobcards ?? [];
    const found = [...scheduled, ...unscheduled].find((j) => j.id === id);
    if (found) {
        selectedDispatchJobcard.value = found;
    }
};

const loadBoard = async () => {
    loading.value = true;
    feedback.value = '';
    const params = new URLSearchParams();
    if (filters.value.assigned_to_user_id) params.set('assigned_to_user_id', filters.value.assigned_to_user_id);
    if (filters.value.assigned_to_team_id) params.set('assigned_to_team_id', filters.value.assigned_to_team_id);

    if (viewMode.value === 'board') {
        params.set('date', boardDate.value);
        params.set('tab', boardTab.value);
        if (boardSearch.value.trim()) params.set('search', boardSearch.value.trim());
        if (boardPriority.value) params.set('priority', boardPriority.value);
    }

    const data = await fetchDispatchBoard(params);
    loading.value = false;
    if (!data) {
        feedback.value = 'Failed to load dispatch board.';
        return;
    }

    board.value = {
        jobcards: (data.jobcards ?? []) as ScheduledItem[],
        tasks: (data.tasks ?? []) as ScheduledItem[],
        users: data.users ?? [],
        teams: data.teams ?? [],
        conflicts: (data.conflicts ?? []) as unknown as Array<{ reason: string; first: ScheduledItem; second: ScheduledItem }>,
        scheduled_jobcards: data.scheduled_jobcards ?? [],
        unscheduled_jobcards: data.unscheduled_jobcards ?? [],
    };
    laneVisibleCount.new = 20;
    laneVisibleCount.needs_scheduling = 20;
    laneVisibleCount.scheduled = 20;
    laneVisibleCount.dispatched = 20;
    laneVisibleCount.accepted = 20;
    laneVisibleCount.en_route = 20;
    laneVisibleCount.on_site = 20;
    laneVisibleCount.paused = 20;
    laneVisibleCount.waiting_for_parts = 20;
    laneVisibleCount.needs_follow_up = 20;
    laneVisibleCount.emergency = 20;
    laneVisibleCount.completed = 20;
    laneVisibleCount.cancelled = 20;

    syncSelectedDispatchJobcardFromBoard();
};

const minutesBetween = (startIso: string, endIso: string | null): number | null => {
    const a = new Date(startIso).getTime();
    const b = endIso ? new Date(endIso).getTime() : NaN;
    if (Number.isNaN(a) || Number.isNaN(b)) return null;
    return Math.max(5, Math.round((b - a) / 60000));
};

const onBoardDropJobcard = async (payload: {
    jobcardId: number;
    assignedToUserId: number | null;
    start: string;
    end: string | null;
}) => {
    loading.value = true;
    const est = minutesBetween(payload.start, payload.end);
    const r = await patchJobcardAssign(payload.jobcardId, {
        assigned_to_user_id: payload.assignedToUserId,
        assigned_to_team_id: null,
        scheduled_start_at: payload.start,
        scheduled_end_at: payload.end,
        status: 'scheduled',
        ...(est ? { estimated_duration_minutes: est } : {}),
    });
    loading.value = false;
    if (r.ok) await loadBoard();
};

const onBoardMoveJobcard = async (payload: {
    jobcardId: number;
    assignedToUserId: number | null;
    start: string;
    end: string | null;
}) => {
    loading.value = true;
    const est = minutesBetween(payload.start, payload.end);
    const job = timelineScheduledJobcards.value.find((j) => j.id === payload.jobcardId);
    const userChanged = job && (job.assigned_to_user_id ?? null) !== (payload.assignedToUserId ?? null);
    let ok = true;
    if (userChanged) {
        const r = await patchJobcardAssign(payload.jobcardId, {
            assigned_to_user_id: payload.assignedToUserId,
            assigned_to_team_id: null,
            scheduled_start_at: payload.start,
            scheduled_end_at: payload.end,
            ...(est ? { estimated_duration_minutes: est } : {}),
        });
        ok = r.ok;
    } else {
        ok = await patchJobcardSchedule(payload.jobcardId, payload.start, payload.end, est ?? undefined);
    }
    loading.value = false;
    if (ok) await loadBoard();
};

const onDrawerAssign = async (p: { assigned_to_user_id: number | null; assigned_to_team_id: number | null }) => {
    if (!selectedDispatchJobcard.value) return;
    loading.value = true;
    const r = await patchJobcardAssign(selectedDispatchJobcard.value.id, {
        assigned_to_user_id: p.assigned_to_user_id,
        assigned_to_team_id: p.assigned_to_team_id,
    });
    loading.value = false;
    if (r.ok && r.data && typeof r.data === 'object' && !Array.isArray(r.data)) {
        mergeJobcardFromAssignResponse(r.data as Record<string, unknown>);
    }
    if (r.ok) await loadBoard();
};

const onDrawerStatus = async (status: string) => {
    if (!selectedDispatchJobcard.value) return;
    loading.value = true;
    const ok = await patchJobcardStatus(selectedDispatchJobcard.value.id, status);
    loading.value = false;
    if (ok) await loadBoard();
};

const onDrawerUnschedule = async () => {
    if (!selectedDispatchJobcard.value) return;
    loading.value = true;
    const r = await patchJobcardAssign(selectedDispatchJobcard.value.id, {
        scheduled_start_at: null,
        scheduled_end_at: null,
        assigned_to_user_id: selectedDispatchJobcard.value.assigned_to_user_id,
        assigned_to_team_id: selectedDispatchJobcard.value.assigned_to_team_id,
    });
    loading.value = false;
    if (r.ok) await loadBoard();
};

const formatScheduleLocalFromDate = (d: Date): string => {
    const pad = (n: number) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:00`;
};

const onDrawerEstimatedDuration = async (minutes: number) => {
    if (!selectedDispatchJobcard.value) return;
    const job = selectedDispatchJobcard.value;
    const m = Math.max(5, Math.min(1440, Math.round(minutes)));
    loading.value = true;
    let ok = false;
    if (job.scheduled_start_at) {
        const start = new Date(job.scheduled_start_at);
        if (Number.isNaN(start.getTime())) {
            loading.value = false;
            return;
        }
        const end = new Date(start.getTime() + m * 60000);
        ok = await patchJobcardSchedule(job.id, formatScheduleLocalFromDate(start), formatScheduleLocalFromDate(end), m);
    } else {
        const r = await patchJobcardAssign(job.id, { estimated_duration_minutes: m });
        ok = r.ok;
    }
    loading.value = false;
    if (ok) await loadBoard();
};
const onSuggestTechnicians = async () => {
    if (!selectedDispatchJobcard.value) return;
    technicianSuggestionsLoading.value = true;
    const response = await fetch('/ai/suggest-technician', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify({
            jobcard_id: selectedDispatchJobcard.value.id,
            title: selectedDispatchJobcard.value.title || '',
            description: selectedDispatchJobcard.value.description || '',
            service_address: selectedDispatchJobcard.value.service_address || '',
        }),
    });
    technicianSuggestionsLoading.value = false;
    if (!response.ok) {
        feedback.value = 'Unable to load AI technician suggestions.';
        return;
    }
    const data = (await response.json()) as { suggestions?: Array<{ user_id: number; name: string; score: number; reason: string }> };
    technicianSuggestions.value = data.suggestions ?? [];
};

const openFullJobcard = (job: DispatchJobcard) => {
    router.visit(`/jobcards/${job.id}`);
};

const openSelectedDispatchFull = () => {
    if (selectedDispatchJobcard.value) {
        openFullJobcard(selectedDispatchJobcard.value);
    }
};

const updateCardStatus = async (card: BoardCard, status: BoardStatus) => {
    loading.value = true;
    feedback.value = '';
    const response = await fetch(`/dispatch/cards/${card.entityType}/${card.id}/status`, {
        method: 'PATCH',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify({ status }),
    });
    loading.value = false;
    feedback.value = response.ok
        ? `${card.entityType === 'jobcard' ? 'Jobcard' : 'Task'} #${card.id} moved to ${status.replace('_', ' ')}.`
        : `Failed to move ${card.entityType} #${card.id}.`;
    if (response.ok) await loadBoard();
};

const onDragStart = (card: BoardCard) => {
    draggedCard.value = card;
    document.addEventListener('dragover', onKanbanAutoScrollDragOver);
};
const onDragEnd = () => {
    draggedCard.value = null;
    laneHoverStatus.value = null;
    document.removeEventListener('dragover', onKanbanAutoScrollDragOver);
};
const onLaneDragOver = (status: BoardStatus, event: DragEvent) => {
    const card = draggedCard.value;
    if (card?.entityType === 'task' && !isTaskAllowedLane(status)) {
        laneHoverStatus.value = null;
        return;
    }
    event.preventDefault();
    if (event.dataTransfer) {
        event.dataTransfer.dropEffect = 'move';
    }
    laneHoverStatus.value = status;
};
const onLaneDrop = async (status: BoardStatus, event: DragEvent) => {
    event.preventDefault();
    laneHoverStatus.value = null;
    const card = draggedCard.value;
    if (!card || normalizedStatus(card) === status) return;
    if (card.entityType === 'task' && !isTaskAllowedLane(status)) return;
    await updateCardStatus(card, status);
};
const onLaneScroll = (status: BoardStatus, event: Event) => {
    const target = event.target as HTMLElement;
    const remaining = target.scrollHeight - target.scrollTop - target.clientHeight;
    if (remaining > 64) return;
    if (laneVisibleCount[status] < sortedLaneCards(status).length) {
        laneVisibleCount[status] += 20;
    }
};
const openCardRecord = (card: BoardCard) => {
    if (draggedCard.value) return;
    window.location.assign(card.entityType === 'jobcard' ? `/jobcards/${card.id}` : `/tasks/${card.id}/edit`);
};
onBeforeUnmount(() => {
    document.removeEventListener('dragover', onKanbanAutoScrollDragOver);
    if (userLocationPollTimer) {
        clearInterval(userLocationPollTimer);
        userLocationPollTimer = null;
    }
    clearBoardDrawerMarkers();
    boardDrawerMap.value = null;
    boardDrawerInfoWindow.value = null;
});
const submitCreateTask = async () => {
    creatingTask.value = true;
    taskFormErrors.value = {};
    const response = await fetch('/dispatch/tasks', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify({
            title: taskForm.title,
            description: taskForm.description || null,
            status: taskForm.status,
            assigned_to_user_id: toApiValue(taskForm.assigned_to_user_id),
            assigned_to_team_id: toApiValue(taskForm.assigned_to_team_id),
            scheduled_start_at: taskForm.scheduled_start_at || null,
            scheduled_end_at: taskForm.scheduled_end_at || null,
        }),
    });
    creatingTask.value = false;

    if (!response.ok) {
        const data = await response.json().catch(() => null);
        taskFormErrors.value = Object.fromEntries(
            Object.entries((data?.errors ?? {}) as Record<string, string[]>).map(([key, values]) => [key, values[0] ?? 'Invalid value'])
        );
        feedback.value = data?.message ?? 'Failed to create task.';
        return;
    }

    closeCreateTaskModal();
    feedback.value = 'Task created successfully.';
    await loadBoard();
};
const formatScheduledDate = (value?: string | null) => {
    if (!value) return 'Unscheduled';
    return formatDispatchScheduleInstant(value);
};

watch(
    [
        viewMode,
        boardDate,
        boardTab,
        boardSearch,
        boardPriority,
        () => filters.value.assigned_to_user_id,
        () => filters.value.assigned_to_team_id,
    ],
    () => {
        if (!hasLoadedDispatchPreferences.value) return;
        saveDispatchPreferences();
    },
    { deep: false },
);
watch(viewMode, (mode) => {
    void loadBoard();
    if (mode !== 'board') {
        selectedDispatchJobcard.value = null;
        showDispatchJobMapModal.value = false;
    }
});
watch([boardDate, boardTab, boardPriority, () => filters.value.assigned_to_user_id, () => filters.value.assigned_to_team_id], () => {
    if (viewMode.value !== 'board') return;
    void loadBoard();
});
const reloadBoardIfBoard = useDebounceFn(() => {
    if (viewMode.value === 'board') void loadBoard();
}, 400);
watch(boardSearch, () => reloadBoardIfBoard());
watch(
    currentUserId,
    () => {
        hasLoadedDispatchPreferences.value = false;
        loadDispatchPreferences();
        hasLoadedDispatchPreferences.value = true;
        void loadBoard();
    },
    { immediate: true },
);
watch(
    [() => filters.value.assigned_to_user_id, () => filters.value.assigned_to_team_id],
    () => {
        void loadBoard();
    },
);
watch(showDispatchJobMapModal, async (open) => {
    if (open) {
        boardDrawerMap.value = null;
        dispatchMapContainerKey.value += 1;
        await nextTick();
        await nextTick();
        await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()));
        await refreshUserLocations();
        if (userLocationPollTimer) {
            clearInterval(userLocationPollTimer);
        }
        userLocationPollTimer = setInterval(() => {
            void refreshUserLocations();
        }, 15000);
        void ensureBoardDrawerMap();
    } else {
        if (userLocationPollTimer) {
            clearInterval(userLocationPollTimer);
            userLocationPollTimer = null;
        }
        clearBoardDrawerMarkers();
        boardDrawerMap.value = null;
        boardDrawerMapError.value = '';
        boardDrawerInfoWindow.value = null;
    }
});
watch(
    [selectedDispatchJobcard, selectedDrawerJobAddress, googleMapsApiKey, () => userLocations.value, mapTechnicianFilterUserId],
    async () => {
        if (!showDispatchJobMapModal.value || viewMode.value !== 'board') {
            return;
        }
        await nextTick();
        void ensureBoardDrawerMap();
    },
    { deep: true },
);
watch(mapTechnicianFilterUserId, () => {
    if (
        selectedMapUser.value &&
        mapTechnicianFilterUserId.value &&
        String(selectedMapUser.value.location.user_id) !== mapTechnicianFilterUserId.value
    ) {
        selectedMapUser.value = null;
        showMapUserPinModal.value = false;
    }
});
watch(selectedDispatchJobcard, (job) => {
    if (!job) {
        showDispatchJobMapModal.value = false;
    }
    technicianSuggestions.value = [];
});
watch(
    [selectedMapUser, etaDestinationAddress],
    () => {
        void updateSelectedUserEta();
    },
    { deep: true },
);
</script>

<template>
    <Head title="Dispatch" />

    <AppLayout :breadcrumbs="[{ title: 'Dispatch', href: '/dispatch' }]">
        <Dialog v-model:open="showDispatchJobMapModal">
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>
                        Map
                        <template v-if="selectedDispatchJobcard">
                            —
                            {{ selectedDispatchJobcard.job_number || `Jobcard #${selectedDispatchJobcard.id}` }}
                        </template>
                    </DialogTitle>
                    <DialogDescription class="sr-only">
                        Map for the selected jobcard showing technician GPS locations and the job address when available.
                    </DialogDescription>
                </DialogHeader>
                <div v-if="googleMapsApiKey" class="space-y-2">
                    <div class="flex items-center gap-2">
                        <label for="dispatch-map-technician-filter" class="text-xs font-medium text-muted-foreground">Technician</label>
                        <select
                            id="dispatch-map-technician-filter"
                            v-model="mapTechnicianFilterUserId"
                            class="h-8 rounded-md border border-input bg-background px-2 text-xs"
                        >
                            <option value="">All technicians</option>
                            <option v-for="tech in mapTechnicianOptions" :key="tech.userId" :value="tech.userId">
                                {{ tech.name }}
                            </option>
                        </select>
                    </div>
                    <div class="h-[min(60vh,520px)] w-full overflow-hidden rounded-md border border-border bg-muted/30">
                        <div
                            :key="dispatchMapContainerKey"
                            ref="boardDrawerMapContainerRef"
                            class="h-full min-h-[280px] w-full"
                        />
                    </div>
                    <p v-if="boardDrawerMapError" class="text-xs text-amber-800">{{ boardDrawerMapError }}</p>
                </div>
                <p v-else class="text-sm text-muted-foreground">
                    Configure a Google Maps API key under Administration → Other Integrations.
                </p>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="showMapUserPinModal">
            <DialogContent v-if="selectedMapUser" class="max-h-[85vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{{ selectedMapUser.location.name }}</DialogTitle>
                    <DialogDescription class="sr-only">
                        Technician location, driving ETA to the open job when an address is set, and jobs scheduled for the board date.
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-3 text-sm text-gray-700">
                    <p class="text-xs text-gray-500">
                        {{ formatLocationLastUpdatedLabel(selectedMapUser.location) }}
                    </p>
                    <p>
                        Nearest address:
                        {{ selectedMapUser.nearestAddress || 'Loading address...' }}
                    </p>
                    <p>
                        Location: {{ selectedMapUser.location.lat.toFixed(5) }},
                        {{ selectedMapUser.location.lng.toFixed(5) }}
                    </p>
                    <p v-if="etaDestinationAddress">
                        ETA to job:
                        <span v-if="etaLoading">calculating…</span>
                        <span v-else-if="selectedUserEta">
                            {{ selectedUserEta.durationText }} ({{ selectedUserEta.distanceText }})
                            <template v-if="selectedUserEta.trafficLevel != null">
                                · Traffic:
                                <span
                                    class="ml-1 inline-flex items-center rounded-full px-1.5 py-0.5 text-[10px] font-semibold ring-1"
                                    :class="etaTrafficBadgeClass(selectedUserEta.trafficLevel)"
                                >
                                    {{ formatEtaTrafficLabel(selectedUserEta.trafficLevel) }}
                                </span>
                            </template>
                        </span>
                        <span v-else>unavailable</span>
                    </p>
                    <div>
                        <p class="font-semibold text-gray-900">Daily lineup</p>
                        <ul class="mt-2 space-y-1 text-xs">
                            <li
                                v-for="item in selectedUserDailyLineup"
                                :key="`lineup-modal-${item.entityType}-${item.id}`"
                            >
                                {{ formatBoardLineupRow(item) }}
                            </li>
                            <li v-if="selectedUserDailyLineup.length === 0" class="text-gray-500">
                                No jobs lined up for the board date.
                            </li>
                        </ul>
                    </div>
                    <div class="flex justify-end pt-2">
                        <Button type="button" variant="outline" @click="showMapUserPinModal = false">Close</Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>

        <div class="space-y-6 p-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Dispatch & Scheduling</h1>
                <p class="text-sm text-gray-600">
                    Day dispatch board for jobcards and Kanban (jobcards &amp; tasks) with saved preferences.
                </p>
                <div class="mt-3 inline-flex rounded-lg border bg-white p-1">
                    <button
                        type="button"
                        class="rounded px-3 py-1 text-sm"
                        :class="viewMode === 'board' ? 'bg-indigo-600 text-white' : 'text-gray-700 hover:bg-gray-100'"
                        @click="viewMode = 'board'"
                    >
                        Board
                    </button>
                    <button
                        type="button"
                        class="rounded px-3 py-1 text-sm"
                        :class="viewMode === 'kanban' ? 'bg-indigo-600 text-white' : 'text-gray-700 hover:bg-gray-100'"
                        @click="viewMode = 'kanban'"
                    >
                        Kanban
                    </button>
                </div>
            </div>

            <div
                v-if="viewMode === 'board'"
                class="grid min-h-0 gap-3 xl:grid-cols-[260px_minmax(0,1fr)_300px] dispatch-three-col"
            >
                <div class="flex min-h-0 flex-col gap-3 overflow-hidden">
                    <DispatchFilters
                        v-model:board-date="boardDate"
                        v-model:search="boardSearch"
                        v-model:assigned-user-id="filters.assigned_to_user_id"
                        v-model:assigned-team-id="filters.assigned_to_team_id"
                        v-model:priority="boardPriority"
                        v-model:tab="boardTab"
                        :users="board.users"
                        :teams="board.teams"
                        :loading="loading"
                        @refresh="loadBoard"
                    />
                    <UnscheduledJobcardList
                        :jobcards="board.unscheduled_jobcards"
                        :conflict-ids="conflictJobcardIds"
                        @select="selectedDispatchJobcard = $event"
                    />
                </div>
                <TechnicianTimelineBoard
                    :board-date="boardDate"
                    :users="board.users"
                    :jobcards="timelineScheduledJobcards"
                    :conflict-ids="conflictJobcardIds"
                    :show-all-hours="showTimelineAllHours"
                    @toggle-all-hours="showTimelineAllHours = $event"
                    @select="selectedDispatchJobcard = $event"
                    @drop-jobcard="onBoardDropJobcard"
                    @move-jobcard="onBoardMoveJobcard"
                />
                <DispatchJobcardDrawer
                    :job="selectedDispatchJobcard"
                    :users="board.users"
                    :teams="board.teams"
                    :google-maps-api-key="googleMapsApiKey"
                    :technician-suggestions="technicianSuggestions"
                    :technician-suggestions-loading="technicianSuggestionsLoading"
                    @open-full="openSelectedDispatchFull"
                    @view-map="showDispatchJobMapModal = true"
                    @set-status="onDrawerStatus"
                    @assign="onDrawerAssign"
                    @unschedule="onDrawerUnschedule"
                    @set-estimated-duration="onDrawerEstimatedDuration"
                    @suggest-technicians="onSuggestTechnicians"
                />
            </div>

            <div v-else-if="viewMode === 'kanban'" class="flex min-h-0 min-w-0 flex-col">
                <div class="mb-3 flex justify-end">
                    <button
                        type="button"
                        class="rounded bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                        @click="openCreateTaskModal()"
                    >
                        + Add Task
                    </button>
                </div>
                <div ref="kanbanScrollRef" class="min-w-0 flex-1 overflow-x-auto pb-1">
                    <div class="flex flex-nowrap gap-3">
                        <div
                            v-for="status in boardStatuses"
                            :key="status.value"
                            class="relative flex w-72 shrink-0 flex-col rounded-lg border border-gray-200 bg-gray-100 p-3 shadow-sm transition-colors"
                            :class="laneHoverStatus === status.value ? 'ring-2 ring-indigo-400 ring-offset-1' : ''"
                            @dragover="onLaneDragOver(status.value, $event)"
                            @drop="onLaneDrop(status.value, $event)"
                        >
                            <div
                                v-if="draggedCard?.entityType === 'task' && !isTaskAllowedLane(status.value)"
                                class="pointer-events-none absolute inset-0 z-20 flex flex-col items-center justify-center gap-1 rounded-lg bg-white/85 px-2 backdrop-blur-[1px]"
                                aria-hidden="true"
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    class="h-12 w-12 shrink-0 text-red-600"
                                >
                                    <circle cx="12" cy="12" r="10" />
                                    <line x1="4.93" y1="4.93" x2="19.07" y2="19.07" />
                                </svg>
                                <span class="text-center text-[10px] font-semibold leading-tight text-red-800">Not a task status</span>
                            </div>
                        <div class="mb-3 flex items-baseline justify-between gap-2 border-b border-gray-200/80 pb-2">
                            <h3 class="text-sm font-semibold text-gray-900">{{ status.label }}</h3>
                            <span class="shrink-0 text-xs tabular-nums text-gray-500">{{ sortedLaneCards(status.value).length }}</span>
                        </div>

                        <div class="max-h-[62vh] min-h-0 flex-1 space-y-2 overflow-y-auto pr-1" @scroll="onLaneScroll(status.value, $event)">
                            <div
                                v-for="card in visibleLaneCards(status.value)"
                                :key="`${card.entityType}-${card.id}`"
                                draggable="true"
                                class="cursor-move rounded-lg border border-gray-200 bg-white p-3 shadow-sm transition hover:border-indigo-300 hover:shadow-md"
                                @dragstart="onDragStart(card)"
                                @dragend="onDragEnd"
                                @click="openCardRecord(card)"
                            >
                                <div class="mb-1 flex items-center justify-between">
                                    <span class="rounded px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide" :class="card.entityType === 'jobcard' ? 'bg-indigo-100 text-indigo-700' : 'bg-violet-100 text-violet-700'">
                                        {{ card.entityType }}
                                    </span>
                                    <span class="text-xs text-gray-500">{{ normalizedStatus(card).replace('_', ' ') }} · #{{ card.id }}</span>
                                </div>
                                <div class="text-sm font-semibold text-gray-900">{{ card.entityType === 'jobcard' ? card.job_number || `Jobcard #${card.id}` : card.title || `Task #${card.id}` }}</div>
                                <div v-if="card.entityType === 'jobcard' && card.title" class="mt-1 text-xs text-gray-600">{{ card.title }}</div>
                                <div class="mt-2 text-xs text-gray-600">Scheduled: {{ formatScheduledDate(card.scheduled_start_at) }}</div>
                                <div class="mt-1 text-xs text-gray-600">Assigned: {{ card.assigned_user?.name || card.assigned_team?.name || 'Unassigned' }}</div>
                            </div>
                            <div v-if="visibleLaneCards(status.value).length === 0" class="rounded-lg border border-dashed border-gray-300 bg-white/70 p-4 text-center text-xs text-gray-500">Drop cards here</div>
                            <div
                                v-else-if="visibleLaneCards(status.value).length < sortedLaneCards(status.value).length"
                                class="rounded-lg border border-dashed border-gray-300 bg-white/70 p-2 text-center text-xs text-gray-500"
                            >
                                Scroll for more...
                            </div>
                        </div>
                        </div>
                    </div>
                </div>
            </div>

            <p v-if="feedback" class="text-sm text-gray-600">{{ feedback }}</p>
            <div v-if="loading" class="text-sm text-gray-500">Working...</div>

            <div v-if="showCreateTaskModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
                <div class="w-full max-w-xl rounded-lg bg-white p-5 shadow-xl">
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-900">Create Task</h3>
                        <button type="button" class="text-gray-500 hover:text-gray-700" @click="closeCreateTaskModal">✕</button>
                    </div>
                    <div class="space-y-3">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Title</label>
                            <input v-model="taskForm.title" type="text" class="w-full rounded border px-3 py-2 text-sm" />
                            <p v-if="taskFormErrors.title" class="mt-1 text-xs text-red-600">{{ taskFormErrors.title }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Description</label>
                            <textarea v-model="taskForm.description" rows="3" class="w-full rounded border px-3 py-2 text-sm" />
                        </div>
                        <div class="grid gap-3 md:grid-cols-3">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Status</label>
                                <select v-model="taskForm.status" class="w-full rounded border px-3 py-2 text-sm">
                                    <option value="new">New</option>
                                    <option value="scheduled">Scheduled</option>
                                    <option value="accepted">Accepted</option>
                                    <option value="completed">Completed</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Assign User</label>
                                <select v-model="taskForm.assigned_to_user_id" class="w-full rounded border px-3 py-2 text-sm">
                                    <option value="">None</option>
                                    <option v-for="user in board.users" :key="user.id" :value="String(user.id)">{{ user.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Assign Team</label>
                                <select v-model="taskForm.assigned_to_team_id" class="w-full rounded border px-3 py-2 text-sm">
                                    <option value="">None</option>
                                    <option v-for="team in board.teams" :key="team.id" :value="String(team.id)">{{ team.name }}</option>
                                </select>
                            </div>
                        </div>
                        <p v-if="taskFormErrors.assigned_to_user_id || taskFormErrors.assigned_to_team_id" class="text-xs text-red-600">
                            {{ taskFormErrors.assigned_to_user_id || taskFormErrors.assigned_to_team_id }}
                        </p>
                        <div class="grid gap-3 md:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Scheduled Start</label>
                                <input v-model="taskForm.scheduled_start_at" type="datetime-local" class="w-full rounded border px-3 py-2 text-sm" />
                                <p v-if="taskFormErrors.scheduled_start_at" class="mt-1 text-xs text-red-600">{{ taskFormErrors.scheduled_start_at }}</p>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Scheduled End</label>
                                <input v-model="taskForm.scheduled_end_at" type="datetime-local" class="w-full rounded border px-3 py-2 text-sm" />
                                <p v-if="taskFormErrors.scheduled_end_at" class="mt-1 text-xs text-red-600">{{ taskFormErrors.scheduled_end_at }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="mt-5 flex justify-end gap-2">
                        <button type="button" class="rounded border px-3 py-2 text-sm text-gray-700 hover:bg-gray-50" @click="closeCreateTaskModal">Cancel</button>
                        <button
                            type="button"
                            class="rounded bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-60"
                            :disabled="creatingTask"
                            @click="submitCreateTask"
                        >
                            {{ creatingTask ? 'Creating...' : 'Create Task' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style>
.dispatch-three-col {
    height: calc(100vh - 210px);
    min-height: 620px;
}
</style>
