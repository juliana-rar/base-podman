<script setup lang="ts">
import AppFooter from '@/components/AppFooter.vue';
import AppNavbar from '@/components/AppNavbar.vue';
import { Toaster } from '@/components/ui/sonner';
import '../../css/reserva/navbar.css';

// Rep breadcrumbs com l'antic layout, encara que el navbar no els mostri.
defineProps<{ breadcrumbs?: unknown[] }>();
</script>

<template>
    <div class="rsv-shell">
        <AppNavbar />

        <main>
            <slot />
        </main>

        <AppFooter />

        <Toaster />
    </div>
</template>
