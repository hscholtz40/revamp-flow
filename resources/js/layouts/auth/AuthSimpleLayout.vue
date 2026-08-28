<script setup lang="ts">
import { home } from '@/routes';
import { Link } from '@inertiajs/vue3';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-[#fff7ed] via-white to-[#fef3c7]">
        <!-- Background pattern -->
        <div class="absolute inset-0 opacity-40">
            <div class="h-full w-full" style="background-image: url('data:image/svg+xml,%3Csvg width=&quot;60&quot; height=&quot;60&quot; viewBox=&quot;0 0 60 60&quot; xmlns=&quot;http://www.w3.org/2000/svg&quot;%3E%3Cg fill=&quot;none&quot; fill-rule=&quot;evenodd&quot;%3E%3Cg fill=&quot;%23f7941d&quot; fill-opacity=&quot;0.08&quot;%3E%3Ccircle cx=&quot;30&quot; cy=&quot;30&quot; r=&quot;1&quot;/%3E%3C/g%3E%3C/g%3E%3C/svg%3E');"></div>
        </div>
        
        <div class="relative flex min-h-screen flex-col">
            <div class="flex flex-1 items-center justify-center p-6 md:p-10">
                <div class="w-full max-w-md">
                    <!-- Logo and branding -->
                    <div class="mb-8 text-center">
                        <Link
                            :href="home()"
                            class="inline-flex items-center justify-center"
                        >
                            <img
                                src="/revamp-logo.png"
                                alt="Revamp"
                                class="h-auto max-h-28 w-auto max-w-xs"
                            >
                        </Link>
                    </div>

                    <!-- Login form card -->
                    <div class="rounded-2xl border border-[#f7941d]/10 bg-white/85 p-8 shadow-xl shadow-[#3b2a1a]/10 backdrop-blur-sm">
                        <div class="mb-6 text-center">
                            <h2 class="text-2xl font-bold text-neutral-950">{{ title }}</h2>
                            <p class="mt-2 text-sm text-neutral-600">
                                {{ description }}
                            </p>
                        </div>
                        
                        <slot />
                    </div>

                    <!-- Footer info -->
                    <div class="mt-8 text-center">
                        <p class="text-xs text-neutral-500">
                            © {{ new Date().getFullYear() }} Revamp© Flow. All rights reserved.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
