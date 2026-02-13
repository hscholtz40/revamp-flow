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
import { BookOpen, Folder, LayoutGrid, Users, Settings, UserCheck, Package, Building2, ClipboardList, FileText, Receipt, Warehouse, ArrowUpDown, ShoppingCart, Clock, BarChart3, KeyRound } from 'lucide-vue-next';
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
    'Reports': 'reports',
    'Timesheet': 'timesheet',
};

const userType = computed(() => (page.props.auth?.user as any)?.user_type ?? 'standard');

const filteredNavItems = computed(() => {
    return mainNavItems.filter((item) => {
        // Dashboard is always visible
        if (item.title === 'Dashboard') {
            return true;
        }

        // Limited users can only see Jobcards and Timesheet
        if (userType.value === 'limited') {
            return item.title === 'Jobcards' || item.title === 'Timesheet';
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
        
        // Then check permissions
        if (item.title === 'Customers') {
            return page.props.auth?.abilities?.customers?.list;
        }
        if (item.title === 'Contacts') {
            return page.props.auth?.abilities?.contacts?.list;
        }
        if (item.title === 'Products & Services') {
            return page.props.auth?.abilities?.products?.list;
        }
        if (item.title === 'Suppliers') {
            return page.props.auth?.abilities?.suppliers?.list;
        }
        if (item.title === 'Stock Movements') {
            return page.props.auth?.abilities?.['stock-movements']?.view;
        }
        if (item.title === 'Purchase Orders') {
            return page.props.auth?.abilities?.['purchase-orders']?.list;
        }
        if (item.title === 'Users') {
            return page.props.auth?.abilities?.users?.list;
        }
        if (item.title === 'Groups') {
            return page.props.auth?.abilities?.groups?.list;
        }
        if (item.title === 'Reports') {
            return page.props.auth?.abilities?.reports?.list;
        }
        return true;
    });
});
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child class="!h-auto py-3">
                        <Link :href="dashboard().url" class="block w-full">
                            <!-- Company Logo or Fallback -->
                            <div class="flex flex-col gap-2 w-full">
                                <div class="flex w-full items-center justify-center rounded-lg bg-primary/10 p-2">
                                    <img
                                        v-if="currentCompany?.logo_path"
                                        :src="`/storage/${currentCompany.logo_path}`"
                                        :alt="currentCompany.name"
                                        class="w-full h-auto max-h-16 object-contain rounded"
                                    />
                                    <img
                                        v-else
                                        src="/jobcardonline-logo.png"
                                        alt="JobCardOnline"
                                        class="w-full h-auto max-h-16 object-contain rounded"
                                    />
                                </div>
                                <div class="flex flex-col text-center">
                                    <span class="text-sm font-semibold text-gray-900">
                                        {{ currentCompany?.name || 'Company' }}
                                    </span>
                                    <span class="text-xs text-gray-500">Dashboard</span>
                                </div>
                            </div>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="filteredNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter v-if="userType !== 'limited'" :items="footerNavItems" />
            <!-- Default Logo above user menu when company logo is uploaded -->
            <div v-if="currentCompany?.logo_path" class="mb-3 px-2">
                <img
                    src="/jobcardonline-logo.png"
                    alt="JobCardOnline"
                    class="w-full h-auto max-h-12 object-contain opacity-80 hover:opacity-100 transition-opacity"
                />
            </div>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
