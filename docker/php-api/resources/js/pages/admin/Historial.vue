<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Calendar from '@/components/Calendar.vue';
import '../../../css/reserva/admin.css';

interface Reservation {
    id: number;
    note: string | null;
    created_at: string;
    user: { id: number; name: string; email: string } | null;
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
            r.slot ? slotLabel(r.slot.starts_at) : '',
        ]
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
</script>

<template>
    <Head title="Historial de reserves" />

    <div id="rsv-history">
        <header>
            <h1>Historial de reserves</h1>
            <p>Cerca, filtra per data i consulta totes les reserves fetes.</p>
        </header>

        <section>
            <aside>
                <h2>Filtra per data</h2>
                <Calendar v-model="selectedDate" :highlight-dates="reservationDayKeys" />
                <button v-if="selectedDate" type="button" class="rsv-clear" @click="clearDate">
                    Veure totes les dates
                </button>
            </aside>

            <div>
                <div class="rsv-toolbar">
                    <input v-model="search" type="search" placeholder="Cerca per usuari, email o hora…" />
                    <span class="rsv-count">{{ filtered.length }} de {{ reservations.length }}</span>
                </div>

                <div v-if="selectedDate" class="rsv-chip">
                    Reserves fetes el {{ selectedDateLabel }}
                    <button type="button" aria-label="Treure filtre" @click="clearDate">×</button>
                </div>

                <div v-if="filtered.length" class="rsv-tablewrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Reserva</th>
                                <th>Usuari</th>
                                <th>Email</th>
                                <th>Servei</th>
                                <th>Motiu</th>
                                <th>Feta el</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="r in filtered" :key="r.id">
                                <td>{{ r.slot ? slotLabel(r.slot.starts_at) : 'Franja eliminada' }}</td>
                                <td>{{ r.user?.name ?? 'Usuari eliminat' }}</td>
                                <td>{{ r.user?.email ?? '—' }}</td>
                                <td>{{ r.service?.name ?? '—' }}</td>
                                <td class="rsv-note-cell">{{ r.note ?? '—' }}</td>
                                <td>{{ madeLabel(r.created_at) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-else class="rsv-empty">Cap reserva coincideix amb el filtre.</div>
            </div>
        </section>
    </div>
</template>
