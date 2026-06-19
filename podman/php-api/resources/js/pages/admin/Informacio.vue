<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
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
    siteName: string | null;
    logoUrl: string | null;
    legalName: string | null;
    taxId: string | null;
    fiscalAddress: string | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Informació', href: '/admin/informacio' }],
    },
});

const { t, localeTag } = useI18n();

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
    site_name: string;
    legal_name: string;
    tax_id: string;
    fiscal_address: string;
    logo: File | null;
    removeLogo: boolean;
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
    site_name: props.siteName ?? '',
    legal_name: props.legalName ?? '',
    tax_id: props.taxId ?? '',
    fiscal_address: props.fiscalAddress ?? '',
    logo: null,
    removeLogo: false,
});

const logoPreview = ref<string | null>(null);

function onLogo(event: Event): void {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    form.logo = file;
    form.removeLogo = false;
    if (logoPreview.value) {
        URL.revokeObjectURL(logoPreview.value);
    }
    logoPreview.value = file ? URL.createObjectURL(file) : null;
}

function removeLogo(): void {
    form.logo = null;
    form.removeLogo = true;
    if (logoPreview.value) {
        URL.revokeObjectURL(logoPreview.value);
    }
    logoPreview.value = null;
}

function submit(): void {
    form
        .transform((data) => ({ ...data, _method: 'put' }))
        .post('/admin/informacio', {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                form.logo = null;
                form.removeLogo = false;
                if (logoPreview.value) {
                    URL.revokeObjectURL(logoPreview.value);
                }
                logoPreview.value = null;
            },
        });
}
</script>

<template>
    <Head :title="t('info.title')" />

    <div id="rsv-horaris">
        <header>
            <h1>{{ t('info.title') }}</h1>
            <p>{{ t('info.subtitle') }}</p>
        </header>

        <form @submit.prevent="submit">
            <h2 class="rsv-hr-section">{{ t('info.brand') }}</h2>
            <div class="rsv-hr-fields">
                <label for="site_name">{{ t('info.name') }}</label>
                <input id="site_name" v-model="form.site_name" type="text" maxlength="100" placeholder="ReservaHores" />

                <label>{{ t('info.logo') }}</label>
                <div class="rsv-logo-row">
                    <div class="rsv-logo-prev">
                        <img v-if="logoPreview" :src="logoPreview" alt="" />
                        <img v-else-if="props.logoUrl && !form.removeLogo" :src="props.logoUrl" alt="" />
                        <span v-else class="rsv-logo-none">{{ t('info.noLogo') }}</span>
                    </div>
                    <label class="rsv-file">
                        <span>{{ form.logo ? form.logo.name : t('info.chooseImg') }}</span>
                        <input type="file" accept="image/*" @change="onLogo" />
                    </label>
                    <button
                        v-if="(props.logoUrl && !form.removeLogo) || logoPreview"
                        type="button"
                        class="rsv-logo-del"
                        @click="removeLogo"
                    >
                        {{ t('info.remove') }}
                    </button>
                </div>
                <p v-if="form.errors.logo" class="rsv-error">{{ form.errors.logo }}</p>
            </div>

            <h2 class="rsv-hr-section">{{ t('info.hours') }}</h2>
            <div class="rsv-hr-rows">
                <div v-for="h in form.hours" :key="h.weekday" class="rsv-hr-row" :class="{ 'is-closed': h.closed }">
                    <span class="rsv-hr-day">{{ dayName(h.weekday) }}</span>
                    <label class="rsv-hr-toggle">
                        <input v-model="h.closed" type="checkbox" />
                        <span>{{ h.closed ? t('info.closed') : t('info.open') }}</span>
                    </label>
                    <div class="rsv-hr-times">
                        <input v-model="h.opens" type="time" step="900" :disabled="h.closed" />
                        <span>–</span>
                        <input v-model="h.closes" type="time" step="900" :disabled="h.closed" />
                    </div>
                </div>
            </div>

            <h2 class="rsv-hr-section">{{ t('info.addrContact') }}</h2>
            <div class="rsv-hr-fields">
                <label for="address">{{ t('info.address') }}</label>
                <input id="address" v-model="form.address" type="text" maxlength="255" :placeholder="t('info.addressPh')" />

                <label for="email">{{ t('info.email') }}</label>
                <input id="email" v-model="form.email" type="email" maxlength="255" placeholder="info@exemple.com" />
                <p v-if="form.errors.email" class="rsv-error">{{ form.errors.email }}</p>

                <label for="phone">{{ t('info.phone') }}</label>
                <input id="phone" v-model="form.phone" type="text" maxlength="50" placeholder="+34 600 000 000" />

                <label for="instagram">{{ t('info.instagram') }}</label>
                <input id="instagram" v-model="form.instagram" type="text" maxlength="255" placeholder="https://instagram.com/..." />

                <label for="facebook">{{ t('info.facebook') }}</label>
                <input id="facebook" v-model="form.facebook" type="text" maxlength="255" placeholder="https://facebook.com/..." />

                <label for="linkedin">{{ t('info.linkedin') }}</label>
                <input id="linkedin" v-model="form.linkedin" type="text" maxlength="255" placeholder="https://linkedin.com/company/..." />
            </div>

            <h2 class="rsv-hr-section">{{ t('info.fiscal') }}</h2>
            <div class="rsv-hr-fields">
                <label for="legal_name">{{ t('info.legalName') }}</label>
                <input id="legal_name" v-model="form.legal_name" type="text" maxlength="150" placeholder="Empresa, SL" />

                <label for="tax_id">{{ t('info.taxId') }}</label>
                <input id="tax_id" v-model="form.tax_id" type="text" maxlength="50" placeholder="B12345678" />

                <label for="fiscal_address">{{ t('info.fiscalAddress') }}</label>
                <input id="fiscal_address" v-model="form.fiscal_address" type="text" maxlength="255" :placeholder="t('info.addressPh')" />
            </div>

            <button type="submit" :disabled="form.processing">{{ t('info.save') }}</button>
        </form>
    </div>
</template>
