<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();

const page = usePage();
const siteName = computed(() => (page.props.siteName as string | undefined) || 'ReservaHores');
const logoUrl = computed(() => (page.props.logoUrl as string | null | undefined) ?? null);
</script>

<template>
    <div
        class="relative flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10"
        style="
            background:
                radial-gradient(900px 480px at 85% -6%, rgba(79, 70, 229, 0.18), transparent 60%),
                radial-gradient(820px 460px at -8% 4%, rgba(168, 85, 247, 0.14), transparent 55%),
                radial-gradient(700px 700px at 50% 118%, rgba(236, 72, 153, 0.1), transparent 60%),
                radial-gradient(circle at 1px 1px, rgba(79, 70, 229, 0.15) 1.5px, transparent 1.6px) 0 0 / 26px 26px,
                linear-gradient(rgba(79, 70, 229, 0.06) 1px, transparent 1px) 0 0 / 100% 26px,
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
                            class="flex items-center gap-2 text-2xl font-extrabold tracking-tight text-[#4f46e5] dark:text-[#818cf8]"
                        >
                            <img v-if="logoUrl" :src="logoUrl" alt="" class="h-8 w-auto max-w-[10rem] object-contain" />
                            <span>{{ siteName }}</span>
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
