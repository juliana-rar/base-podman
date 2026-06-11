<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Briefcase, CalendarClock, Clock, History, Home, Images, Newspaper, Tags } from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from '@/lib/i18n';
import '../../css/reserva/dashboard.css';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Tauler', href: '/dashboard' }],
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
const { t } = useI18n();

const items = [
    { key: 'nav.reservar', desc: 'dash.reservarD', href: '/reservar', icon: CalendarClock },
    { key: 'nav.hores', desc: 'dash.horesD', href: '/admin/horas', icon: Clock },
    { key: 'nav.serveis', desc: 'dash.serveisD', href: '/admin/serveis', icon: Briefcase },
    { key: 'nav.posts', desc: 'dash.postsD', href: '/admin/posts', icon: Newspaper },
    { key: 'nav.etiquetes', desc: 'dash.etiquetesD', href: '/admin/etiquetes', icon: Tags },
    { key: 'nav.imatges', desc: 'dash.imatgesD', href: '/admin/imatges', icon: Images },
    { key: 'nav.historial', desc: 'dash.historialD', href: '/admin/reserves', icon: History },
    { key: 'nav.inici', desc: 'dash.iniciD', href: '/', icon: Home },
];
</script>

<template>
    <Head title="Tauler" />

    <div id="rsv-dash">
        <header>
            <h1>{{ t('dash.hello') }}, {{ user?.name }} 👋</h1>
            <p>{{ t('dash.where') }}</p>
        </header>

        <nav>
            <Link v-for="item in items" :key="item.href" :href="item.href">
                <component :is="item.icon" />
                <span>{{ t(item.key) }}</span>
                <small>{{ t(item.desc) }}</small>
            </Link>
        </nav>
    </div>
</template>
