<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { CalendarClock, Clock, Globe, History, Home, LayoutGrid, Moon, Newspaper, Sun, Tags } from '@lucide/vue';
import { computed } from 'vue';
import { Toaster } from '@/components/ui/sonner';
import { useAppearance } from '@/composables/useAppearance';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { type Locale, useI18n } from '@/lib/i18n';
import type { NavItem } from '@/types';
import '../../css/reserva/navbar.css';

// Rep breadcrumbs com l'antic layout, encara que el navbar no els mostri.
defineProps<{ breadcrumbs?: unknown[] }>();

const page = usePage();
const user = computed(() => page.props.auth.user);
const { isCurrentUrl } = useCurrentUrl();
const { resolvedAppearance, updateAppearance } = useAppearance();
const { t, locale, setLocale, locales } = useI18n();

const items = computed<NavItem[]>(() => {
    if (user.value?.role === 'admin') {
        return [
            { title: 'nav.dashboard', href: '/dashboard', icon: LayoutGrid },
            { title: 'nav.hores', href: '/admin/horas', icon: Clock },
            { title: 'nav.posts', href: '/admin/posts', icon: Newspaper },
            { title: 'nav.etiquetes', href: '/admin/etiquetes', icon: Tags },
            { title: 'nav.historial', href: '/admin/reserves', icon: History },
        ];
    }

    return [
        { title: 'nav.inici', href: '/', icon: Home },
        { title: 'nav.reservar', href: '/reservar', icon: CalendarClock },
    ];
});

function toggleTheme(): void {
    updateAppearance(resolvedAppearance.value === 'dark' ? 'light' : 'dark');
}

function onLang(event: Event): void {
    setLocale((event.target as HTMLSelectElement).value as Locale);
}
</script>

<template>
    <div class="rsv-shell">
        <header>
            <div class="rsv-nav">
                <Link href="/">ReservaHores</Link>
                <nav>
                    <Link
                        v-for="item in items"
                        :key="item.title"
                        :href="item.href"
                        :class="{ 'is-active': isCurrentUrl(item.href) }"
                    >
                        <component :is="item.icon" />
                        <span>{{ t(item.title) }}</span>
                    </Link>
                </nav>
                <div>
                    <label class="rsv-langwrap" :title="t('lang.label')">
                        <Globe />
                        <select class="rsv-lang" :value="locale" :aria-label="t('lang.label')" @change="onLang">
                            <option v-for="l in locales" :key="l.code" :value="l.code">
                                {{ l.code.toUpperCase() }}
                            </option>
                        </select>
                    </label>
                    <button type="button" class="rsv-icon" :aria-label="t('lang.label')" @click="toggleTheme">
                        <component :is="resolvedAppearance === 'dark' ? Sun : Moon" />
                    </button>
                    <span class="rsv-user">{{ user?.name }}</span>
                    <Link class="rsv-logout" href="/logout" method="post" as="button">{{ t('nav.sortir') }}</Link>
                </div>
            </div>
        </header>

        <main>
            <slot />
        </main>

        <Toaster />
    </div>
</template>
