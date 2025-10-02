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
import jobcards from '@/routes/jobcards';
import quotes from '@/routes/quotes';
import invoices from '@/routes/invoices';
import users from '@/routes/users';
import groups from '@/routes/groups';
import administration from '@/routes/administration';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { BookOpen, Folder, LayoutGrid, Users, Settings, UserCheck, Package, Building2, ClipboardList, FileText, Receipt } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';
import { computed } from 'vue';

const page = usePage();

const currentCompany = computed(() => page.props.currentCompany as {
    id: number;
    name: string;
    logo_path: string | null;
} | null);

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
];

const footerNavItems: NavItem[] = [
    {
        title: 'Administration',
        href: administration.index().url,
        icon: Settings,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard().url">
                            <!-- Company Logo or Fallback -->
                            <div class="flex items-center gap-3">
                                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-100">
                                    <img
                                        v-if="currentCompany?.logo_path"
                                        :src="`/storage/${currentCompany.logo_path}`"
                                        :alt="currentCompany.name"
                                        class="h-6 w-6 rounded object-cover"
                                    />
                                    <Building2
                                        v-else
                                        class="h-5 w-5 text-blue-600"
                                    />
                                </div>
                                <div class="flex flex-col">
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
            <NavMain
                :items="mainNavItems.filter((item) => {
                    if (item.title === 'Customers') {
                        return $page.props.auth?.abilities?.customers?.list;
                    }
                    if (item.title === 'Contacts') {
                        return $page.props.auth?.abilities?.contacts?.list;
                    }
                    if (item.title === 'Products & Services') {
                        return $page.props.auth?.abilities?.products?.list;
                    }
                    if (item.title === 'Users') {
                        return $page.props.auth?.abilities?.users?.list;
                    }
                    if (item.title === 'Groups') {
                        return $page.props.auth?.abilities?.groups?.list;
                    }
                    return true;
                })"
            />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
