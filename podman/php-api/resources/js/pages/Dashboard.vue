<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Boxes, Briefcase, CalendarClock, CalendarRange, CalendarX, Clock, History, Images, MessageCircle, Newspaper, Star, UserCog, Users } from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from '@/lib/i18n';
import '../../css/reserva/dashboard.css';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Tauler', href: '/dashboard' }],
    },
});

const { t } = useI18n();
const page = usePage();

// Pantalles accessibles (compartides pel servidor) i si l'usuari és admin.
const screens = computed(() => (page.props.screens as string[] | undefined) ?? []);
const isAdmin = computed(() => page.props.auth?.user?.role === 'admin');
// Missatges de xat sense llegir, per mostrar un punt a la targeta de Xat.
const unreadChat = computed(() => (page.props.unreadChat as number | undefined) ?? 0);

// `screen` indica quina pantalla cal tenir; sense `screen` és visible per a tothom.
// `adminOnly` només per a admins.
const allItems = [
    { key: 'nav.reservar', desc: 'dash.reservarD', href: '/reservar', icon: CalendarClock },
    { key: 'nav.hores', desc: 'dash.horesD', href: '/admin/horas', icon: Clock, screen: 'hores' },
    { key: 'nav.horaris', desc: 'dash.horarisD', href: '/admin/informacio', icon: CalendarRange, screen: 'informacio' },
    { key: 'nav.serveis', desc: 'dash.serveisD', href: '/admin/serveis', icon: Briefcase, screen: 'serveis' },
    { key: 'nav.stock', desc: 'dash.stockD', href: '/admin/stock', icon: Boxes, screen: 'stock' },
    { key: 'nav.empleats', desc: 'dash.empleatsD', href: '/admin/empleats', icon: Users, screen: 'empleats' },
    { key: 'nav.posts', desc: 'dash.postsD', href: '/admin/posts', icon: Newspaper, screen: 'posts' },
    { key: 'nav.imatges', desc: 'dash.imatgesD', href: '/admin/imatges', icon: Images, screen: 'imatges' },
    { key: 'nav.historial', desc: 'dash.historialD', href: '/admin/reserves', icon: History, screen: 'reserves' },
    { key: 'nav.reviews', desc: 'dash.reviewsD', href: '/reserves-admin', icon: Star, screen: 'reviews' },
    { key: 'nav.cancellacions', desc: 'dash.cancellacionsD', href: '/admin/cancellacions', icon: CalendarX, screen: 'cancellacions' },
    { key: 'nav.xat', desc: 'dash.xatD', href: '/admin/xat', icon: MessageCircle, screen: 'xat' },
    { key: 'nav.usuaris', desc: 'dash.usuarisD', href: '/admin/usuaris', icon: UserCog, adminOnly: true },
];

const items = computed(() =>
    allItems.filter((item) => {
        if (item.adminOnly) return isAdmin.value;
        if (!item.screen) return true;
        return isAdmin.value || screens.value.includes(item.screen);
    }),
);
</script>

<template>
    <Head title="Tauler" />

    <div id="rsv-dash">
        <nav>
            <Link v-for="item in items" :key="item.href" :href="item.href" :class="{ 'has-dot': item.href === '/admin/xat' && unreadChat > 0 }">
                <span v-if="item.href === '/admin/xat' && unreadChat > 0" class="rsv-dash-dot">{{ unreadChat }}</span>
                <component :is="item.icon" />
                <span>{{ t(item.key) }}</span>
                <small>{{ t(item.desc) }}</small>
            </Link>
        </nav>
    </div>
</template>
