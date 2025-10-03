<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
}

defineProps<Props>();
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 48 48"
        :class="className"
        v-bind="$attrs"
    >
        <!-- JobCardOnline Logo -->
        <!-- Background circle -->
        <circle cx="24" cy="24" r="22" fill="url(#gradient)" stroke="currentColor" stroke-width="2"/>
        
        <!-- Card/Job icon -->
        <rect x="12" y="16" width="24" height="16" rx="2" fill="currentColor" opacity="0.9"/>
        <rect x="14" y="18" width="20" height="2" fill="white" opacity="0.8"/>
        <rect x="14" y="22" width="16" height="1" fill="white" opacity="0.6"/>
        <rect x="14" y="25" width="12" height="1" fill="white" opacity="0.6"/>
        
        <!-- Online indicator -->
        <circle cx="32" cy="20" r="3" fill="#10b981"/>
        <circle cx="32" cy="20" r="1.5" fill="white"/>
        
        <!-- Gradient definition -->
        <defs>
            <linearGradient id="gradient" x1="0%" y1="0%" x2="100%" y2="100%">
                <stop offset="0%" style="stop-color:#3b82f6;stop-opacity:1" />
                <stop offset="100%" style="stop-color:#1d4ed8;stop-opacity:1" />
            </linearGradient>
        </defs>
    </svg>
</template>
