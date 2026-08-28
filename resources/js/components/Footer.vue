<template>
    <footer class="border-t bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60">
        <div class="container mx-auto px-4 py-6">
            <div class="flex flex-col items-center justify-center space-y-2 text-center text-sm text-muted-foreground">
                <p>&copy; {{ currentYear }} Revamp© Flow. All rights reserved.</p>
                <p>Version {{ $page.props.app?.version ?? 'unknown' }}</p>
            </div>
        </div>
    </footer>
</template>

<script setup lang="ts">
import { computed } from 'vue';

const currentYear = computed(() => new Date().getFullYear());
</script>
