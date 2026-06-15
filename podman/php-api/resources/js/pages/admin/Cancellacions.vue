<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import Calendar from '@/components/Calendar.vue';
import { useI18n } from '@/lib/i18n';
import '../../../css/reserva/admin.css';

const { t, localeTag } = useI18n();

interface Cancellation {
    id: number;
    user: { id: number; name: string; email: string; phone: string | null } | null;
    service_name: string | null;
    slot_starts_at: string | null;
    note: string | null;
    reason: string;
    created_at: string;
}

const props = defineProps<{
    cancellations: Cancellation[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Cancel·lacions', href: '/admin/cancellacions' }],
    },
});

const pad = (n: number) => String(n).padStart(2, '0');

function dayKeyOf(iso: string): string {
    const d = new Date(iso);
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

// Format de data fix: 13/06/2026, 03:00
function slotLabel(iso: string): string {
    const d = new Date(iso);
    return `${pad(d.getDate())}/${pad(d.getMonth() + 1)}/${d.getFullYear()}, ${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

const search = ref('');
const selectedDate = ref('');

const cancellationDayKeys = computed(() => [
    ...new Set(props.cancellations.map((c) => dayKeyOf(c.created_at))),
]);

const filtered = computed(() => {
    const q = search.value.trim().toLowerCase();

    return props.cancellations.filter((c) => {
        if (selectedDate.value && dayKeyOf(c.created_at) !== selectedDate.value) {
            return false;
        }
        if (!q) {
            return true;
        }
        const haystack = [c.user?.name, c.user?.email, c.user?.phone, c.service_name, c.reason]
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

// Paginació: màxim 20 cancel·lacions per pàgina.
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
    <Head :title="t('can.title')" />

    <div id="rsv-history">
        <header>
            <h1>{{ t('can.title') }}</h1>
            <p>{{ t('can.subtitle') }}</p>
        </header>

        <section class="rsv-history-grid">
            <aside>
                <h2>{{ t('adm.filterByDate') }}</h2>
                <Calendar v-model="selectedDate" :highlight-dates="cancellationDayKeys" />
                <button v-if="selectedDate" type="button" class="rsv-clear" @click="clearDate">
                    {{ t('adm.allDates') }}
                </button>
            </aside>

            <div>
                <div class="rsv-toolbar">
                    <input v-model="search" type="search" :placeholder="t('can.searchPh')" />
                    <span class="rsv-count">{{ filtered.length }} {{ t('adm.of') }} {{ cancellations.length }}</span>
                </div>

                <div v-if="selectedDate" class="rsv-chip">
                    {{ t('can.cancelledOn') }} {{ selectedDateLabel }}
                    <button type="button" aria-label="×" @click="clearDate">×</button>
                </div>

                <div v-if="filtered.length" class="rsv-tablewrap">
                    <table>
                        <thead>
                            <tr>
                                <th>{{ t('can.colAppt') }}</th>
                                <th>{{ t('adm.user') }}</th>
                                <th>{{ t('adm.email') }}</th>
                                <th>{{ t('adm.phone') }}</th>
                                <th>{{ t('adm.service') }}</th>
                                <th class="rsv-note-cell">{{ t('can.colReason') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="c in paged" :key="c.id">
                                <td>{{ c.slot_starts_at ? slotLabel(c.slot_starts_at) : '—' }}</td>
                                <td>{{ c.user?.name ?? t('adm.userDeleted') }}</td>
                                <td>{{ c.user?.email ?? '—' }}</td>
                                <td>{{ c.user?.phone ?? '—' }}</td>
                                <td>{{ c.service_name ?? '—' }}</td>
                                <td class="rsv-note-cell">{{ c.reason }}</td>
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
                <div v-else class="rsv-empty">{{ t('can.empty') }}</div>
            </div>
        </section>
    </div>
</template>
