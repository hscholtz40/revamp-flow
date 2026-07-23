import { loadGoogleMapsJavaScriptApi } from '@/lib/googleMapsLoader';

export interface ParsedPlaceAddress {
    streetAddress: string;
    city: string;
    province: string;
    country: string;
    formattedAddress: string;
}

function componentValue(
    components: google.maps.GeocoderAddressComponent[],
    type: string,
    useShort = false,
): string {
    const match = components.find((component) => component.types.includes(type));

    if (!match) {
        return '';
    }

    return (useShort ? match.short_name : match.long_name)?.trim() ?? '';
}

export function parseAddressComponents(
    components: google.maps.GeocoderAddressComponent[],
    formattedAddress: string,
    placeName?: string,
): ParsedPlaceAddress {
    const streetNumber = componentValue(components, 'street_number');
    const route = componentValue(components, 'route');
    const streetAddress = [streetNumber, route].filter(Boolean).join(' ').trim();

    const city =
        componentValue(components, 'locality') ||
        componentValue(components, 'postal_town') ||
        componentValue(components, 'administrative_area_level_2') ||
        componentValue(components, 'sublocality') ||
        componentValue(components, 'neighborhood');

    const province = componentValue(components, 'administrative_area_level_1');

    const country = componentValue(components, 'country');

    return {
        streetAddress:
            streetAddress ||
            placeName?.trim() ||
            formattedAddress.split(',')[0]?.trim() ||
            formattedAddress,
        city,
        province,
        country,
        formattedAddress,
    };
}

/**
 * Attach Google Places Autocomplete to a text input. Returns a teardown function.
 * Requires Maps JavaScript API + Places API enabled for the same API key.
 */
export async function attachPlacesAutocomplete(
    input: HTMLInputElement,
    apiKey: string,
    onPlaceSelected: (address: ParsedPlaceAddress) => void,
): Promise<() => void> {
    const maps = await loadGoogleMapsJavaScriptApi(apiKey);
    const placesLib = (await maps.importLibrary('places')) as google.maps.PlacesLibrary;

    const autocomplete = new placesLib.Autocomplete(input, {
        fields: ['formatted_address', 'address_components', 'geometry', 'name'],
        types: ['address'],
        /** Prefer South African addresses (ISO 3166-1 alpha-2). */
        componentRestrictions: { country: 'za' },
    });

    const listener = autocomplete.addListener('place_changed', () => {
        const place = autocomplete.getPlace();
        const formattedAddress = place.formatted_address?.trim();

        if (!formattedAddress) {
            return;
        }

        const parsed = parseAddressComponents(
            place.address_components ?? [],
            formattedAddress,
            place.name,
        );

        onPlaceSelected(parsed);
    });

    return () => {
        listener.remove();
        if (typeof google !== 'undefined' && google.maps?.event) {
            google.maps.event.clearInstanceListeners(autocomplete);
        }
    };
}
