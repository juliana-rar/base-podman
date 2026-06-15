<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Briefcase, CalendarClock, CalendarRange, CalendarX, Clock, History, Images, Newspaper, Star, Tags, User, Users } from '@lucide/vue';
import { useI18n } from '@/lib/i18n';
import '../../css/reserva/dashboard.css';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Tauler', href: '/dashboard' }],
    },
});

const { t } = useI18n();

const items = [
    { key: 'nav.reservar', desc: 'dash.reservarD', href: '/reservar', icon: CalendarClock },
    { key: 'nav.hores', desc: 'dash.horesD', href: '/admin/horas', icon: Clock },
    { key: 'nav.horaris', desc: 'dash.horarisD', href: '/admin/informacio', icon: CalendarRange },
    { key: 'nav.serveis', desc: 'dash.serveisD', href: '/admin/serveis', icon: Briefcase },
    { key: 'nav.empleats', desc: 'dash.empleatsD', href: '/admin/empleats', icon: Users },
    { key: 'nav.posts', desc: 'dash.postsD', href: '/admin/posts', icon: Newspaper },
    { key: 'nav.etiquetes', desc: 'dash.etiquetesD', href: '/admin/etiquetes', icon: Tags },
    { key: 'nav.imatges', desc: 'dash.imatgesD', href: '/admin/imatges', icon: Images },
    { key: 'nav.historial', desc: 'dash.historialD', href: '/admin/reserves', icon: History },
    { key: 'nav.reviews', desc: 'dash.reviewsD', href: '/reserves-admin', icon: Star },
    { key: 'nav.cancellacions', desc: 'dash.cancellacionsD', href: '/admin/cancellacions', icon: CalendarX },
    { key: 'nav.perfil', desc: 'dash.perfilD', href: '/settings/profile', icon: User },
];
</script>

<template>
    <Head title="Tauler" />

    <div id="rsv-dash">
        <nav>
            <Link v-for="item in items" :key="item.href" :href="item.href">
                <component :is="item.icon" />
                <span>{{ t(item.key) }}</span>
                <small>{{ t(item.desc) }}</small>
            </Link>
        </nav>
    </div>
</template>
