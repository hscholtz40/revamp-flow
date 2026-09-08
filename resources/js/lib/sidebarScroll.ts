import { router } from '@inertiajs/vue3';

const STORAGE_KEY = 'app-sidebar-content-scroll-top';

let scrollTop = 0;
let wired = false;

function readStored(): number {
    try {
        const raw = sessionStorage.getItem(STORAGE_KEY);
        if (raw != null) {
            const parsed = Number(raw);
            if (Number.isFinite(parsed) && parsed >= 0) {
                return parsed;
            }
        }
    } catch {
        // ignore storage failures
    }

    return scrollTop;
}

export function getSidebarScrollTop(): number {
    return scrollTop;
}

export function setSidebarScrollTop(value: number): void {
    scrollTop = Math.max(0, Number.isFinite(value) ? value : 0);
    try {
        sessionStorage.setItem(STORAGE_KEY, String(scrollTop));
    } catch {
        // ignore storage failures
    }
}

export function captureSidebarScrollFromDom(): void {
    const el = document.querySelector<HTMLElement>('[data-sidebar="content"]');
    if (el) {
        setSidebarScrollTop(el.scrollTop);
    }
}

export function applySidebarScrollToDom(): void {
    const top = readStored();
    scrollTop = top;

    const apply = () => {
        const el = document.querySelector<HTMLElement>('[data-sidebar="content"]');
        if (el) {
            el.scrollTop = top;
        }
    };

    apply();
    requestAnimationFrame(apply);
    window.setTimeout(apply, 0);
    window.setTimeout(apply, 50);
    window.setTimeout(apply, 150);
}

/**
 * Survives AppLayout remounts on Inertia navigations (layout is nested in each page).
 */
export function ensureSidebarScrollPersistence(): void {
    if (wired || typeof window === 'undefined') {
        return;
    }

    wired = true;
    scrollTop = readStored();

    router.on('before', () => {
        captureSidebarScrollFromDom();
    });

    router.on('finish', () => {
        applySidebarScrollToDom();
    });
}
