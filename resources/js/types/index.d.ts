import { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from 'lucide-vue-next';

export interface Auth {
    user: User | null;
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
}

export type AppPageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    flash?: {
        success?: string | null;
        error?: string | null;
        warning?: string | null;
        info?: string | null;
        status?: string | null;
    };
    sidebarOpen: boolean;
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
