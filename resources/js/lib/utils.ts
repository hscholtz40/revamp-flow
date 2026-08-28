import { InertiaLinkProps } from '@inertiajs/vue3';
import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';
import type { NavItem } from '@/types';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(href: NonNullable<InertiaLinkProps['href']>) {
    return typeof href === 'string' ? href : href?.url;
}

export function normalizeNavPath(url: string): string {
    const path = url.split('?')[0].split('#')[0];

    if (path.length > 1 && path.endsWith('/')) {
        return path.slice(0, -1);
    }

    return path;
}

export function collectNavHrefs(items: NavItem[]): string[] {
    const hrefs: string[] = [];

    for (const item of items) {
        const href = toUrl(item.href);
        if (href) {
            hrefs.push(href);
        }

        item.children?.forEach((child) => {
            const childHref = toUrl(child.href);
            if (childHref) {
                hrefs.push(childHref);
            }
        });
    }

    return hrefs;
}

export function urlIsActive(
    urlToCheck: NonNullable<InertiaLinkProps['href']>,
    currentUrl: string,
    allNavHrefs: string[] = [],
) {
    const target = normalizeNavPath(toUrl(urlToCheck) ?? '');
    const current = normalizeNavPath(currentUrl);

    if (!target) {
        return false;
    }

    const matches =
        current === target ||
        (target !== '/' && current.startsWith(`${target}/`));

    if (!matches) {
        return false;
    }

    if (current === target) {
        return true;
    }

    if (allNavHrefs.length === 0) {
        return true;
    }

    const normalizedNav = allNavHrefs.map((href) => normalizeNavPath(href));

    const hasMoreSpecificNavMatch = normalizedNav.some((other) => {
        if (!other || other === target) {
            return false;
        }

        if (!other.startsWith(`${target}/`)) {
            return false;
        }

        return current === other || current.startsWith(`${other}/`);
    });

    return !hasMoreSpecificNavMatch;
}
