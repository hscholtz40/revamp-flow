const ALLOWED_EXTERNAL_PROTOCOLS = new Set(['http:', 'https:', 'mailto:', 'tel:']);

export function getSafeExternalUrl(url: string | null | undefined): string | null {
    if (!url || typeof url !== 'string') {
        return null;
    }

    try {
        const parsed = new URL(url, window.location.origin);
        if (!ALLOWED_EXTERNAL_PROTOCOLS.has(parsed.protocol)) {
            return null;
        }

        return parsed.toString();
    } catch {
        return null;
    }
}

