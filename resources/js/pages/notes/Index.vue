<script setup lang="ts">
import NotesPanel from '@/components/NotesPanel.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';

interface ModuleOption {
    value: string;
    label: string;
}

interface Props {
    modules: ModuleOption[];
}

const props = defineProps<Props>();
</script>

<template>
    <Head title="Notes" />

    <AppLayout :breadcrumbs="[{ title: 'Notes', href: '/notes' }]">
        <div class="p-4">
            <NotesPanel
                title="Notes Module"
                :show-module-selector="true"
                :modules="props.modules"
            />
        </div>
    </AppLayout>
</template>
