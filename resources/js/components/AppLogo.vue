<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const company = page.props.company as { name?: string; logo_path?: string } | null;
</script>

<template>
    <div
        class="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground overflow-hidden"
    >
        <img
            v-if="company?.logo_path"
            :src="`/storage/${company.logo_path}`"
            :alt="company.name || 'Company Logo'"
            class="size-8 object-cover rounded-md"
        />
        <AppLogoIcon
            v-else
            class="size-5 fill-current text-white dark:text-black"
        />
    </div>
    <div class="ml-1 grid flex-1 text-left text-sm">
        <span class="mb-0.5 truncate leading-tight font-semibold">
            {{ company?.name || 'Laravel Starter Kit' }}
        </span>
    </div>
</template>
