<script setup lang="ts">
import { computed, ref } from 'vue';
import '../../css/reserva/calendar.css';

const props = withDefaults(
    defineProps<{
        /** Dia seleccionat en format "YYYY-MM-DD". */
        modelValue: string;
        /** Dies a destacar (amb disponibilitat), format "YYYY-MM-DD". */
        highlightDates?: string[];
        /** Dia mínim seleccionable, format "YYYY-MM-DD". */
        minDate?: string;
    }>(),
    {
        highlightDates: () => [],
        minDate: '',
    },
);

const emit = defineEmits<{ 'update:modelValue': [value: string] }>();

const pad = (n: number) => String(n).padStart(2, '0');
const key = (y: number, m: number, d: number) => `${y}-${pad(m + 1)}-${pad(d)}`;

const weekdays = ['Dl', 'Dt', 'Dc', 'Dj', 'Dv', 'Ds', 'Dg'];
const monthNames = [
    'gener', 'febrer', 'març', 'abril', 'maig', 'juny',
    'juliol', 'agost', 'setembre', 'octubre', 'novembre', 'desembre',
];

const initial = props.modelValue ? new Date(props.modelValue + 'T00:00:00') : new Date();
const viewYear = ref(initial.getFullYear());
const viewMonth = ref(initial.getMonth());

const highlightSet = computed(() => new Set(props.highlightDates));

const title = computed(() => `${monthNames[viewMonth.value]} ${viewYear.value}`);

// Desplaçament del primer dia (setmana comença en dilluns).
const firstOffset = computed(() => {
    const day = new Date(viewYear.value, viewMonth.value, 1).getDay(); // 0=diumenge
    return (day + 6) % 7;
});

const daysInMonth = computed(() => new Date(viewYear.value, viewMonth.value + 1, 0).getDate());

const days = computed(() =>
    Array.from({ length: daysInMonth.value }, (_, i) => {
        const d = i + 1;
        const k = key(viewYear.value, viewMonth.value, d);
        return {
            day: d,
            key: k,
            hasData: highlightSet.value.has(k),
            disabled: props.minDate !== '' && k < props.minDate,
        };
    }),
);

function prevMonth(): void {
    if (viewMonth.value === 0) {
        viewMonth.value = 11;
        viewYear.value -= 1;
    } else {
        viewMonth.value -= 1;
    }
}

function nextMonth(): void {
    if (viewMonth.value === 11) {
        viewMonth.value = 0;
        viewYear.value += 1;
    } else {
        viewMonth.value += 1;
    }
}
</script>

<template>
    <div class="rsv-cal">
        <div>
            <button type="button" aria-label="Mes anterior" @click="prevMonth">‹</button>
            <span>{{ title }}</span>
            <button type="button" aria-label="Mes següent" @click="nextMonth">›</button>
        </div>

        <div>
            <span v-for="wd in weekdays" :key="wd">{{ wd }}</span>
        </div>

        <div>
            <button
                v-for="(d, index) in days"
                :key="d.key"
                type="button"
                :style="index === 0 ? { gridColumnStart: firstOffset + 1 } : undefined"
                :class="{ 'is-selected': d.key === modelValue, 'has-data': d.hasData }"
                :disabled="d.disabled"
                @click="emit('update:modelValue', d.key)"
            >
                {{ d.day }}
            </button>
        </div>
    </div>
</template>
