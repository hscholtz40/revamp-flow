import { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from 'lucide-vue-next';

export interface Auth {
    user: User | null;
    /** When set, UI should only offer these cash/card/eft options for recording payments. */
    payment_methods?: {
        cash: boolean;
        card: boolean;
        eft: boolean;
    } | null;
    abilities?: {
        customers?: {
            list?: boolean;
            view?: boolean;
            create?: boolean;
            edit?: boolean;
            delete?: boolean;
        };
        groups?: {
            list?: boolean;
            view?: boolean;
            create?: boolean;
            edit?: boolean;
            delete?: boolean;
        };
        users?: {
            list?: boolean;
            view?: boolean;
            create?: boolean;
            edit?: boolean;
            delete?: boolean;
        };
                contacts?: {
                    list?: boolean;
                    view?: boolean;
                    create?: boolean;
                    edit?: boolean;
                    delete?: boolean;
                };
                products?: {
                    list?: boolean;
                    view?: boolean;
                    create?: boolean;
                    edit?: boolean;
                    delete?: boolean;
                };
                suppliers?: {
                    list?: boolean;
                    view?: boolean;
                    create?: boolean;
                    edit?: boolean;
                    delete?: boolean;
                };
                'stock-movements'?: {
                    list?: boolean;
                    view?: boolean;
                    create?: boolean;
                    edit?: boolean;
                    delete?: boolean;
                };
                'purchase-orders'?: {
                    list?: boolean;
                    view?: boolean;
                    create?: boolean;
                    edit?: boolean;
                    delete?: boolean;
                };
                jobcards?: {
                    list?: boolean;
                    view?: boolean;
                    create?: boolean;
                    edit?: boolean;
                    delete?: boolean;
                };
                quotes?: {
                    list?: boolean;
                    view?: boolean;
                    create?: boolean;
                    edit?: boolean;
                    delete?: boolean;
                };
                invoices?: {
                    list?: boolean;
                    view?: boolean;
                    create?: boolean;
                    edit?: boolean;
                    delete?: boolean;
                };
                'credit-notes'?: {
                    list?: boolean;
                    view?: boolean;
                    create?: boolean;
                    edit?: boolean;
                    delete?: boolean;
                };
                reports?: {
                    list?: boolean;
                    view?: boolean;
                    create?: boolean;
                    edit?: boolean;
                    delete?: boolean;
                };
                timesheet?: {
                    list?: boolean;
                    view?: boolean;
                    create?: boolean;
                    edit?: boolean;
                    delete?: boolean;
                };
                messages?: {
                    list?: boolean;
                    view?: boolean;
                    create?: boolean;
                    edit?: boolean;
                    delete?: boolean;
                };
                dispatch?: {
                    list?: boolean;
                    view?: boolean;
                    create?: boolean;
                    edit?: boolean;
                    delete?: boolean;
                };
                tasks?: {
                    list?: boolean;
                    view?: boolean;
                    create?: boolean;
                    edit?: boolean;
                    delete?: boolean;
                };
        'registered-users'?: {
            list?: boolean;
            view?: boolean;
            approve?: boolean;
        };
        'customer-update-requests'?: {
            list?: boolean;
            view?: boolean;
            approve?: boolean;
        };
    };
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon;
    isActive?: boolean;
    /** When set on a child item, used for module permission checks in the sidebar filter. */
    moduleKey?: string;
    children?: NavItem[];
}

export type AppPageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    app?: {
        version?: string | null;
    };
    flash?: {
        success?: string | null;
        error?: string | null;
        warning?: string | null;
        info?: string | null;
        status?: string | null;
    };
    sidebarOpen: boolean;
    /** Count of unread database notifications for the signed-in user (shared via HandleInertiaRequests). */
    unreadNotificationCount?: number;
    /** Browser Maps JavaScript API key for Places Autocomplete (from Google integration settings). */
    google_maps_api_key?: string;
    ai?: {
        enabled: boolean;
        available: boolean;
        allowed: boolean;
        admin_only: boolean;
        prompt_logging_enabled: boolean;
        daily_user_limit: number;
        daily_company_limit: number;
    };
};

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
}

export type BreadcrumbItemType = BreadcrumbItem;
