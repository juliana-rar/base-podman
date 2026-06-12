<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { useI18n } from '@/lib/i18n';
import '../../../css/reserva/admin.css';

interface Hour {
    weekday: number;
    closed: boolean;
    opens: string | null;
    closes: string | null;
}

const props = defineProps<{
    hours: Hour[];
    address: string | null;
    email: string | null;
    phone: string | null;
    instagram: string | null;
    facebook: string | null;
    linkedin: string | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Informació', href: '/admin/informacio' }],
    },
});

const { localeTag } = useI18n();

function dayName(weekday: number): string {
    // 2024-01-01 va ser dilluns; sumem el dia per obtenir el nom localitzat.
    return new Date(2024, 0, 1 + weekday).toLocaleDateString(localeTag(), { weekday: 'long' });
}

const form = useForm<{
    hours: { weekday: number; closed: boolean; opens: string; closes: string }[];
    address: string;
    email: string;
    phone: string;
    instagram: string;
    facebook: string;
    linkedin: string;
}>({
    hours: props.hours.map((h) => ({
        weekday: h.weekday,
        closed: h.closed,
        opens: (h.opens ?? '09:00').slice(0, 5),
        closes: (h.closes ?? '20:00').slice(0, 5),
    })),
    address: props.address ?? '',
    email: props.email ?? '',
    phone: props.phone ?? '',
    instagram: props.instagram ?? '',
    facebook: props.facebook ?? '',
    linkedin: props.linkedin ?? '',
});

function submit(): void {
    form.put('/admin/informacio', { preserveScroll: true });
}
</script>

<template>
    <Head title="Informació" />

    <div id="rsv-horaris">
        <header>
            <h1>Informació</h1>
            <p>Defineix l'horari, l'adreça i les dades de contacte que apareixen al footer de la web.</p>
        </header>

        <form @submit.prevent="submit">
            <h2 class="rsv-hr-section">Horari d'atenció</h2>
            <div class="rsv-hr-rows">
                <div v-for="h in form.hours" :key="h.weekday" class="rsv-hr-row" :class="{ 'is-closed': h.closed }">
                    <span class="rsv-hr-day">{{ dayName(h.weekday) }}</span>
                    <label class="rsv-hr-toggle">
                        <input v-model="h.closed" type="checkbox" />
                        <span>{{ h.closed ? 'Tancat' : 'Obert' }}</span>
                    </label>
                    <div class="rsv-hr-times">
                        <input v-model="h.opens" type="time" step="900" :disabled="h.closed" />
                        <span>–</span>
                        <input v-model="h.closes" type="time" step="900" :disabled="h.closed" />
                    </div>
                </div>
            </div>

            <h2 class="rsv-hr-section">Adreça i contacte</h2>
            <div class="rsv-hr-fields">
                <label for="address">Adreça (apareix al footer amb un mapa)</label>
                <input id="address" v-model="form.address" type="text" maxlength="255" placeholder="Ex: Carrer Major 12, Barcelona" />

                <label for="email">Correu electrònic</label>
                <input id="email" v-model="form.email" type="email" maxlength="255" placeholder="info@exemple.com" />
                <p v-if="form.errors.email" class="rsv-error">{{ form.errors.email }}</p>

                <label for="phone">Telèfon</label>
                <input id="phone" v-model="form.phone" type="text" maxlength="50" placeholder="+34 600 000 000" />

                <label for="instagram">Instagram (enllaç)</label>
                <input id="instagram" v-model="form.instagram" type="text" maxlength="255" placeholder="https://instagram.com/el_teu_compte" />

                <label for="facebook">Facebook (enllaç)</label>
                <input id="facebook" v-model="form.facebook" type="text" maxlength="255" placeholder="https://facebook.com/la_teva_pagina" />

                <label for="linkedin">LinkedIn (enllaç)</label>
                <input id="linkedin" v-model="form.linkedin" type="text" maxlength="255" placeholder="https://linkedin.com/company/..." />
            </div>

            <button type="submit" :disabled="form.processing">Desar</button>
        </form>
    </div>
</template>
