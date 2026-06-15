<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
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
    service: { id: number; name: string; price: string | number } | null;
    service_option: { id: number; price: string | number } | null;
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

// --- Facturació mensual ---
const now = new Date();
const billingMonth = ref(`${now.getFullYear()}-${pad(now.getMonth() + 1)}`);

// Data que compta per a la facturació: la de la cita (slot) si en té, si no la de creació.
function billingDate(r: Reservation): string {
    return r.slot?.starts_at ?? r.created_at;
}

function monthKeyOf(iso: string): string {
    const d = new Date(iso);
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}`;
}

// Import d'una reserva: el de l'opció si en té (>0), si no el del servei.
function priceOf(r: Reservation): number {
    const opt = r.service_option ? Number(r.service_option.price) : 0;
    if (opt > 0) {
        return opt;
    }
    return r.service ? Number(r.service.price) : 0;
}

const monthReservations = computed(() =>
    props.reservations.filter((r) => monthKeyOf(billingDate(r)) === billingMonth.value),
);

const billingTotal = computed(() => monthReservations.value.reduce((sum, r) => sum + priceOf(r), 0));
const billingCount = computed(() => monthReservations.value.length);
const billingAvg = computed(() => (billingCount.value ? billingTotal.value / billingCount.value : 0));

// Desglossament per servei (import i nombre de reserves).
const billingByService = computed(() => {
    const map = new Map<string, { total: number; count: number }>();
    for (const r of monthReservations.value) {
        const name = r.service?.name ?? t('bill.noService');
        const cur = map.get(name) ?? { total: 0, count: 0 };
        cur.total += priceOf(r);
        cur.count += 1;
        map.set(name, cur);
    }
    return [...map.entries()]
        .map(([name, v]) => ({ name, ...v }))
        .sort((a, b) => b.total - a.total);
});
const billingMax = computed(() => Math.max(1, ...billingByService.value.map((s) => s.total)));

const currencyFmt = computed(() =>
    new Intl.NumberFormat(localeTag(), { style: 'currency', currency: 'EUR' }),
);
function money(n: number): string {
    return currencyFmt.value.format(n);
}

const billingMonthLabel = computed(() =>
    new Date(billingMonth.value + '-01T00:00:00').toLocaleDateString(localeTag(), {
        month: 'long',
        year: 'numeric',
    }),
);

// --- Selector de mes modern (popover amb graella de mesos) ---
const pickerOpen = ref(false);
const monthPickEl = ref<HTMLElement | null>(null);
const selectedYear = computed(() => Number(billingMonth.value.slice(0, 4)));
const selectedMonthIdx = computed(() => Number(billingMonth.value.slice(5, 7)) - 1);
const pickerYear = ref(selectedYear.value);

const monthNames = computed(() =>
    Array.from({ length: 12 }, (_, i) =>
        new Date(2024, i, 1).toLocaleDateString(localeTag(), { month: 'short' }),
    ),
);

function togglePicker(): void {
    if (!pickerOpen.value) {
        pickerYear.value = selectedYear.value;
    }
    pickerOpen.value = !pickerOpen.value;
}

function selectMonth(i: number): void {
    billingMonth.value = `${pickerYear.value}-${pad(i + 1)}`;
    pickerOpen.value = false;
}

function shiftMonth(delta: number): void {
    const d = new Date(selectedYear.value, selectedMonthIdx.value + delta, 1);
    billingMonth.value = `${d.getFullYear()}-${pad(d.getMonth() + 1)}`;
}

function isSelectedMonth(i: number): boolean {
    return pickerYear.value === selectedYear.value && i === selectedMonthIdx.value;
}

function onDocClick(event: MouseEvent): void {
    if (pickerOpen.value && monthPickEl.value && !monthPickEl.value.contains(event.target as Node)) {
        pickerOpen.value = false;
    }
}

onMounted(() => document.addEventListener('click', onDocClick));
onBeforeUnmount(() => document.removeEventListener('click', onDocClick));

// --- Exportació CSV de la facturació del mes ---
// Separador ';' perquè Excel (ES/CA) reparteixi bé les columnes, amb BOM UTF-8.
const CSV_SEP = ';';

function csvCell(value: string): string {
    return /[";\n]/.test(value) ? `"${value.replace(/"/g, '""')}"` : value;
}

function csvRow(cells: string[]): string {
    return cells.map(csvCell).join(CSV_SEP);
}

function exportBillingCsv(): void {
    const rows = [...monthReservations.value].sort((a, b) =>
        billingDate(a).localeCompare(billingDate(b)),
    );

    const lines: string[] = [];

    // Capçalera del report + resum.
    lines.push(csvRow([t('bill.title')]));
    lines.push(csvRow([t('bill.month'), billingMonthLabel.value]));
    lines.push(csvRow([t('bill.total'), money(billingTotal.value)]));
    lines.push(csvRow([t('bill.count'), String(billingCount.value)]));
    lines.push(csvRow([t('bill.avg'), money(billingAvg.value)]));
    lines.push('');

    // Taula de detall.
    lines.push(csvRow([t('bill.csvDate'), t('bill.csvClient'), t('bill.csvEmail'), t('bill.csvService'), t('bill.csvAmount')]));
    for (const r of rows) {
        lines.push(
            csvRow([
                dateLabel(billingDate(r)),
                r.user?.name ?? '',
                r.user?.email ?? '',
                r.service?.name ?? '',
                money(priceOf(r)),
            ]),
        );
    }
    lines.push('');
    lines.push(csvRow(['', '', '', t('bill.total'), money(billingTotal.value)]));

    const blob = new Blob(['﻿' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `facturacio-${billingMonth.value}.csv`;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
}
</script>

<template>
    <Head :title="t('hist.title')" />

    <div id="rsv-history">
        <header>
            <h1>{{ t('hist.title') }}</h1>
            <p>{{ t('hist.subtitle') }}</p>
        </header>

        <section class="rsv-billing">
            <div class="rsv-billing-head">
                <h2>{{ t('bill.title') }}</h2>
                <div class="rsv-billing-controls">
                    <div ref="monthPickEl" class="rsv-monthpick">
                        <button type="button" class="rsv-month-arrow" aria-label="‹" @click="shiftMonth(-1)">‹</button>
                        <button type="button" class="rsv-month-trigger" :aria-expanded="pickerOpen" @click="togglePicker">
                            {{ billingMonthLabel }}
                        </button>
                        <button type="button" class="rsv-month-arrow" aria-label="›" @click="shiftMonth(1)">›</button>

                        <div v-if="pickerOpen" class="rsv-month-pop">
                            <div class="rsv-month-pop-head">
                                <button type="button" aria-label="‹" @click="pickerYear--">‹</button>
                                <span>{{ pickerYear }}</span>
                                <button type="button" aria-label="›" @click="pickerYear++">›</button>
                            </div>
                            <div class="rsv-month-grid">
                                <button
                                    v-for="(name, i) in monthNames"
                                    :key="i"
                                    type="button"
                                    :class="{ on: isSelectedMonth(i) }"
                                    @click="selectMonth(i)"
                                >{{ name }}</button>
                            </div>
                        </div>
                    </div>

                    <button type="button" class="rsv-billing-export" :disabled="!billingCount" @click="exportBillingCsv">
                        {{ t('bill.export') }}
                    </button>
                </div>
            </div>

            <div class="rsv-billing-stats">
                <div class="rsv-bill-stat is-primary">
                    <span class="rsv-bill-num">{{ money(billingTotal) }}</span>
                    <span class="rsv-bill-label">{{ t('bill.total') }}</span>
                </div>
                <div class="rsv-bill-stat">
                    <span class="rsv-bill-num">{{ billingCount }}</span>
                    <span class="rsv-bill-label">{{ t('bill.count') }}</span>
                </div>
                <div class="rsv-bill-stat">
                    <span class="rsv-bill-num">{{ money(billingAvg) }}</span>
                    <span class="rsv-bill-label">{{ t('bill.avg') }}</span>
                </div>
            </div>

            <div v-if="billingByService.length" class="rsv-bill-bars">
                <h3>{{ t('bill.byService') }}</h3>
                <div v-for="s in billingByService" :key="s.name" class="rsv-bill-bar">
                    <span class="rsv-bill-bar-label">{{ s.name }}</span>
                    <div class="rsv-bill-bar-track">
                        <div class="rsv-bill-bar-fill" :style="{ width: Math.round((s.total / billingMax) * 100) + '%' }"></div>
                    </div>
                    <span class="rsv-bill-bar-val">{{ money(s.total) }} · {{ s.count }}</span>
                </div>
            </div>
            <p v-else class="rsv-bill-empty">{{ t('bill.empty') }} {{ billingMonthLabel }}.</p>
        </section>

        <section class="rsv-history-grid">
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
