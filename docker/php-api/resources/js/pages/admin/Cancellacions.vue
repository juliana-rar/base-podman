<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import Calendar from '@/components/Calendar.vue';
import '../../../css/reserva/admin.css';

interface Cancellation {
    id: number;
    user: { id: number; name: string; email: string } | null;
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

function slotLabel(iso: string): string {
    return new Date(iso).toLocaleString('ca-ES', {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function madeLabel(iso: string): string {
    return new Date(iso).toLocaleString('ca-ES', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
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
        const haystack = [c.user?.name, c.user?.email, c.service_name, c.reason]
            .join(' ')
            .toLowerCase();
        return haystack.includes(q);
    });
});

const selectedDateLabel = computed(() =>
    selectedDate.value
        ? new Date(selectedDate.value + 'T00:00:00').toLocaleDateString('ca-ES', {
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
    <Head title="Cancel·lacions" />

    <div id="rsv-history">
        <header>
            <h1>Cancel·lacions</h1>
            <p>Reserves que els usuaris han cancel·lat, amb el motiu indicat.</p>
        </header>

        <section>
            <aside>
                <h2>Filtra per data</h2>
                <Calendar v-model="selectedDate" :highlight-dates="cancellationDayKeys" />
                <button v-if="selectedDate" type="button" class="rsv-clear" @click="clearDate">
                    Veure totes les dates
                </button>
            </aside>

            <div>
                <div class="rsv-toolbar">
                    <input v-model="search" type="search" placeholder="Cerca per usuari, email, servei o motiu…" />
                    <span class="rsv-count">{{ filtered.length }} de {{ cancellations.length }}</span>
                </div>

                <div v-if="selectedDate" class="rsv-chip">
                    Cancel·lades el {{ selectedDateLabel }}
                    <button type="button" aria-label="Treure filtre" @click="clearDate">×</button>
                </div>

                <div v-if="filtered.length" class="rsv-tablewrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Cita</th>
                                <th>Usuari</th>
                                <th>Email</th>
                                <th>Servei</th>
                                <th>Motiu cancel·lació</th>
                                <th>Cancel·lada el</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="c in paged" :key="c.id">
                                <td>{{ c.slot_starts_at ? slotLabel(c.slot_starts_at) : '—' }}</td>
                                <td>{{ c.user?.name ?? 'Usuari eliminat' }}</td>
                                <td>{{ c.user?.email ?? '—' }}</td>
                                <td>{{ c.service_name ?? '—' }}</td>
                                <td class="rsv-note-cell">{{ c.reason }}</td>
                                <td>{{ madeLabel(c.created_at) }}</td>
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
                <div v-else class="rsv-empty">Encara no hi ha cap cancel·lació.</div>
            </div>
        </section>
    </div>
</template>
