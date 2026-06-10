<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import '../../css/reserva/clock.css';

const props = withDefaults(
    defineProps<{
        /** Hora seleccionada en format "HH:MM" (24h). */
        modelValue: string;
        /** Hores disponibles a destacar, en format "HH:MM". */
        highlightTimes?: string[];
        /** Si és cert, només es poden triar les hores destacades. */
        restrict?: boolean;
    }>(),
    {
        highlightTimes: () => [],
        restrict: false,
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const pad = (n: number) => String(n).padStart(2, '0');

const parsed = computed(() => {
    const match = (props.modelValue || '').match(/^(\d{1,2}):(\d{2})$/);

    if (!match) {
        return { hour: null as number | null, minute: 0 };
    }

    return { hour: Number(match[1]), minute: Number(match[2]) };
});

const selectedHour = computed(() => parsed.value.hour);
const minute = computed(() => parsed.value.minute);

// Meridià (AM/PM) per traduir els 12 números del rellotge a hores 0-23.
const meridiem = ref<'AM' | 'PM'>('AM');

watch(
    selectedHour,
    (hour) => {
        if (hour !== null) {
            meridiem.value = hour >= 12 ? 'PM' : 'AM';
        }
    },
    { immediate: true },
);

const availableHours = computed(
    () => new Set(props.highlightTimes.map((time) => Number(time.split(':')[0]))),
);

const numbers = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12];

function toHour24(displayNumber: number, period: 'AM' | 'PM'): number {
    if (period === 'AM') {
        return displayNumber === 12 ? 0 : displayNumber;
    }

    return displayNumber === 12 ? 12 : displayNumber + 12;
}

function commit(hour24: number, min: number): void {
    emit('update:modelValue', `${pad(hour24)}:${pad(min)}`);
}

function pickNumber(displayNumber: number): void {
    const hour24 = toHour24(displayNumber, meridiem.value);

    if (props.restrict) {
        const match = props.highlightTimes
            .filter((time) => Number(time.split(':')[0]) === hour24)
            .sort()[0];

        if (match) {
            emit('update:modelValue', match);
        }

        return;
    }

    commit(hour24, minute.value);
}

function setMeridiem(period: 'AM' | 'PM'): void {
    meridiem.value = period;

    if (selectedHour.value !== null) {
        const displayNumber = ((selectedHour.value + 11) % 12) + 1;
        commit(toHour24(displayNumber, period), minute.value);
    }
}

function setMinute(min: number): void {
    commit(selectedHour.value ?? 9, min);
}

function isSelected(displayNumber: number): boolean {
    return selectedHour.value !== null && toHour24(displayNumber, meridiem.value) === selectedHour.value;
}

function isAvailable(displayNumber: number): boolean {
    return availableHours.value.has(toHour24(displayNumber, meridiem.value));
}

function isDisabled(displayNumber: number): boolean {
    return props.restrict && !isAvailable(displayNumber);
}

const handStyle = computed(() => {
    const hour = selectedHour.value ?? 0;
    const angle = (hour % 12) * 30 + minute.value * 0.5;

    return { transform: `rotate(${angle}deg)` };
});

const readout = computed(() =>
    selectedHour.value === null ? '--:--' : `${pad(selectedHour.value)}:${pad(minute.value)}`,
);
</script>

<template>
    <div class="rsv-clock">
        <div>
            <div>
                <button
                    v-for="n in numbers"
                    :key="n"
                    type="button"
                    :class="{ 'is-selected': isSelected(n), 'is-available': isAvailable(n) }"
                    :disabled="isDisabled(n)"
                    @click="pickNumber(n)"
                >
                    {{ n }}
                </button>
            </div>
            <div :style="handStyle"></div>
            <div></div>
        </div>

        <div>
            <div>{{ readout }}</div>
            <div>
                <button type="button" :class="{ 'is-active': meridiem === 'AM' }" @click="setMeridiem('AM')">
                    AM
                </button>
                <button type="button" :class="{ 'is-active': meridiem === 'PM' }" @click="setMeridiem('PM')">
                    PM
                </button>
            </div>
            <div v-if="!restrict">
                <button
                    v-for="m in [0, 15, 30, 45]"
                    :key="m"
                    type="button"
                    :class="{ 'is-active': minute === m }"
                    @click="setMinute(m)"
                >
                    :{{ pad(m) }}
                </button>
            </div>
        </div>
    </div>
</template>
