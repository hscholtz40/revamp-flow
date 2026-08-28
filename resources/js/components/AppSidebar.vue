<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import customers from '@/routes/customers';
import contacts from '@/routes/contacts';
import products from '@/routes/products';
import suppliers from '@/routes/suppliers';
import stockMovements from '@/routes/stock-movements';
import purchaseOrders from '@/routes/purchase-orders';
import jobcards from '@/routes/jobcards';
import quotes from '@/routes/quotes';
import invoices from '@/routes/invoices';
import reports from '@/routes/reports';
import users from '@/routes/users';
import groups from '@/routes/groups';
import administration from '@/routes/administration';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { BookOpen, Folder, LayoutGrid, Users, Settings, UserCheck, Package, Building2, ClipboardList, FileText, Receipt, Warehouse, ArrowUpDown, ShoppingCart, Clock, BarChart3, KeyRound, CreditCard, MessageSquare, CalendarDays, CheckSquare, Inbox } from 'lucide-vue-next';
import licenses from '@/routes/licenses';
import AppLogo from './AppLogo.vue';
import { computed } from 'vue';

const page = usePage();

const currentCompany = computed(() => page.props.currentCompany as {
    id: number;
    name: string;
    logo_path: string | null;
    visible_modules: string[] | null;
} | null);

const isLicensingInstance = computed(() => (page.props as any).isLicensingInstance === true);

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
        icon: LayoutGrid,
    },
    {
        title: 'AI Assistant',
        href: '/ai-assistant',
        icon: MessageSquare,
    },
    {
        title: 'Client Zone',
        href: '/client-zone',
        icon: BookOpen,
    },
    {
        title: 'Client Documents',
        href: '/client-zone/documents',
        icon: Folder,
    },
    {
        title: 'Request Info Update',
        href: '/client-zone/request-update',
        icon: Building2,
    },
    {
        title: 'Registered Users',
        href: '/registered-users',
        icon: Users,
    },
    {
        title: 'Customers',
        href: customers.index().url,
        icon: Users,
    },
    {
        title: 'Contacts',
        href: contacts.index().url,
        icon: UserCheck,
    },
    {
        title: 'Products & Services',
        href: products.index().url,
        icon: Package,
    },
    {
        title: 'Suppliers',
        href: suppliers.index().url,
        icon: Warehouse,
    },
    {
        title: 'Stock Movements',
        href: stockMovements.index().url,
        icon: ArrowUpDown,
    },
    {
        title: 'Purchase Orders',
        href: purchaseOrders.index().url,
        icon: ShoppingCart,
    },
    {
        title: 'Jobcards',
        href: jobcards.index().url,
        icon: ClipboardList,
        children: [
            {
                title: 'Dispatch',
                href: '/dispatch',
                icon: CalendarDays,
                moduleKey: 'dispatch',
            },
        ],
    },
    {
        title: 'Quotes',
        href: quotes.index().url,
        icon: FileText,
    },
    {
        title: 'Invoices',
        href: invoices.index().url,
        icon: Receipt,
    },
    {
        title: 'Credit Notes',
        href: '/credit-notes',
        icon: CreditCard,
    },
    {
        title: 'Reports',
        href: reports.index().url,
        icon: BarChart3,
    },
    {
        title: 'Timesheet',
        href: '/time-entries',
        icon: Clock,
    },
    {
        title: 'Tasks',
        href: '/tasks',
        icon: CheckSquare,
    },
    {
        title: 'Queries',
        href: '/queries',
        icon: Inbox,
    },
    {
        title: 'Licensing',
        href: licenses.index().url,
        icon: KeyRound,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Administration',
        href: administration.index().url,
        icon: Settings,
    },
];

const isAdministrator = computed(() => !!(page.props.auth?.user as { is_administrator?: boolean } | null)?.is_administrator);

const filteredFooterNavItems = computed(() => {
    if (!isAdministrator.value) {
        return [];
    }
    return footerNavItems;
});

const moduleKeyMap: Record<string, string> = {
    'Customers': 'customers',
    'Contacts': 'contacts',
    'Products & Services': 'products',
    'Suppliers': 'suppliers',
    'Stock Movements': 'stock-movements',
    'Purchase Orders': 'purchase-orders',
    'Jobcards': 'jobcards',
    'Quotes': 'quotes',
    'Invoices': 'invoices',
    'Credit Notes': 'credit-notes',
    'Reports': 'reports',
    'Timesheet': 'timesheet',
    'Tasks': 'tasks',
    'Queries': 'queries',
};

const userType = computed(() => (page.props.auth?.user as any)?.user_type ?? 'standard');
const homeHref = computed(() => userType.value === 'client' ? '/client-zone' : dashboard().url);

const dispatchEnabledForCompany = computed(
    () => !!(page.props.currentCompany as { enable_dispatch?: boolean } | null | undefined)?.enable_dispatch,
);

const filteredNavItems = computed(() => {
    const withFilteredChildren = mainNavItems.map((item) => {
        if (item.title === 'Jobcards' && item.children?.length) {
            const children = item.children.filter((child) => {
                if (child.moduleKey === 'dispatch') {
                    return (
                        dispatchEnabledForCompany.value &&
                        !!(page.props.auth?.abilities as { dispatch?: { list?: boolean } } | undefined)?.dispatch?.list
                    );
                }
                return true;
            });
            return { ...item, children };
        }
        return item;
    });

    return withFilteredChildren.filter((item) => {
        if (userType.value === 'client') {
            return ['Client Zone', 'Client Documents', 'Request Info Update'].includes(item.title);
        }

        // Dashboard is always visible
        if (item.title === 'Dashboard') {
            return true;
        }
        if (item.title === 'AI Assistant') {
            return !!(page.props as { ai?: { allowed?: boolean } }).ai?.allowed;
        }
        if (item.title === 'Tasks') {
            return true;
        }

        if (item.title === 'Client Zone') {
            return false;
        }
        if (item.title === 'Client Documents' || item.title === 'Request Info Update') {
            return false;
        }

        // Licensing is only visible on licensing instances
        if (item.title === 'Licensing') {
            return isLicensingInstance.value;
        }

        // Check module visibility settings first
        const moduleKey = moduleKeyMap[item.title];
        if (moduleKey && currentCompany.value) {
            const visibleModules = currentCompany.value.visible_modules;

            // If visible_modules is null or undefined, all modules are visible (default)
            if (visibleModules === null || visibleModules === undefined) {
                // Continue to permission check - show module
            }
            // If visible_modules is an array, check if the module is in the array
            else if (Array.isArray(visibleModules)) {
                // If array is empty, hide all modules (except Dashboard)
                if (visibleModules.length === 0) {
                    return false; // Hide module
                }
                // If module is not in the array, hide it
                if (!visibleModules.includes(moduleKey)) {
                    return false; // Module is hidden
                }
            }
        }

        // Limited users only get Jobcards and Timesheet entries (still gated by permissions below)
        if (userType.value === 'limited') {
            if (item.title !== 'Jobcards' && item.title !== 'Timesheet') {
                return false;
            }
        }

        // Then check permissions (aligned with list/index route middleware)
        if (item.title === 'Customers') {
            return !!page.props.auth?.abilities?.customers?.list;
        }
        if (item.title === 'Contacts') {
            return !!page.props.auth?.abilities?.contacts?.list;
        }
        if (item.title === 'Products & Services') {
            return !!page.props.auth?.abilities?.products?.list;
        }
        if (item.title === 'Suppliers') {
            return !!page.props.auth?.abilities?.suppliers?.list;
        }
        if (item.title === 'Stock Movements') {
            return !!page.props.auth?.abilities?.['stock-movements']?.view;
        }
        if (item.title === 'Purchase Orders') {
            return !!page.props.auth?.abilities?.['purchase-orders']?.list;
        }
        if (item.title === 'Jobcards') {
            return !!page.props.auth?.abilities?.jobcards?.list;
        }
        if (item.title === 'Quotes') {
            return !!page.props.auth?.abilities?.quotes?.view;
        }
        if (item.title === 'Invoices') {
            return !!page.props.auth?.abilities?.invoices?.view;
        }
        if (item.title === 'Credit Notes') {
            return !!page.props.auth?.abilities?.['credit-notes']?.list;
        }
        if (item.title === 'Reports') {
            return !!page.props.auth?.abilities?.reports?.list;
        }
        if (item.title === 'Timesheet') {
            return !!page.props.auth?.abilities?.timesheet?.view;
        }
        if (item.title === 'Queries') {
            return !!page.props.auth?.abilities?.queries?.list;
        }
        if (item.title === 'Dispatch') {
            return dispatchEnabledForCompany.value && !!page.props.auth?.abilities?.dispatch?.list;
        }
        if (item.title === 'Registered Users') {
            const abilities = page.props.auth?.abilities as Record<string, { list?: boolean } | undefined> | undefined;
            return !!(abilities?.['registered-users']?.list || abilities?.['customer-update-requests']?.list);
        }
        if (item.title === 'Users') {
            return !!page.props.auth?.abilities?.users?.list;
        }
        if (item.title === 'Groups') {
            return !!page.props.auth?.abilities?.groups?.list;
        }

        return false;
    });
});
</script>

<template>
    <Sidebar
        collapsible="icon"
        variant="inset"
        class="bg-sidebar/95 shadow-sm backdrop-blur supports-[backdrop-filter]:bg-sidebar/90"
    >
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton
                        size="lg"
                        as-child
                        class="!h-auto rounded-2xl border border-sidebar-border/70 bg-sidebar-accent/30 p-3 shadow-sm transition-all duration-200 hover:border-sidebar-border hover:bg-sidebar-accent/60 hover:shadow-md"
                    >
                        <Link :href="homeHref" class="block w-full">
                            <!-- Company Logo or Fallback -->
                            <div class="flex flex-col gap-2 w-full">
                                <div class="flex w-full items-center justify-center rounded-xl bg-gradient-to-br from-primary/10 via-primary/5 to-secondary/10 p-2 ring-1 ring-primary/10">
                                    <img
                                        v-if="currentCompany?.logo_path"
                                        :src="`/storage/${currentCompany.logo_path}`"
                                        :alt="currentCompany.name"
                                        class="w-full h-auto max-h-16 object-contain rounded"
                                    />
                                    <img
                                        v-else
                                        src="/revamp-logo.png"
                                        alt="JobCardOnline"
                                        class="w-full h-auto max-h-16 object-contain rounded"
                                    />
                                </div>
                                <div class="flex flex-col text-center">
                                    <span class="text-sm font-semibold tracking-tight text-sidebar-foreground">
                                        {{ currentCompany?.name || 'Company' }}
                                    </span>
                                    <span class="text-[11px] uppercase tracking-[0.08em] text-sidebar-foreground/55">{{ userType === 'client' ? 'Client Zone' : 'Dashboard' }}</span>
                                </div>
                            </div>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent class="px-2 pb-1">
            <NavMain :items="filteredNavItems" />
        </SidebarContent>

        <SidebarFooter class="border-t border-sidebar-border/60 pt-3">
            <NavFooter v-if="userType !== 'limited' && filteredFooterNavItems.length > 0" :items="filteredFooterNavItems" />
            <img
                src="/revamp-logo.png"
                alt="JobCardOnline"
                class="w-full h-auto max-h-12 object-contain opacity-80 hover:opacity-100 transition-opacity"
            />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
