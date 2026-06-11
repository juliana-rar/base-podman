<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import Calendar from '@/components/Calendar.vue';
import ClockPicker from '@/components/ClockPicker.vue';
import '../../../css/reserva/admin.css';

interface Slot {
    id: number;
    starts_at: string;
    notes: string | null;
    reservation: {
        id: number;
        note: string | null;
        service: { id: number; name: string } | null;
        user: { id: number; name: string; email: string };
    } | null;
}

const props = defineProps<{
    slots: Slot[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Gestió d\'hores', href: '/admin/horas' }],
    },
});

const pad = (n: number) => String(n).padStart(2, '0');

function dayKeyOf(iso: string): string {
    const d = new Date(iso);
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

const now = new Date();
const today = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;

const date = ref(today);
const time = ref('');

const existingDayKeys = computed(() => [
    ...new Set(props.slots.map((slot) => dayKeyOf(slot.starts_at))),
]);

const dateLabel = computed(() =>
    new Date(date.value + 'T00:00:00').toLocaleDateString('ca-ES', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
    }),
);

const form = useForm({ starts_at: '', notes: '' });
const canSubmit = computed(() => date.value !== '' && time.value !== '');

function dayHeading(iso: string): string {
    return new Date(iso).toLocaleDateString('ca-ES', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
    });
}

function timeLabel(iso: string): string {
    const d = new Date(iso);
    return `${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

const filter = ref<'all' | 'free' | 'booked'>('all');

// Filtre per dia: es para quan es tria un dia al calendari; buit = tots els dies.
const dayFilter = ref('');
watch(date, (day) => {
    dayFilter.value = day;
});

const dayFilterLabel = computed(() =>
    dayFilter.value
        ? new Date(dayFilter.value + 'T00:00:00').toLocaleDateString('ca-ES', {
              weekday: 'long',
              day: 'numeric',
              month: 'long',
          })
        : '',
);

function clearDayFilter(): void {
    dayFilter.value = '';
}

// Franges dins del dia triat (o totes), per al resum.
const dayScopedSlots = computed(() =>
    dayFilter.value
        ? props.slots.filter((s) => dayKeyOf(s.starts_at) === dayFilter.value)
        : props.slots,
);

const freeCount = computed(() => dayScopedSlots.value.filter((s) => !s.reservation).length);
const bookedCount = computed(() => dayScopedSlots.value.filter((s) => s.reservation).length);

const visibleSlots = computed(() =>
    dayScopedSlots.value.filter((s) => {
        if (filter.value === 'free') {
            return !s.reservation;
        }
        if (filter.value === 'booked') {
            return !!s.reservation;
        }
        return true;
    }),
);

// Agrupem les franges visibles per dia (ja venen ordenades per data i hora).
const groupedSlots = computed(() => {
    const groups = new Map<string, { key: string; label: string; slots: Slot[] }>();
    for (const slot of visibleSlots.value) {
        const key = dayKeyOf(slot.starts_at);
        if (!groups.has(key)) {
            groups.set(key, { key, label: dayHeading(slot.starts_at), slots: [] });
        }
        groups.get(key)!.slots.push(slot);
    }
    return [...groups.values()];
});

function submit(): void {
    form.starts_at = `${date.value}T${time.value}`;
    form.post('/admin/horas', {
        preserveScroll: true,
        onSuccess: () => {
            time.value = '';
            form.reset('notes');
        },
    });
}

function remove(id: number): void {
    router.delete(`/admin/horas/${id}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Gestió d'hores" />

    <div id="rsv-hours">
        <header>
            <h1>Gestió d'hores</h1>
            <p>Tria un dia al calendari i una hora al rellotge per obrir una nova franja reservable.</p>
        </header>

        <form @submit.prevent="submit">
            <Calendar v-model="date" :highlight-dates="existingDayKeys" :min-date="today" />
            <ClockPicker v-model="time" />
            <div>
                <h2>Nova franja</h2>

                <p class="rsv-chosen">{{ dateLabel }}<span v-if="time"> · {{ time }}</span></p>

                <label for="time">Hora</label>
                <input id="time" v-model="time" type="time" step="900" required />

                <label for="notes">Nota (opcional)</label>
                <input id="notes" v-model="form.notes" type="text" maxlength="255" placeholder="Ex: Visita inicial" />

                <p v-if="form.errors.starts_at" class="rsv-error">{{ form.errors.starts_at }}</p>

                <button type="submit" :disabled="!canSubmit || form.processing">Afegir franja</button>
            </div>
        </form>

        <section>
            <div class="rsv-slot-head">
                <h2>Franges creades</h2>
                <div class="rsv-slot-summary">
                    <span class="free">{{ freeCount }} lliures</span>
                    <span class="booked">{{ bookedCount }} reservades</span>
                </div>
                <button v-if="dayFilter" type="button" class="rsv-dayfilter" @click="clearDayFilter">
                    📅 {{ dayFilterLabel }} <span>✕</span>
                </button>
                <div class="rsv-slot-filter">
                    <button type="button" :class="{ 'is-active': filter === 'all' }" @click="filter = 'all'">Totes</button>
                    <button type="button" :class="{ 'is-active': filter === 'free' }" @click="filter = 'free'">Lliures</button>
                    <button type="button" :class="{ 'is-active': filter === 'booked' }" @click="filter = 'booked'">Reservades</button>
                </div>
            </div>
            <div v-if="groupedSlots.length">
                <div v-for="group in groupedSlots" :key="group.key">
                    <h3 class="rsv-day-label">
                        {{ group.label }}
                        <span>{{ group.slots.length }} {{ group.slots.length === 1 ? 'franja' : 'franges' }}</span>
                    </h3>
                    <div class="rsv-slot-grid">
                        <div
                            v-for="slot in group.slots"
                            :key="slot.id"
                            class="rsv-slot"
                            :class="slot.reservation ? 'is-booked' : 'is-free'"
                        >
                            <div class="rsv-slot-top">
                                <span class="rsv-slot-time">{{ timeLabel(slot.starts_at) }}</span>
                                <button type="button" class="rsv-del-x" aria-label="Eliminar" @click="remove(slot.id)">×</button>
                            </div>
                            <span class="rsv-badge" :class="slot.reservation ? 'booked' : 'free'">
                                {{ slot.reservation ? 'Reservada' : 'Lliure' }}
                            </span>
                            <span v-if="slot.reservation" class="rsv-slot-user">👤 {{ slot.reservation.user.name }}</span>
                            <span v-if="slot.reservation?.service" class="rsv-slot-note">🔖 {{ slot.reservation.service.name }}</span>
                            <span v-if="slot.reservation?.note" class="rsv-slot-note">💬 {{ slot.reservation.note }}</span>
                            <span v-if="slot.notes" class="rsv-slot-note">📝 {{ slot.notes }}</span>
                        </div>
                    </div>
                </div>
            </div>
            <div v-else class="rsv-empty">
                {{ slots.length ? 'Cap franja en aquesta vista.' : 'Encara no has creat cap franja.' }}
            </div>
        </section>
    </div>
</template>
