import { loadGoogleMapsJavaScriptApi } from '@/lib/googleMapsLoader';

/**
 * Attach Google Places Autocomplete to a text input. Returns a teardown function.
 * Requires Maps JavaScript API + Places API enabled for the same API key.
 */
export async function attachPlacesAutocomplete(
    input: HTMLInputElement,
    apiKey: string,
    onFormattedAddress: (address: string) => void,
): Promise<() => void> {
    const maps = await loadGoogleMapsJavaScriptApi(apiKey);
    const placesLib = (await maps.importLibrary('places')) as google.maps.PlacesLibrary;

    const autocomplete = new placesLib.Autocomplete(input, {
        fields: ['formatted_address', 'geometry', 'name'],
        types: ['address'],
        /** Prefer South African addresses (ISO 3166-1 alpha-2). */
        componentRestrictions: { country: 'za' },
    });

    const listener = autocomplete.addListener('place_changed', () => {
        const place = autocomplete.getPlace();
        const addr = place.formatted_address?.trim();
        if (addr) {
            onFormattedAddress(addr);
        }
    });

    return () => {
        listener.remove();
        if (typeof google !== 'undefined' && google.maps?.event) {
            google.maps.event.clearInstanceListeners(autocomplete);
        }
    };
}
