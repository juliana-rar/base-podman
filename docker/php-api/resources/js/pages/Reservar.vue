<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import Calendar from '@/components/Calendar.vue';
import ClockPicker from '@/components/ClockPicker.vue';
import { useI18n } from '@/lib/i18n';
import '../../css/reserva/booking.css';

interface Slot {
    id: number;
    starts_at: string;
    notes: string | null;
}

interface ServiceOption {
    id: number;
    name: string;
    price: string;
    duration_minutes: number;
    description: string | null;
    url: string | null;
    image_urls: string[];
}

interface Service {
    id: number;
    name: string;
    price: string;
    duration_minutes: number;
    description: string | null;
    url: string | null;
    image_urls: string[];
    category: { id: number; name: string; description: string | null; url: string | null; image_urls: string[] } | null;
    options: ServiceOption[];
}

interface Employee {
    id: number;
    name: string;
    url: string | null;
    service_ids: number[];
    option_ids: number[];
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
    employees: Employee[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Reservar', href: '/reservar' }],
    },
});

const { t, localeTag } = useI18n();
const pad = (n: number) => String(n).padStart(2, '0');

// Empleat escollit (null = qualsevol). Filtra els serveis als que fa aquell empleat.
const employeeId = ref<number | null>(null);

const visibleServices = computed(() => {
    if (employeeId.value === null) return [];
    const emp = props.employees.find((e) => e.id === employeeId.value);
    if (!emp) return [];
    const ids = new Set(emp.service_ids);
    return props.services.filter((s) => ids.has(s.id));
});

// Element seleccionable: o bé un servei sense opcions, o bé una opció concreta d'un servei.
interface Bookable {
    key: string;
    service: Service;
    option: ServiceOption | null;
}

// Bloc d'un servei dins d'una categoria: el servei i els seus elements seleccionables (opcions o ell mateix).
interface ServiceBlock {
    service: Service;
    hasOptions: boolean;
    items: Bookable[];
}

function blockFor(service: Service): ServiceBlock {
    const hasOptions = service.options.length > 0;
    const items: Bookable[] = hasOptions
        ? service.options.map((o) => ({ key: `s${service.id}o${o.id}`, service, option: o }))
        : [{ key: `s${service.id}`, service, option: null }];
    return { service, hasOptions, items };
}

// Serveis agrupats per categoria; el grup sense categoria va al final (sense títol).
const serviceGroups = computed(() => {
    const byCat = new Map<
        number,
        { id: number | null; name: string; description: string | null; url: string | null; image_urls: string[]; services: ServiceBlock[] }
    >();
    const uncategorized: ServiceBlock[] = [];
    for (const s of visibleServices.value) {
        const block = blockFor(s);
        if (s.category) {
            const group = byCat.get(s.category.id);
            if (group) {
                group.services.push(block);
            } else {
                byCat.set(s.category.id, {
                    id: s.category.id,
                    name: s.category.name,
                    description: s.category.description,
                    url: s.category.url,
                    image_urls: s.category.image_urls,
                    services: [block],
                });
            }
        } else {
            uncategorized.push(block);
        }
    }
    const groups = [...byCat.values()].sort((a, b) => a.name.localeCompare(b.name));
    if (uncategorized.length) {
        groups.push({ id: null, name: '', description: null, url: null, image_urls: [], services: uncategorized });
    }
    return groups;
});

// Camps de presentació d'un bookable (l'opció mana sobre el servei per nom/descripció/imatge).
function itemName(item: Bookable): string {
    return item.option ? item.option.name : item.service.name;
}
function itemDescription(item: Bookable): string | null {
    return item.option ? item.option.description : item.service.description;
}
function itemUrl(item: Bookable): string | null {
    return (item.option && item.option.url) || item.service.url;
}
// Galeria completa d'un bookable: la de l'opció si en té, si no la del servei.
function itemImages(item: Bookable): string[] {
    if (item.option && item.option.image_urls.length) {
        return item.option.image_urls;
    }
    return item.service.image_urls;
}

// Preu i durada d'un bookable: els de l'opció si en té (>0), si no els del servei.
function itemMeta(item: Bookable): string {
    const price = item.option && Number(item.option.price) > 0 ? item.option.price : item.service.price;
    const duration = item.option && item.option.duration_minutes > 0 ? item.option.duration_minutes : item.service.duration_minutes;
    const parts: string[] = [];
    if (Number(price) > 0) parts.push(`${price} €`);
    if (duration > 0) parts.push(formatDuration(duration));
    return parts.join(' · ');
}
function isItemActive(item: Bookable): boolean {
    return serviceId.value === item.service.id && optionId.value === (item.option ? item.option.id : null);
}
function selectItem(item: Bookable): void {
    serviceId.value = item.service.id;
    optionId.value = item.option ? item.option.id : null;
}

// Mostra una durada en minuts com a "1 h 30 min" / "45 min".
function formatDuration(total: number): string {
    const h = Math.floor(total / 60);
    const m = total % 60;
    if (h && m) return `${h} ${t('srv.hours')} ${m} ${t('srv.minutes')}`;
    if (h) return `${h} ${t('srv.hours')}`;
    return `${m} ${t('srv.minutes')}`;
}

// Línia de preu i durada del servei (omet el que sigui 0).
function serviceMeta(s: Service): string {
    const parts: string[] = [];
    if (Number(s.price) > 0) parts.push(`${s.price} €`);
    if (s.duration_minutes > 0) parts.push(formatDuration(s.duration_minutes));
    return parts.join(' · ');
}

// Visor de galeria ampliada: una sola imatge a la vista petita; al clicar es pot
// desplaçar (fletxes, swipe tàctil o teclat) per veure la resta.
const galleryImages = ref<string[]>([]);
const galleryIndex = ref(0);

function openGallery(urls: string[], start = 0): void {
    if (!urls.length) return;
    galleryImages.value = urls;
    galleryIndex.value = Math.min(start, urls.length - 1);
}

function closeGallery(): void {
    galleryImages.value = [];
    galleryIndex.value = 0;
}

function nextImage(): void {
    if (galleryImages.value.length) {
        galleryIndex.value = (galleryIndex.value + 1) % galleryImages.value.length;
    }
}

function prevImage(): void {
    if (galleryImages.value.length) {
        galleryIndex.value = (galleryIndex.value - 1 + galleryImages.value.length) % galleryImages.value.length;
    }
}

// Navegació amb teclat mentre el visor és obert.
function onGalleryKey(event: KeyboardEvent): void {
    if (!galleryImages.value.length) return;
    if (event.key === 'ArrowRight') nextImage();
    else if (event.key === 'ArrowLeft') prevImage();
    else if (event.key === 'Escape') closeGallery();
}

onMounted(() => window.addEventListener('keydown', onGalleryKey));
onUnmounted(() => window.removeEventListener('keydown', onGalleryKey));

// Swipe tàctil sobre la imatge ampliada.
let touchStartX = 0;

function onTouchStart(event: TouchEvent): void {
    touchStartX = event.changedTouches[0].clientX;
}

function onTouchEnd(event: TouchEvent): void {
    const delta = event.changedTouches[0].clientX - touchStartX;
    if (Math.abs(delta) > 40) {
        if (delta < 0) nextImage();
        else prevImage();
    }
}

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
const optionId = ref<number | null>(null);

const selectedSlotId = computed(
    () => dayTimes.value.find((entry) => entry.time === time.value)?.id ?? null,
);

const canReserve = computed(
    () =>
        selectedSlotId.value !== null &&
        employeeId.value !== null &&
        serviceId.value !== null &&
        note.value.trim() !== '',
);

watch(effectiveDay, () => {
    time.value = '';
});

// En canviar d'empleat es reinicia la selecció de servei/opció.
watch(employeeId, () => {
    serviceId.value = null;
    optionId.value = null;
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
        {
            slot_id: selectedSlotId.value,
            service_id: serviceId.value,
            service_option_id: optionId.value,
            employee_id: employeeId.value,
            note: note.value,
        },
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

            <div v-if="employees.length" class="rsv-service-pick">
                <span class="rsv-service-label">{{ t('res.employee') }} *</span>
                <div class="rsv-service-chips">
                    <button
                        v-for="e in employees"
                        :key="e.id"
                        type="button"
                        :class="{ 'is-active': employeeId === e.id, 'has-img': e.url }"
                        @click="employeeId = e.id"
                    >
                        <img v-if="e.url" :src="e.url" alt="" />
                        <span>{{ e.name }}</span>
                    </button>
                </div>
            </div>

            <div v-if="services.length" class="rsv-service-pick">
                <span class="rsv-service-label">{{ t('res.service') }} *</span>
                <p v-if="employeeId === null" class="rsv-service-hint">{{ t('res.pickEmployeeFirst') }}</p>
                <p v-else-if="!serviceGroups.length" class="rsv-service-hint">{{ t('res.employeeNoServices') }}</p>
                <div v-else class="rsv-service-scroller">
                    <div class="rsv-service-groups">
                        <div v-for="group in serviceGroups" :key="group.id ?? 'none'" class="rsv-service-group">
                        <span v-if="group.name" class="rsv-service-cat">
                            <img
                                v-if="group.url"
                                :src="group.url"
                                alt=""
                                class="rsv-service-cat-img"
                                :title="t('res.zoomImg')"
                                @click="openGallery(group.image_urls)"
                            />
                            {{ group.name }}
                        </span>
                        <small v-if="group.description" class="rsv-service-catdesc">{{ group.description }}</small>
                        <div v-for="block in group.services" :key="block.service.id" class="rsv-service-svcblock">
                            <span v-if="block.hasOptions" class="rsv-service-svcname">
                                <img
                                    v-if="block.service.url"
                                    :src="block.service.url"
                                    alt=""
                                    class="rsv-service-svcimg"
                                    :title="t('res.zoomImg')"
                                    @click="openGallery(block.service.image_urls)"
                                />
                                {{ block.service.name }}
                            </span>
                            <div class="rsv-service-chips">
                                <button
                                    v-for="item in block.items"
                                    :key="item.key"
                                    type="button"
                                    :class="{ 'is-active': isItemActive(item), 'has-img': itemUrl(item) }"
                                    @click="selectItem(item)"
                                >
                                    <img
                                        v-if="itemUrl(item)"
                                        :src="itemUrl(item) ?? undefined"
                                        alt=""
                                        class="rsv-service-thumb"
                                        :title="t('res.zoomImg')"
                                        @click.stop="openGallery(itemImages(item))"
                                    />
                                    <span class="rsv-service-info">
                                        <span class="rsv-service-name">{{ itemName(item) }}</span>
                                        <small v-if="itemMeta(item)" class="rsv-service-meta">{{ itemMeta(item) }}</small>
                                        <small v-if="itemDescription(item)" class="rsv-service-desc">{{ itemDescription(item) }}</small>
                                    </span>
                                </button>
                            </div>
                        </div>
                        </div>
                    </div>
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
                <div v-if="galleryImages.length" class="rsv-img-overlay" @click.self="closeGallery">
                    <button
                        v-if="galleryImages.length > 1"
                        type="button"
                        class="rsv-img-nav rsv-img-prev"
                        :aria-label="t('res.prevImg')"
                        @click.stop="prevImage"
                    >
                        ‹
                    </button>
                    <img
                        :src="galleryImages[galleryIndex]"
                        alt=""
                        class="rsv-img-zoom"
                        @touchstart.passive="onTouchStart"
                        @touchend="onTouchEnd"
                    />
                    <button
                        v-if="galleryImages.length > 1"
                        type="button"
                        class="rsv-img-nav rsv-img-next"
                        :aria-label="t('res.nextImg')"
                        @click.stop="nextImage"
                    >
                        ›
                    </button>
                    <button type="button" class="rsv-img-close" aria-label="×" @click="closeGallery">×</button>
                    <div v-if="galleryImages.length > 1" class="rsv-img-dots">
                        <button
                            v-for="(url, i) in galleryImages"
                            :key="i"
                            type="button"
                            :class="{ 'is-active': i === galleryIndex }"
                            :aria-label="`${i + 1}`"
                            @click.stop="galleryIndex = i"
                        ></button>
                    </div>
                </div>
            </transition>
        </Teleport>

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
