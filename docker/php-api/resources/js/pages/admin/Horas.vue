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
    statsSlots: Slot[];
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

// --- Resum de les reserves, filtrable per període (dia / setmana / mes) ---
const period = ref<'day' | 'week' | 'month'>('week');
const periodDays = computed(() => (period.value === 'day' ? 1 : period.value === 'week' ? 7 : 30));

// Finestra de dies CAP ENRERE des d'avui (històric real): de més antic a avui.
const periodDayKeys = computed(() => {
    const keys: string[] = [];
    const base = new Date(today + 'T00:00:00');
    for (let i = periodDays.value - 1; i >= 0; i--) {
        const d = new Date(base);
        d.setDate(base.getDate() - i);
        keys.push(`${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`);
    }
    return keys;
});
const periodKeySet = computed(() => new Set(periodDayKeys.value));
const periodSlots = computed(() =>
    props.statsSlots.filter((s) => periodKeySet.value.has(dayKeyOf(s.starts_at))),
);

const totalSlots = computed(() => periodSlots.value.length);
const totalBooked = computed(() => periodSlots.value.filter((s) => s.reservation).length);
const totalFree = computed(() => totalSlots.value - totalBooked.value);
const occupancy = computed(() =>
    totalSlots.value ? Math.round((totalBooked.value / totalSlots.value) * 100) : 0,
);
const todayBooked = computed(
    () => props.statsSlots.filter((s) => s.reservation && dayKeyOf(s.starts_at) === today).length,
);
const uniqueClients = computed(
    () => new Set(periodSlots.value.filter((s) => s.reservation).map((s) => s.reservation!.user.id)).size,
);
// Gràfic de línia: reserves per dia dins de la finestra (històric cap enrere).
const chartData = computed(() =>
    periodDayKeys.value.map((key) => ({
        key,
        count: periodSlots.value.filter((s) => s.reservation && dayKeyOf(s.starts_at) === key).length,
    })),
);
const chartMax = computed(() => Math.max(1, ...chartData.value.map((d) => d.count)));

// Punts (viewBox 0..100) per a la línia i l'àrea.
const linePoints = computed(() => {
    const data = chartData.value;
    const n = data.length;
    const max = chartMax.value;
    if (n === 1) {
        const y = 100 - (data[0].count / max) * 100;
        return `0,${y} 100,${y}`;
    }
    return data
        .map((d, i) => {
            const x = (i / (n - 1)) * 100;
            const y = 100 - (d.count / max) * 100;
            return `${x.toFixed(2)},${y.toFixed(2)}`;
        })
        .join(' ');
});
const areaPoints = computed(() => `${linePoints.value} 100,100 0,100`);
const lineDots = computed(() => {
    const data = chartData.value;
    const n = data.length;
    if (n > 12) {
        return [];
    }
    const max = chartMax.value;
    return data.map((d, i) => ({
        key: d.key,
        x: n === 1 ? 50 : (i / (n - 1)) * 100,
        y: 100 - (d.count / max) * 100,
        count: d.count,
    }));
});

function chartLabel(key: string): string {
    const d = new Date(key + 'T00:00:00');
    if (periodDays.value > 10) {
        return String(d.getDate());
    }
    return d.toLocaleDateString('ca-ES', { weekday: 'short', day: 'numeric' });
}

// Mini tendència: % de reserves respecte el període equivalent anterior.
const prevPeriodSlots = computed(() => {
    const base = new Date(today + 'T00:00:00');
    const keys = new Set<string>();
    for (let i = periodDays.value; i < periodDays.value * 2; i++) {
        const d = new Date(base);
        d.setDate(base.getDate() - i);
        keys.add(`${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`);
    }
    return props.statsSlots.filter((s) => s.reservation && keys.has(dayKeyOf(s.starts_at))).length;
});
const trendPct = computed(() => {
    const prev = prevPeriodSlots.value;
    const curr = totalBooked.value;
    if (prev === 0) {
        return curr > 0 ? 100 : 0;
    }
    return Math.round(((curr - prev) / prev) * 100);
});

// Reserves per servei (dins del període).
const byService = computed(() => {
    const counts = new Map<string, number>();
    for (const s of periodSlots.value) {
        const name = s.reservation?.service?.name;
        if (name) {
            counts.set(name, (counts.get(name) ?? 0) + 1);
        }
    }
    const arr = [...counts.entries()].map(([name, count]) => ({ name, count })).sort((a, b) => b.count - a.count);
    const noServ = periodSlots.value.filter((s) => s.reservation && !s.reservation.service).length;
    if (noServ) {
        arr.push({ name: 'Sense servei', count: noServ });
    }
    return arr;
});
const byServiceMax = computed(() => Math.max(1, ...byService.value.map((s) => s.count)));

// Reserves per dia de la setmana (Dl..Dg).
const weekdayNames = ['Dl', 'Dt', 'Dc', 'Dj', 'Dv', 'Ds', 'Dg'];
const byWeekday = computed(() => {
    const counts = [0, 0, 0, 0, 0, 0, 0];
    for (const s of periodSlots.value) {
        if (s.reservation) {
            const idx = (new Date(s.starts_at).getDay() + 6) % 7;
            counts[idx]++;
        }
    }
    return weekdayNames.map((name, i) => ({ name, count: counts[i] }));
});
const byWeekdayMax = computed(() => Math.max(1, ...byWeekday.value.map((w) => w.count)));

function csvCell(value: string): string {
    return /[",\n]/.test(value) ? `"${value.replace(/"/g, '""')}"` : value;
}

// Exportació detallada: una fila per reserva del període.
function exportDetailCsv(): void {
    const rows = periodSlots.value
        .filter((s) => s.reservation)
        .sort((a, b) => a.starts_at.localeCompare(b.starts_at));

    const lines: string[] = [];
    lines.push(['Data', 'Hora', 'Client', 'Email', 'Servei', 'Nota reserva', 'Nota franja'].join(','));
    for (const s of rows) {
        const d = new Date(s.starts_at);
        const date = `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
        const r = s.reservation!;
        lines.push(
            [
                date,
                timeLabel(s.starts_at),
                csvCell(r.user.name),
                csvCell(r.user.email),
                csvCell(r.service?.name ?? ''),
                csvCell(r.note ?? ''),
                csvCell(s.notes ?? ''),
            ].join(','),
        );
    }

    downloadCsv(lines, `reserves-detall-${period.value}.csv`);
}

function downloadCsv(lines: string[], filename: string): void {
    const blob = new Blob(['﻿' + lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
}

// Agenda d'un dia seleccionable (avui per defecte; navegable endavant i enrere).
const agendaDate = ref(today);

function shiftAgenda(delta: number): void {
    const d = new Date(agendaDate.value + 'T00:00:00');
    d.setDate(d.getDate() + delta);
    agendaDate.value = `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

function addDaysKey(baseKey: string, n: number): string {
    const d = new Date(baseKey + 'T00:00:00');
    d.setDate(d.getDate() + n);
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}
const agendaMin = computed(() => addDaysKey(today, -61));
const agendaMax = computed(() => addDaysKey(today, 62));

const agendaSlots = computed(() =>
    props.statsSlots
        .filter((s) => dayKeyOf(s.starts_at) === agendaDate.value)
        .sort((a, b) => a.starts_at.localeCompare(b.starts_at)),
);

const agendaLabel = computed(() => {
    const a = new Date(agendaDate.value + 'T00:00:00').getTime();
    const t = new Date(today + 'T00:00:00').getTime();
    const diff = Math.round((a - t) / 86400000);
    if (diff === 0) {
        return 'Avui';
    }
    if (diff === 1) {
        return 'Demà';
    }
    if (diff === -1) {
        return 'Ahir';
    }
    return new Date(agendaDate.value + 'T00:00:00').toLocaleDateString('ca-ES', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
    });
});

// Pròximes 3 reserves (futures) — independents del període.
const next3 = computed(() => {
    const nowMs = now.getTime();
    return props.statsSlots
        .filter((s) => s.reservation && new Date(s.starts_at).getTime() >= nowMs)
        .sort((a, b) => a.starts_at.localeCompare(b.starts_at))
        .slice(0, 3);
});

// Reserves futures sense servei assignat (per resoldre).
const noServiceSlots = computed(() => {
    const nowMs = now.getTime();
    return props.statsSlots.filter(
        (s) => s.reservation && !s.reservation.service && new Date(s.starts_at).getTime() >= nowMs,
    );
});

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

            <div class="rsv-summary">
                <div class="rsv-summary-head">
                    <h2 class="rsv-summary-title">Resum de reserves</h2>
                    <div class="rsv-export-group">
                        <button type="button" class="rsv-export" @click="exportDetailCsv">⬇ Exportar CSV (detall)</button>
                    </div>
                </div>
                <div class="rsv-period">
                    <button type="button" :class="{ 'is-active': period === 'day' }" @click="period = 'day'">Avui</button>
                    <button type="button" :class="{ 'is-active': period === 'week' }" @click="period = 'week'">Últims 7 dies</button>
                    <button type="button" :class="{ 'is-active': period === 'month' }" @click="period = 'month'">Últims 30 dies</button>
                </div>

                <section class="rsv-stats">
                    <div class="rsv-stat">
                        <span class="rsv-stat-num">{{ totalSlots }}</span>
                        <span class="rsv-stat-label">Franges totals</span>
                    </div>
                    <div class="rsv-stat is-booked">
                        <span class="rsv-stat-num">{{ totalBooked }}</span>
                        <span class="rsv-stat-label">Reservades</span>
                    </div>
                    <div class="rsv-stat is-free">
                        <span class="rsv-stat-num">{{ totalFree }}</span>
                        <span class="rsv-stat-label">Lliures</span>
                    </div>
                    <div class="rsv-stat">
                        <span class="rsv-stat-num">{{ occupancy }}%</span>
                        <span class="rsv-stat-label">Ocupació</span>
                    </div>
                    <div class="rsv-stat">
                        <span class="rsv-stat-num">{{ todayBooked }}</span>
                        <span class="rsv-stat-label">Reserves avui</span>
                    </div>
                    <div class="rsv-stat">
                        <span class="rsv-stat-num">{{ uniqueClients }}</span>
                        <span class="rsv-stat-label">Clients diferents</span>
                    </div>
                </section>

                <div class="rsv-summary-row">
                    <div class="rsv-panel is-wide">
                        <h3>
                            Agenda
                            <span class="rsv-count-badge alt">{{ agendaSlots.length }}</span>
                            <span class="rsv-agenda-nav">
                                <button type="button" aria-label="Dia anterior" @click="shiftAgenda(-1)">‹</button>
                                <span class="rsv-agenda-day">{{ agendaLabel }}</span>
                                <button type="button" aria-label="Dia següent" @click="shiftAgenda(1)">›</button>
                                <input
                                    v-model="agendaDate"
                                    type="date"
                                    class="rsv-agenda-date"
                                    :min="agendaMin"
                                    :max="agendaMax"
                                    aria-label="Tria un dia"
                                />
                                <button v-if="agendaDate !== today" type="button" class="rsv-agenda-today" @click="agendaDate = today">
                                    Avui
                                </button>
                            </span>
                        </h3>
                        <ul v-if="agendaSlots.length" class="rsv-agenda">
                            <li v-for="s in agendaSlots" :key="s.id" :class="s.reservation ? 'is-booked' : 'is-free'">
                                <span class="rsv-agenda-time">{{ timeLabel(s.starts_at) }}</span>
                                <span class="rsv-agenda-status" :class="s.reservation ? 'booked' : 'free'">
                                    {{ s.reservation ? 'Reservada' : 'Lliure' }}
                                </span>
                                <span class="rsv-agenda-info">
                                    <template v-if="s.reservation">
                                        👤 {{ s.reservation.user.name }}
                                        <template v-if="s.reservation.service"> · 🔖 {{ s.reservation.service.name }}</template>
                                        <template v-if="s.reservation.note"> · 💬 {{ s.reservation.note }}</template>
                                    </template>
                                    <template v-else>—</template>
                                </span>
                            </li>
                        </ul>
                        <p v-else class="rsv-mini-empty">No hi ha cap franja {{ agendaLabel.toLowerCase() }}.</p>
                    </div>

                    <div class="rsv-panel is-wide">
                        <h3>
                            Reserves per dia
                            <span
                                class="rsv-trend"
                                :class="trendPct > 0 ? 'up' : trendPct < 0 ? 'down' : 'flat'"
                            >
                                {{ trendPct > 0 ? '↑' : trendPct < 0 ? '↓' : '→' }} {{ Math.abs(trendPct) }}%
                            </span>
                        </h3>
                        <div class="rsv-line">
                            <svg viewBox="0 0 100 100" preserveAspectRatio="none" class="rsv-line-svg">
                                <polygon :points="areaPoints" class="rsv-line-area" />
                                <polyline :points="linePoints" class="rsv-line-stroke" />
                                <circle
                                    v-for="dot in lineDots"
                                    :key="dot.key"
                                    :cx="dot.x"
                                    :cy="dot.y"
                                    r="1.6"
                                    class="rsv-line-dot"
                                />
                            </svg>
                            <span
                                v-for="dot in lineDots"
                                :key="`val-${dot.key}`"
                                class="rsv-line-val"
                                :style="{ left: dot.x + '%', top: dot.y + '%' }"
                            >{{ dot.count }}</span>
                        </div>
                        <div class="rsv-line-x">
                            <span v-for="d in chartData" :key="d.key">{{ chartLabel(d.key) }}</span>
                        </div>
                    </div>

                    <div class="rsv-panel">
                        <h3>Reserves per servei</h3>
                        <div v-if="byService.length" class="rsv-hbars">
                            <div v-for="s in byService" :key="s.name" class="rsv-hbar">
                                <span class="rsv-hbar-label">{{ s.name }}</span>
                                <div class="rsv-hbar-track">
                                    <div class="rsv-hbar-fill" :style="{ width: Math.round((s.count / byServiceMax) * 100) + '%' }"></div>
                                </div>
                                <span class="rsv-hbar-num">{{ s.count }}</span>
                            </div>
                        </div>
                        <p v-else class="rsv-mini-empty">Cap reserva en aquest període.</p>
                    </div>

                    <div class="rsv-panel">
                        <h3>Reserves per dia de la setmana</h3>
                        <div class="rsv-chart">
                            <div v-for="w in byWeekday" :key="w.name" class="rsv-chart-col">
                                <div class="rsv-chart-track">
                                    <div
                                        class="rsv-chart-bar"
                                        :style="{ height: Math.round((w.count / byWeekdayMax) * 100) + '%' }"
                                        :title="`${w.count} reserves`"
                                    >
                                        <span v-if="w.count" class="rsv-chart-val">{{ w.count }}</span>
                                    </div>
                                </div>
                                <span class="rsv-chart-x">{{ w.name }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="rsv-panel">
                        <h3>Pròximes reserves</h3>
                        <ul v-if="next3.length" class="rsv-mini-list">
                            <li v-for="s in next3" :key="s.id">
                                <span class="t">{{ dayHeading(s.starts_at) }} · {{ timeLabel(s.starts_at) }}</span>
                                <span class="u">
                                    👤 {{ s.reservation!.user.name }}
                                    <template v-if="s.reservation!.service"> · 🔖 {{ s.reservation!.service.name }}</template>
                                </span>
                            </li>
                        </ul>
                        <p v-else class="rsv-mini-empty">Cap reserva propera en aquest període.</p>
                    </div>

                    <div class="rsv-panel">
                        <h3>Sense servei <span class="rsv-count-badge">{{ noServiceSlots.length }}</span></h3>
                        <ul v-if="noServiceSlots.length" class="rsv-mini-list is-warn">
                            <li v-for="s in noServiceSlots" :key="s.id">
                                <span class="t">{{ dayHeading(s.starts_at) }} · {{ timeLabel(s.starts_at) }}</span>
                                <span class="u">👤 {{ s.reservation!.user.name }}</span>
                            </li>
                        </ul>
                        <p v-else class="rsv-mini-empty">Totes les reserves tenen servei 🎉</p>
                    </div>
                </div>
            </div>
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
