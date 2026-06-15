<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    CalendarCheck,
    CalendarClock,
    ChevronDown,
    Clock,
    Globe,
    Home,
    LayoutGrid,
    Moon,
    Sun,
    User,
} from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useAppearance } from '@/composables/useAppearance';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { type Locale, useI18n } from '@/lib/i18n';
import type { NavItem } from '@/types';

const page = usePage();
const user = computed(() => page.props.auth?.user ?? null);
const siteName = computed(() => (page.props.siteName as string | undefined) || 'ReservaHores');
const logoUrl = computed(() => (page.props.logoUrl as string | null | undefined) ?? null);
const { isCurrentUrl } = useCurrentUrl();
const { resolvedAppearance, updateAppearance } = useAppearance();
const { t, locale, setLocale, locales } = useI18n();

const items = computed<NavItem[]>(() => {
    if (user.value?.role === 'admin') {
        return [
            { title: 'nav.dashboard', href: '/dashboard', icon: LayoutGrid },
            { title: 'nav.hores', href: '/admin/horas', icon: Clock },
            { title: 'nav.perfil', href: '/settings/profile', icon: User },
        ];
    }

    const base: NavItem[] = [
        { title: 'nav.inici', href: '/', icon: Home },
        { title: 'nav.reservar', href: '/reservar', icon: CalendarClock },
    ];

    // Els usuaris amb sessió poden veure les seves reserves fetes i el seu perfil.
    if (user.value) {
        base.push({ title: 'nav.reserves', href: '/reserves', icon: CalendarCheck });
        base.push({ title: 'nav.perfil', href: '/settings/profile', icon: User });
    }

    return base;
});

function toggleTheme(): void {
    updateAppearance(resolvedAppearance.value === 'dark' ? 'light' : 'dark');
}

// --- Desplegable d'idioma personalitzat ---
const langOpen = ref(false);
const langRef = ref<HTMLElement | null>(null);

function pickLang(code: Locale): void {
    setLocale(code);
    langOpen.value = false;
}

function onDocClick(event: MouseEvent): void {
    if (langRef.value && !langRef.value.contains(event.target as Node)) {
        langOpen.value = false;
    }
}

function onEsc(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        langOpen.value = false;
    }
}

onMounted(() => {
    document.addEventListener('mousedown', onDocClick);
    document.addEventListener('keydown', onEsc);
});

onBeforeUnmount(() => {
    document.removeEventListener('mousedown', onDocClick);
    document.removeEventListener('keydown', onEsc);
});
</script>

<template>
    <header class="rsv-navbar">
        <div class="rsv-nav">
            <Link href="/" class="rsv-brand">
                <img v-if="logoUrl" :src="logoUrl" alt="" class="rsv-brand-logo" />
                <span>{{ siteName }}</span>
            </Link>
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
                <div ref="langRef" class="rsv-langdd">
                    <button
                        type="button"
                        class="rsv-langbtn"
                        :class="{ 'is-open': langOpen }"
                        :aria-label="t('lang.label')"
                        @click="langOpen = !langOpen"
                    >
                        <Globe />
                        <span>{{ locale.toUpperCase() }}</span>
                        <ChevronDown class="rsv-chev" />
                    </button>
                    <transition name="rsv-dd">
                        <ul v-if="langOpen" class="rsv-langmenu">
                            <li v-for="l in locales" :key="l.code">
                                <button
                                    type="button"
                                    :class="{ 'is-active': l.code === locale }"
                                    @click="pickLang(l.code)"
                                >
                                    <span class="rsv-lcode">{{ l.code.toUpperCase() }}</span>
                                    <span class="rsv-llabel">{{ l.label }}</span>
                                </button>
                            </li>
                        </ul>
                    </transition>
                </div>
                <button type="button" class="rsv-icon" :aria-label="t('lang.label')" @click="toggleTheme">
                    <component :is="resolvedAppearance === 'dark' ? Sun : Moon" />
                </button>
                <template v-if="user">
                    <span class="rsv-user">{{ user.name }}</span>
                    <Link class="rsv-logout" href="/logout" method="post" as="button">{{ t('nav.sortir') }}</Link>
                </template>
                <template v-else>
                    <Link class="rsv-logout" href="/login">{{ t('welcome.entrar') }}</Link>
                    <Link class="rsv-authlink" href="/register">{{ t('welcome.crear') }}</Link>
                </template>
            </div>
        </div>
    </header>
</template>
