<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="relative flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10"
        style="
            background:
                radial-gradient(1000px 520px at 90% -10%, rgba(79, 70, 229, 0.12), transparent 60%),
                radial-gradient(900px 480px at 0% 0%, rgba(79, 70, 229, 0.08), transparent 55%),
                var(--background);
        "
    >
        <div class="w-full max-w-sm">
            <div
                class="rounded-3xl border border-border/70 bg-card/80 p-8 shadow-xl backdrop-blur-sm"
            >
                <div class="flex flex-col gap-8">
                    <div class="flex flex-col items-center gap-3">
                        <Link
                            :href="home()"
                            class="text-2xl font-extrabold tracking-tight text-[#4f46e5] dark:text-[#818cf8]"
                        >
                            ReservaHores
                        </Link>
                        <div class="space-y-1.5 text-center">
                            <h1 class="text-xl font-semibold">{{ title }}</h1>
                            <p class="text-sm text-muted-foreground">
                                {{ description }}
                            </p>
                        </div>
                    </div>
                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>
