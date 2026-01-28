<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import Footer from '@/components/Footer.vue';
import { home } from '@/routes';
import { Link } from '@inertiajs/vue3';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-blue-50 via-white to-blue-50">
        <!-- Background pattern -->
        <div class="absolute inset-0 opacity-40">
            <div class="h-full w-full" style="background-image: url('data:image/svg+xml,%3Csvg width=&quot;60&quot; height=&quot;60&quot; viewBox=&quot;0 0 60 60&quot; xmlns=&quot;http://www.w3.org/2000/svg&quot;%3E%3Cg fill=&quot;none&quot; fill-rule=&quot;evenodd&quot;%3E%3Cg fill=&quot;%23f0f9ff&quot; fill-opacity=&quot;0.3&quot;%3E%3Ccircle cx=&quot;30&quot; cy=&quot;30&quot; r=&quot;1&quot;/%3E%3C/g%3E%3C/g%3E%3C/svg%3E');"></div>
        </div>
        
        <div class="relative flex min-h-screen flex-col">
            <div class="flex flex-1 items-center justify-center p-6 md:p-10">
                <div class="w-full max-w-md">
                    <!-- Logo and branding -->
                    <div class="mb-8 text-center">
                        <Link
                            :href="home()"
                            class="inline-flex flex-col items-center gap-3"
                        >
                            <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-600 to-blue-700 shadow-lg">
                                <AppLogoIcon
                                    class="h-8 w-8 text-white"
                                />
                            </div>
                            <div class="space-y-1">
                                <h1 class="text-2xl font-bold text-gray-900">JobCardOnline</h1>
                                <p class="text-sm text-gray-600">by InTouch Software</p>
                            </div>
                        </Link>
                    </div>

                    <!-- Login form card -->
                    <div class="rounded-2xl bg-white/80 backdrop-blur-sm shadow-xl border border-white/20 p-8">
                        <div class="mb-6 text-center">
                            <h2 class="text-2xl font-bold text-gray-900">{{ title }}</h2>
                            <p class="mt-2 text-sm text-gray-600">
                                {{ description }}
                            </p>
                        </div>
                        
                        <slot />
                    </div>

                    <!-- Footer info -->
                    <div class="mt-8 text-center">
                        <p class="text-xs text-gray-500">
                            © {{ new Date().getFullYear() }} Nexora Software (Pty) Ltd. All rights reserved.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
