<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import Calendar from '@/components/Calendar.vue';
import { useI18n } from '@/lib/i18n';
import '../../../css/reserva/admin.css';

const { t, localeTag } = useI18n();

interface Reservation {
    id: number;
    note: string | null;
    created_at: string;
    user: { id: number; name: string; email: string; phone: string | null } | null;
    slot: { id: number; starts_at: string; notes: string | null } | null;
    service: { id: number; name: string } | null;
}

const props = defineProps<{
    reservations: Reservation[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Historial', href: '/admin/reserves' }],
    },
});

const pad = (n: number) => String(n).padStart(2, '0');

function dayKeyOf(iso: string): string {
    const d = new Date(iso);
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

// Format de data fix: 15/06/2026. 01:17
function dateLabel(iso: string): string {
    const d = new Date(iso);
    return `${pad(d.getDate())}/${pad(d.getMonth() + 1)}/${d.getFullYear()}. ${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

const search = ref('');
const selectedDate = ref('');

// Dies (de creació de la reserva) que tenen alguna reserva, per al calendari.
const reservationDayKeys = computed(() => [
    ...new Set(props.reservations.map((r) => dayKeyOf(r.created_at))),
]);

const filtered = computed(() => {
    const q = search.value.trim().toLowerCase();

    return props.reservations.filter((r) => {
        if (selectedDate.value && dayKeyOf(r.created_at) !== selectedDate.value) {
            return false;
        }
        if (!q) {
            return true;
        }
        const haystack = [
            r.user?.name,
            r.user?.email,
            r.user?.phone,
            dateLabel(r.created_at),
        ]
            .join(' ')
            .toLowerCase();
        return haystack.includes(q);
    });
});

const selectedDateLabel = computed(() =>
    selectedDate.value
        ? new Date(selectedDate.value + 'T00:00:00').toLocaleDateString(localeTag(), {
              day: 'numeric',
              month: 'long',
              year: 'numeric',
          })
        : '',
);

function clearDate(): void {
    selectedDate.value = '';
}

// Paginació: màxim 20 reserves per pàgina.
const PER_PAGE = 20;
const currentPage = ref(1);
const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / PER_PAGE)));
const paged = computed(() =>
    filtered.value.slice((currentPage.value - 1) * PER_PAGE, currentPage.value * PER_PAGE),
);

watch([search, selectedDate], () => {
    currentPage.value = 1;
});
watch(totalPages, () => {
    if (currentPage.value > totalPages.value) {
        currentPage.value = totalPages.value;
    }
});

function goToPage(page: number): void {
    currentPage.value = page;
}
</script>

<template>
    <Head :title="t('hist.title')" />

    <div id="rsv-history">
        <header>
            <h1>{{ t('hist.title') }}</h1>
            <p>{{ t('hist.subtitle') }}</p>
        </header>

        <section>
            <aside>
                <h2>{{ t('adm.filterByDate') }}</h2>
                <Calendar v-model="selectedDate" :highlight-dates="reservationDayKeys" />
                <button v-if="selectedDate" type="button" class="rsv-clear" @click="clearDate">
                    {{ t('adm.allDates') }}
                </button>
            </aside>

            <div>
                <div class="rsv-toolbar">
                    <input v-model="search" type="search" :placeholder="t('hist.searchPh')" />
                    <span class="rsv-count">{{ filtered.length }} {{ t('adm.of') }} {{ reservations.length }}</span>
                </div>

                <div v-if="selectedDate" class="rsv-chip">
                    {{ t('hist.madeOn') }} {{ selectedDateLabel }}
                    <button type="button" aria-label="×" @click="clearDate">×</button>
                </div>

                <div v-if="filtered.length" class="rsv-tablewrap">
                    <table>
                        <thead>
                            <tr>
                                <th class="rsv-when-cell">{{ t('hist.colMadeOn') }}</th>
                                <th>{{ t('adm.user') }}</th>
                                <th>{{ t('adm.email') }}</th>
                                <th>{{ t('adm.phone') }}</th>
                                <th>{{ t('adm.service') }}</th>
                                <th class="rsv-note-cell">{{ t('hist.colReason') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="r in paged" :key="r.id">
                                <td class="rsv-when-cell">{{ dateLabel(r.created_at) }}</td>
                                <td>{{ r.user?.name ?? t('adm.userDeleted') }}</td>
                                <td>{{ r.user?.email ?? '—' }}</td>
                                <td>{{ r.user?.phone ?? '—' }}</td>
                                <td>{{ r.service?.name ?? '—' }}</td>
                                <td class="rsv-note-cell">{{ r.note ?? '—' }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <div v-if="totalPages > 1" class="rsv-pagination">
                        <button type="button" :disabled="currentPage === 1" @click="goToPage(currentPage - 1)">‹</button>
                        <button
                            v-for="page in totalPages"
                            :key="page"
                            type="button"
                            :class="{ 'is-active': page === currentPage }"
                            @click="goToPage(page)"
                        >
                            {{ page }}
                        </button>
                        <button type="button" :disabled="currentPage === totalPages" @click="goToPage(currentPage + 1)">›</button>
                    </div>
                </div>
                <div v-else class="rsv-empty">{{ t('hist.empty') }}</div>
            </div>
        </section>
    </div>
</template>
