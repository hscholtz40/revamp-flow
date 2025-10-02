<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ChevronDown, Building2, Star } from 'lucide-vue-next';
import { ref } from 'vue';
import companySettings from '@/routes/company-settings';

interface Company {
    id: number;
    name: string;
    logo_path: string | null;
    is_default: boolean;
}

interface Props {
    currentCompany: Company | null;
    companies: Company[];
}

const props = defineProps<Props>();

const isOpen = ref(false);

function switchCompany(company: Company) {
    router.post(companySettings.switch(company.id).url, {}, {
        preserveState: true,
        preserveScroll: true,
    });
    isOpen.value = false;
}

function toggleDropdown() {
    isOpen.value = !isOpen.value;
}

// Close dropdown when clicking outside
function handleClickOutside(event: Event) {
    const target = event.target as HTMLElement;
    if (!target.closest('.company-switcher')) {
        isOpen.value = false;
    }
}

// Add event listener when component mounts
import { onMounted, onUnmounted } from 'vue';

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});
</script>

<template>
    <div v-if="props.companies.length > 0" class="company-switcher relative">
        <!-- Company Switcher Button -->
        <button
            @click="toggleDropdown"
            class="flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
        >
            <!-- Company Logo/Icon -->
            <div class="flex h-6 w-6 items-center justify-center rounded bg-blue-100">
                <Building2 class="h-4 w-4 text-blue-600" />
            </div>
            
            <!-- Company Name -->
            <span class="font-medium text-gray-900">
                {{ props.currentCompany?.name || 'Select Company' }}
            </span>
            
            <!-- Dropdown Arrow -->
            <ChevronDown 
                :class="[
                    'h-4 w-4 text-gray-400 transition-transform duration-200',
                    isOpen ? 'rotate-180' : ''
                ]" 
            />
        </button>

        <!-- Dropdown Menu -->
        <div
            v-if="isOpen"
            class="absolute right-0 top-full z-50 mt-2 w-64 rounded-lg border border-gray-200 bg-white shadow-lg"
        >
            <div class="p-2">
                <!-- Show switch options only if there are multiple companies -->
                <div v-if="props.companies.length > 1">
                    <div class="mb-2 px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                        Switch Company
                    </div>
                    
                    <div class="space-y-1">
                        <button
                            v-for="company in props.companies"
                            :key="company.id"
                            @click="switchCompany(company)"
                            :class="[
                                'flex w-full items-center gap-3 rounded-md px-3 py-2 text-left text-sm transition-colors',
                                company.id === props.currentCompany?.id
                                    ? 'bg-blue-50 text-blue-700'
                                    : 'text-gray-700 hover:bg-gray-50'
                            ]"
                        >
                            <!-- Company Logo/Icon -->
                            <div class="flex h-8 w-8 items-center justify-center rounded bg-blue-100">
                                <Building2 class="h-4 w-4 text-blue-600" />
                            </div>
                            
                            <!-- Company Info -->
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium truncate">{{ company.name }}</span>
                                    <Star 
                                        v-if="company.is_default" 
                                        class="h-3 w-3 text-yellow-500 fill-current flex-shrink-0" 
                                    />
                                </div>
                            </div>
                            
                            <!-- Current Indicator -->
                            <div
                                v-if="company.id === props.currentCompany?.id"
                                class="h-2 w-2 rounded-full bg-blue-600 flex-shrink-0"
                            />
                        </button>
                    </div>
                </div>
                
                <!-- Show current company info if only one company -->
                <div v-else class="mb-2">
                    <div class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                        Current Company
                    </div>
                    <div class="flex items-center gap-3 rounded-md px-3 py-2 bg-blue-50">
                        <div class="flex h-8 w-8 items-center justify-center rounded bg-blue-100">
                            <Building2 class="h-4 w-4 text-blue-600" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="font-medium text-blue-700 truncate">{{ props.currentCompany?.name }}</span>
                                <Star 
                                    v-if="props.currentCompany?.is_default" 
                                    class="h-3 w-3 text-yellow-500 fill-current flex-shrink-0" 
                                />
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Manage Companies Link -->
                <div class="mt-3 border-t border-gray-100 pt-2">
                    <a
                        :href="companySettings.index().url"
                        class="flex w-full items-center gap-3 rounded-md px-3 py-2 text-sm text-gray-600 hover:bg-gray-50"
                    >
                        <Building2 class="h-4 w-4" />
                        <span>Manage Companies</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.company-switcher {
    position: relative;
}
</style>
