import { loadGoogleMapsJavaScriptApi } from '@/lib/googleMapsLoader';
import { usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

const addressCache = new Map<string, string>();
const inflight = new Map<string, Promise<string>>();

function cacheKey(lat: number, lng: number): string {
    return `${lat.toFixed(6)},${lng.toFixed(6)}`;
}

function coordsLabel(
    lat: number | string | null | undefined,
    lng: number | string | null | undefined,
    accuracy?: number | string | null,
): string {
    if (lat == null || lng == null) {
        return '';
    }

    const coords = `${Number(lat).toFixed(5)}, ${Number(lng).toFixed(5)}`;
    if (accuracy != null && accuracy !== '') {
        return `${coords} ±${Number(accuracy).toFixed(0)}m`;
    }

    return coords;
}

export function formatLocationWithAddress(
    address: string | null | undefined,
    lat: number | string | null | undefined,
    lng: number | string | null | undefined,
    accuracy?: number | string | null,
): string {
    const coords = coordsLabel(lat, lng, accuracy);
    if (!coords) {
        return '';
    }

    const trimmedAddress = (address || '').trim();
    if (!trimmedAddress || trimmedAddress === 'Address unavailable') {
        return `(${coords})`;
    }

    return `${trimmedAddress} (${coords})`;
}

export function mapsUrlForCoords(
    lat: number | string | null | undefined,
    lng: number | string | null | undefined,
): string {
    return `https://www.google.com/maps?q=${lat},${lng}`;
}

export function useReverseGeocode() {
    const page = usePage();
    const resolving = ref(false);

    async function reverseGeocode(
        lat: number | string | null | undefined,
        lng: number | string | null | undefined,
    ): Promise<string | null> {
        if (lat == null || lng == null) {
            return null;
        }

        const latitude = Number(lat);
        const longitude = Number(lng);
        if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) {
            return null;
        }

        const key = cacheKey(latitude, longitude);
        const cached = addressCache.get(key);
        if (cached) {
            return cached;
        }

        const existing = inflight.get(key);
        if (existing) {
            return existing;
        }

        const apiKey = String(page.props.google_maps_api_key || '').trim();
        if (!apiKey) {
            return null;
        }

        resolving.value = true;
        const promise = (async () => {
            try {
                const maps = await loadGoogleMapsJavaScriptApi(apiKey);
                const geocoder = new maps.Geocoder();
                const response = await geocoder.geocode({
                    location: { lat: latitude, lng: longitude },
                });
                const nearest = response?.results?.[0]?.formatted_address?.trim() || 'Address unavailable';
                addressCache.set(key, nearest);
                return nearest;
            } catch {
                return null;
            } finally {
                inflight.delete(key);
                resolving.value = false;
            }
        })();

        inflight.set(key, promise);
        return promise;
    }

    return {
        reverseGeocode,
        formatLocationWithAddress,
        mapsUrlForCoords,
        coordsLabel,
        resolving,
    };
}
