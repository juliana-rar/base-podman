<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import Calendar from '@/components/Calendar.vue';
import ClockPicker from '@/components/ClockPicker.vue';
import { useI18n } from '@/lib/i18n';
import '../../css/reserva/booking.css';

interface Slot {
    id: number;
    starts_at: string;
    notes: string | null;
}

interface Service {
    id: number;
    name: string;
    url: string | null;
}

interface Reservation {
    id: number;
    note: string | null;
    slot: Slot;
    service: Service | null;
}

const props = defineProps<{
    availableSlots: Slot[];
    myReservations: Reservation[];
    services: Service[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Reservar', href: '/reservar' }],
    },
});

const { t, localeTag } = useI18n();
const pad = (n: number) => String(n).padStart(2, '0');

function timeOf(iso: string): string {
    const d = new Date(iso);
    return `${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

function dayKeyOf(iso: string): string {
    const d = new Date(iso);
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

function fullLabel(iso: string): string {
    return new Date(iso).toLocaleString(localeTag(), {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        hour: '2-digit',
        minute: '2-digit',
    });
}

const todayKey = dayKeyOf(new Date().toISOString());

const availableDayKeys = computed(() => [
    ...new Set(props.availableSlots.map((slot) => dayKeyOf(slot.starts_at))),
]);

const selectedDay = ref('');
const effectiveDay = computed(
    () => selectedDay.value || [...availableDayKeys.value].sort()[0] || todayKey,
);

const dayTimes = computed(() =>
    props.availableSlots
        .filter((slot) => dayKeyOf(slot.starts_at) === effectiveDay.value)
        .map((slot) => ({ id: slot.id, time: timeOf(slot.starts_at) }))
        .sort((a, b) => a.time.localeCompare(b.time)),
);

const highlightTimes = computed(() => dayTimes.value.map((entry) => entry.time));

const time = ref('');
const note = ref('');
const serviceId = ref<number | null>(null);

const selectedSlotId = computed(
    () => dayTimes.value.find((entry) => entry.time === time.value)?.id ?? null,
);

const canReserve = computed(
    () => selectedSlotId.value !== null && serviceId.value !== null && note.value.trim() !== '',
);

watch(effectiveDay, () => {
    time.value = '';
});

const dayLabel = computed(() =>
    new Date(effectiveDay.value + 'T00:00:00').toLocaleDateString(localeTag(), {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
    }),
);

const message = ref<{ type: 'ok' | 'err'; text: string } | null>(null);
let messageTimer: ReturnType<typeof setTimeout> | undefined;

function showMessage(type: 'ok' | 'err', text: string): void {
    message.value = { type, text };
    clearTimeout(messageTimer);
    messageTimer = setTimeout(() => (message.value = null), 6000);
}

function reserve(): void {
    if (!canReserve.value) {
        return;
    }

    const label = `${dayLabel.value} ${t('res.at')} ${time.value}`;

    router.post(
        '/reservas',
        { slot_id: selectedSlotId.value, service_id: serviceId.value, note: note.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                showMessage('ok', `${t('res.confirmed')} ${label}`);
                time.value = '';
                note.value = '';
            },
            onError: () => {
                showMessage('err', t('res.err'));
            },
        },
    );
}

// Cancel·lació amb motiu obligatori (modal).
const cancelId = ref<number | null>(null);
const cancelReason = ref('');

function openCancel(id: number): void {
    cancelId.value = id;
    cancelReason.value = '';
}

function closeCancel(): void {
    cancelId.value = null;
}

function confirmCancel(): void {
    if (cancelId.value === null || cancelReason.value.trim() === '') {
        return;
    }
    router.delete(`/reservas/${cancelId.value}`, {
        data: { reason: cancelReason.value },
        preserveScroll: true,
        onSuccess: () => {
            cancelId.value = null;
            cancelReason.value = '';
        },
    });
}
</script>

<template>
    <Head title="Reservar" />

    <div id="rsv-booking">
        <header>
            <h1>{{ t('res.title') }}</h1>
            <p>{{ t('res.subtitle') }}</p>
            <transition name="rsv-fade">
                <p v-if="message" class="rsv-confirm" :class="message.type">
                    {{ message.text }}
                </p>
            </transition>

            <div v-if="services.length" class="rsv-service-pick">
                <span class="rsv-service-label">{{ t('res.service') }} *</span>
                <div class="rsv-service-chips">
                    <button
                        v-for="s in services"
                        :key="s.id"
                        type="button"
                        :class="{ 'is-active': serviceId === s.id, 'has-img': s.url }"
                        @click="serviceId = s.id"
                    >
                        <img v-if="s.url" :src="s.url" alt="" />
                        <span>{{ s.name }}</span>
                    </button>
                </div>
            </div>
        </header>

        <section>
            <div>
                <h2>{{ t('res.triaDia') }}</h2>
                <Calendar
                    v-model="selectedDay"
                    :highlight-dates="availableDayKeys"
                    :min-date="todayKey"
                />
                <h3>{{ t('res.horesDisp') }}</h3>
                <div v-if="dayTimes.length">
                    <button
                        v-for="entry in dayTimes"
                        :key="entry.id"
                        type="button"
                        :class="{ 'is-active': entry.time === time }"
                        @click="time = entry.time"
                    >
                        {{ entry.time }}
                    </button>
                </div>
                <div v-else class="rsv-empty">{{ t('res.capHora') }}</div>
            </div>

            <div>
                <h2>{{ t('res.triaHora') }}</h2>
                <ClockPicker v-model="time" :highlight-times="highlightTimes" restrict />
                <div>
                    <label for="time-input">{{ t('res.oEscriu') }}</label>
                    <input id="time-input" v-model="time" type="time" step="900" />
                </div>
                <div class="rsv-note">
                    <label for="note">{{ t('res.note') }} *</label>
                    <textarea
                        id="note"
                        v-model="note"
                        :placeholder="t('res.notePlaceholder')"
                        maxlength="1000"
                        required
                    ></textarea>
                </div>
                <button type="button" :disabled="!canReserve" @click="reserve">
                    {{ selectedSlotId ? `${t('res.book')} ${dayLabel} ${t('res.at')} ${time}` : t('res.pickAvailable') }}
                </button>
            </div>
        </section>

        <section>
            <h2>{{ t('res.meves') }}</h2>
            <div v-if="myReservations.length">
                <div v-for="reservation in myReservations" :key="reservation.id">
                    <div class="rsv-res-info">
                        <span>{{ fullLabel(reservation.slot.starts_at) }}</span>
                        <span v-if="reservation.service" class="rsv-res-note">🔖 {{ reservation.service.name }}</span>
                        <span v-if="reservation.note" class="rsv-res-note">💬 {{ reservation.note }}</span>
                    </div>
                    <button type="button" @click="openCancel(reservation.id)">{{ t('res.cancelar') }}</button>
                </div>
            </div>
            <div v-else class="rsv-empty">{{ t('res.capReserva') }}</div>
        </section>

        <Teleport to="body">
            <transition name="rsv-fade">
                <div v-if="cancelId !== null" class="rsv-cancel-overlay" @click.self="closeCancel">
                    <div class="rsv-cancel-modal">
                        <h3>{{ t('res.cancelTitle') }}</h3>
                        <label for="cancel-reason">{{ t('res.cancelReason') }} *</label>
                        <textarea
                            id="cancel-reason"
                            v-model="cancelReason"
                            maxlength="1000"
                            :placeholder="t('res.cancelReasonPh')"
                        ></textarea>
                        <div class="rsv-cancel-actions">
                            <button type="button" class="rsv-cancel-back" @click="closeCancel">{{ t('res.cancelBack') }}</button>
                            <button type="button" class="rsv-cancel-go" :disabled="cancelReason.trim() === ''" @click="confirmCancel">
                                {{ t('res.cancelConfirm') }}
                            </button>
                        </div>
                    </div>
                </div>
            </transition>
        </Teleport>
    </div>
</template>
