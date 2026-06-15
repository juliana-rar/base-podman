<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
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

// Cercador de serveis: desplaça el carrusel fins al servei trobat i el ressalta.
const serviceSearch = ref('');
const highlightId = ref<number | null>(null);
let highlightTimer: ReturnType<typeof setTimeout> | undefined;

function findAndScroll(): void {
    const query = serviceSearch.value.trim().toLowerCase();
    if (!query) {
        highlightId.value = null;
        return;
    }
    const match = visibleServices.value.find(
        (s) => s.name.toLowerCase().includes(query) || s.options.some((o) => o.name.toLowerCase().includes(query)),
    );
    if (!match) {
        return;
    }
    const el = document.getElementById(`rsv-svc-${match.id}`);
    if (!el) {
        return;
    }
    el.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
    highlightId.value = match.id;
    clearTimeout(highlightTimer);
    highlightTimer = setTimeout(() => (highlightId.value = null), 1600);
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

// Visor de galeria ampliada: la vista petita mostra la portada; al clicar es veuen
// totes les imatges alhora en graella (sense fletxes).
const galleryImages = ref<string[]>([]);

function openGallery(urls: string[]): void {
    if (!urls.length) return;
    galleryImages.value = urls;
}

function closeGallery(): void {
    galleryImages.value = [];
}

// Tancar el visor amb la tecla Escape.
function onGalleryKey(event: KeyboardEvent): void {
    if (galleryImages.value.length && event.key === 'Escape') {
        closeGallery();
    }
}

onMounted(() => window.addEventListener('keydown', onGalleryKey));
onUnmounted(() => {
    window.removeEventListener('keydown', onGalleryKey);
    clearTimeout(highlightTimer);
});

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

// Preselecció des de la presentació: /reservar?service=ID(&option=ID) selecciona el
// servei (i l'opció, si s'indica) i, automàticament, el primer empleat que el fa.
onMounted(() => {
    const params = new URLSearchParams(window.location.search);
    const requested = Number(params.get('service'));
    if (!requested) {
        return;
    }
    const requestedOption = Number(params.get('option')) || null;
    const service = props.services.find((s) => s.id === requested);
    const employee = props.employees.find((e) => e.service_ids.includes(requested));
    if (!service || !employee) {
        return;
    }
    // En triar empleat, el watch reinicia servei/opció; per això els fixem al tick següent.
    employeeId.value = employee.id;
    nextTick(() => {
        serviceId.value = requested;
        if (requestedOption && service.options.some((o) => o.id === requestedOption)) {
            optionId.value = requestedOption;
        }
    });
});

const dayLabel = computed(() =>
    new Date(effectiveDay.value + 'T00:00:00').toLocaleDateString(localeTag(), {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
    }),
);

// A /reservar només es mostren les reserves futures (encara no fetes); les ja fetes
// viuen a la pàgina /reserves, accessible des del menú.
const nowMs = Date.now();
const upcomingReservations = computed(() =>
    props.myReservations.filter((r) => new Date(r.slot.starts_at).getTime() >= nowMs),
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
                        <img
                            v-if="e.url"
                            :src="e.url"
                            alt=""
                            class="rsv-emp-img"
                            :title="t('res.zoomImg')"
                            @click.stop="openGallery(e.url ? [e.url] : [])"
                        />
                        <span>{{ e.name }}</span>
                    </button>
                </div>
            </div>

            <div v-if="services.length" class="rsv-service-pick">
                <span class="rsv-service-label">{{ t('res.service') }} *</span>
                <p v-if="employeeId === null" class="rsv-service-hint">{{ t('res.pickEmployeeFirst') }}</p>
                <p v-else-if="!serviceGroups.length" class="rsv-service-hint">{{ t('res.employeeNoServices') }}</p>
                <template v-else>
                    <div class="rsv-service-search">
                        <span class="rsv-service-search-icon">🔍</span>
                        <input
                            v-model="serviceSearch"
                            type="search"
                            :placeholder="t('welcome.searchService')"
                            @input="findAndScroll"
                            @keydown.enter.prevent="findAndScroll"
                        />
                    </div>
                    <div class="rsv-service-scroller">
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
                        <div
                            v-for="block in group.services"
                            :id="`rsv-svc-${block.service.id}`"
                            :key="block.service.id"
                            class="rsv-service-svcblock"
                            :class="{ 'is-found': highlightId === block.service.id }"
                        >
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
                </template>
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
            <div v-if="upcomingReservations.length">
                <div v-for="reservation in upcomingReservations" :key="reservation.id">
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
                    <div class="rsv-img-gallery" :class="{ 'is-single': galleryImages.length === 1 }">
                        <img v-for="(url, i) in galleryImages" :key="i" :src="url" alt="" class="rsv-img-zoom" />
                    </div>
                    <button type="button" class="rsv-img-close" aria-label="×" @click="closeGallery">×</button>
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
