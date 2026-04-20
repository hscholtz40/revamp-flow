/**
 * Single shared loader for the Google Maps JavaScript API (dynamic `importLibrary` for maps, places, routes, …).
 * One script tag per window; safe across Dispatch, jobcards, and customer forms.
 */

const SCRIPT_ID = 'google-maps-js-api';
const LEGACY_SCRIPT_ID = 'dispatch-google-maps-script';
const PROMISE_KEY = '__jobOnlineGoogleMapsJsPromise';

/** Resolved `google.maps` after the bootstrap script loads (includes `importLibrary` + legacy `Map`, etc.). */
export type MapsNs = typeof google.maps;

const waitForGoogleMapsBootstrap = (maxMs = 15000): Promise<void> => {
    const start = Date.now();
    return new Promise((resolve, reject) => {
        const tick = () => {
            const maps = (window as Window & { google?: { maps?: { importLibrary?: unknown } } }).google?.maps;
            if (maps && typeof maps.importLibrary === 'function') {
                resolve();
                return;
            }
            if (Date.now() - start > maxMs) {
                reject(new Error('Timed out waiting for Google Maps JavaScript API to initialize.'));
                return;
            }
            requestAnimationFrame(tick);
        };
        tick();
    });
};

/**
 * Loads https://maps.googleapis.com/maps/api/js once and resolves `google.maps` (with `importLibrary`).
 */
export async function loadGoogleMapsJavaScriptApi(apiKey: string): Promise<MapsNs> {
    if (typeof window === 'undefined') {
        throw new Error('Google Maps API is only available in the browser.');
    }
    const key = apiKey.trim();
    if (!key) {
        throw new Error('Google Maps API key is missing.');
    }

    const w = window as Window & { google?: { maps?: MapsNs } };
    const store = w as unknown as Record<string, Promise<MapsNs> | undefined>;

    if (w.google?.maps?.importLibrary) {
        return w.google.maps as MapsNs;
    }

    const existingPromise = store[PROMISE_KEY];
    if (existingPromise) {
        return existingPromise;
    }

    store[PROMISE_KEY] = new Promise<MapsNs>((resolve, reject) => {
        const resolveMaps = async () => {
            try {
                await waitForGoogleMapsBootstrap();
                const maps = w.google?.maps;
                if (!maps?.importLibrary) {
                    reject(new Error('Google Maps API loaded but importLibrary is not available.'));
                    return;
                }
                resolve(maps as MapsNs);
            } catch (e) {
                reject(e instanceof Error ? e : new Error(String(e)));
            }
        };

        const existingScript =
            (document.getElementById(SCRIPT_ID) as HTMLScriptElement | null) ||
            (document.getElementById(LEGACY_SCRIPT_ID) as HTMLScriptElement | null);

        if (existingScript) {
            if (w.google?.maps?.importLibrary) {
                void resolveMaps();
            } else if ((existingScript as HTMLScriptElement & { complete?: boolean }).complete) {
                void resolveMaps();
            } else {
                existingScript.addEventListener('load', () => void resolveMaps(), { once: true });
                existingScript.addEventListener('error', () => reject(new Error('Failed to load Google Maps script.')), { once: true });
            }
            return;
        }

        const script = document.createElement('script');
        script.id = SCRIPT_ID;
        script.async = true;
        script.defer = true;
        const bootstrap = new URLSearchParams({
            key,
            loading: 'async',
        });
        script.src = `https://maps.googleapis.com/maps/api/js?${bootstrap.toString()}`;
        script.onload = () => void resolveMaps();
        script.onerror = () => reject(new Error('Failed to load Google Maps script.'));
        document.head.appendChild(script);
    });

    return store[PROMISE_KEY] as Promise<MapsNs>;
}
