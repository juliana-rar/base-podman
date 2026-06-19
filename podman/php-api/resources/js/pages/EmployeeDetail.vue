<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import AppFooter from '@/components/AppFooter.vue';
import AppNavbar from '@/components/AppNavbar.vue';
import { useI18n } from '@/lib/i18n';
import '../../css/reserva/navbar.css';
import '../../css/reserva/employeedetail.css';

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
    category: { id: number; name: string; url: string | null; image_urls: string[] } | null;
    options: ServiceOption[];
}

interface Employee {
    id: number;
    name: string;
    description: string | null;
    url: string | null;
    work_urls: string[];
    work_captions: string[];
    services: Service[];
}

// Element seleccionable: un servei sense opcions, o una opció concreta d'un servei.
interface Bookable {
    key: string;
    service: Service;
    option: ServiceOption | null;
}

interface ServiceBlock {
    service: Service;
    hasOptions: boolean;
    items: Bookable[];
}

const props = defineProps<{ employee: Employee }>();

const { t } = useI18n();

// Imatge del hero: la foto de l'empleat; si no en té, la primera obra.
const heroImage = computed(() => props.employee.url ?? props.employee.work_urls[0] ?? null);

// --- Serveis: mateixa lògica i presentació que el home (agrupats per categoria) ---
function formatDuration(total: number): string {
    const h = Math.floor(total / 60);
    const m = total % 60;
    if (h && m) return `${h} ${t('srv.hours')} ${m} ${t('srv.minutes')}`;
    if (h) return `${h} ${t('srv.hours')}`;
    return `${m} ${t('srv.minutes')}`;
}

function blockFor(service: Service): ServiceBlock {
    const hasOptions = service.options.length > 0;
    const items: Bookable[] = hasOptions
        ? service.options.map((o) => ({ key: `s${service.id}o${o.id}`, service, option: o }))
        : [{ key: `s${service.id}`, service, option: null }];
    return { service, hasOptions, items };
}

function itemName(item: Bookable): string {
    return item.option ? item.option.name : item.service.name;
}
function itemDescription(item: Bookable): string | null {
    return item.option ? item.option.description : item.service.description;
}
function itemUrl(item: Bookable): string | null {
    return (item.option && item.option.url) || item.service.url;
}
function itemImages(item: Bookable): string[] {
    if (item.option && item.option.image_urls.length) return item.option.image_urls;
    return item.service.image_urls;
}
function itemMeta(item: Bookable): string {
    const price = item.option && Number(item.option.price) > 0 ? item.option.price : item.service.price;
    const duration = item.option && item.option.duration_minutes > 0 ? item.option.duration_minutes : item.service.duration_minutes;
    const parts: string[] = [];
    if (Number(price) > 0) parts.push(`${price} €`);
    if (duration > 0) parts.push(formatDuration(duration));
    return parts.join(' · ');
}
function itemHref(item: Bookable): string {
    return item.option
        ? `/reservar?service=${item.service.id}&option=${item.option.id}`
        : `/reservar?service=${item.service.id}`;
}

// Serveis agrupats per categoria (igual que al home); els sense categoria al final.
const serviceGroups = computed(() => {
    const byCat = new Map<number, { id: number | null; name: string; url: string | null; image_urls: string[]; services: ServiceBlock[] }>();
    const uncategorized: ServiceBlock[] = [];
    for (const s of props.employee.services) {
        const block = blockFor(s);
        if (s.category) {
            const group = byCat.get(s.category.id);
            if (group) {
                group.services.push(block);
            } else {
                byCat.set(s.category.id, {
                    id: s.category.id,
                    name: s.category.name,
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
        groups.push({ id: null, name: t('srv.noCategory'), url: null, image_urls: [], services: uncategorized });
    }
    return groups;
});

// --- Carrusel cinematogràfic de les obres (centre gran, laterals petits, bucle infinit) ---
const WORK_MS = 450;
// Amplada de cada obra en % del viewport: com més petita, més «peek» laterals (3 visibles).
const workItemPct = ref(34);
const workCount = computed(() => props.employee.work_urls.length);
const workUseCarousel = computed(() => workCount.value > 1);

// Buffer triple per al bucle infinit; recentrem en silenci als extrems.
const workDisplay = computed(() =>
    workUseCarousel.value
        ? [...props.employee.work_urls, ...props.employee.work_urls, ...props.employee.work_urls]
        : props.employee.work_urls,
);

const workIndex = ref(props.employee.work_urls.length);
const workAnimate = ref(true);
let workLocked = false;
let workTimer: ReturnType<typeof setInterval> | undefined;

// Peu de foto de l'obra que ocupa el centre.
const workCenterCaption = computed(() => props.employee.work_captions[workIndex.value % workCount.value] ?? '');

const workTrackStyle = computed(() => {
    if (!workUseCarousel.value) {
        return { transform: 'none', transition: 'none' };
    }
    const offset = 50 - (workIndex.value + 0.5) * workItemPct.value;
    return {
        transform: `translateX(${offset}%)`,
        transition: workAnimate.value ? `transform ${WORK_MS}ms ease` : 'none',
    };
});

function workRecenter(delta: number): void {
    workAnimate.value = false;
    workIndex.value += delta;
    requestAnimationFrame(() => requestAnimationFrame(() => (workAnimate.value = true)));
}

function workGoTo(target: number): void {
    if (workLocked || !workUseCarousel.value) {
        return;
    }
    workLocked = true;
    workIndex.value = target;

    window.setTimeout(() => {
        if (workIndex.value >= 2 * workCount.value) {
            workRecenter(-workCount.value);
        } else if (workIndex.value < workCount.value) {
            workRecenter(workCount.value);
        }
        workLocked = false;
    }, WORK_MS);
}

const workPrev = () => workGoTo(workIndex.value - 1);
const workNext = () => workGoTo(workIndex.value + 1);

// Clic en una obra: si és lateral, la porta al centre; si ja és la central, obre el visor.
function workClick(displayPos: number, realIndex: number): void {
    if (workUseCarousel.value && displayPos !== workIndex.value) {
        workGoTo(displayPos);
    } else {
        openWorks(realIndex);
    }
}

function pauseWorksAuto(): void {
    clearInterval(workTimer);
}

function restartWorksAuto(): void {
    clearInterval(workTimer);
    if (workUseCarousel.value) {
        workTimer = setInterval(workNext, 4500);
    }
}

// Amplada de la diapositiva central segons el viewport (deixa veure els laterals).
function updateWorkSize(): void {
    const w = window.innerWidth;
    workItemPct.value = w < 640 ? 78 : w < 1024 ? 52 : 34;
}

// --- Visor d'imatges en gran (obres i imatges de servei) amb fletxes i peu de foto ---
const lightbox = ref<string[]>([]);
const lightboxCaptions = ref<string[]>([]);
const lightboxIndex = ref(0);

const lightboxCaption = computed(() => lightboxCaptions.value[lightboxIndex.value] ?? '');

// Obre el visor amb les obres de l'empleat (amb els seus peus de foto).
function openWorks(start = 0): void {
    if (!props.employee.work_urls.length) {
        return;
    }
    lightbox.value = props.employee.work_urls;
    lightboxCaptions.value = props.employee.work_captions;
    lightboxIndex.value = start;
}

// Obre el visor amb les imatges d'un servei/opció (sense peus de foto).
function openImages(urls: string[], fallback?: string | null): void {
    const images = urls.length ? urls : fallback ? [fallback] : [];
    if (images.length) {
        lightbox.value = images;
        lightboxCaptions.value = [];
        lightboxIndex.value = 0;
    }
}

function closeLightbox(): void {
    lightbox.value = [];
    lightboxCaptions.value = [];
    lightboxIndex.value = 0;
}

function lightboxPrev(): void {
    lightboxIndex.value = (lightboxIndex.value - 1 + lightbox.value.length) % lightbox.value.length;
}

function lightboxNext(): void {
    lightboxIndex.value = (lightboxIndex.value + 1) % lightbox.value.length;
}

function onKeydown(event: KeyboardEvent): void {
    if (!lightbox.value.length) {
        return;
    }
    if (event.key === 'Escape') {
        closeLightbox();
    } else if (event.key === 'ArrowLeft') {
        lightboxPrev();
    } else if (event.key === 'ArrowRight') {
        lightboxNext();
    }
}

onMounted(() => {
    updateWorkSize();
    window.addEventListener('resize', updateWorkSize);
    window.addEventListener('keydown', onKeydown);
    restartWorksAuto();
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', updateWorkSize);
    window.removeEventListener('keydown', onKeydown);
    clearInterval(workTimer);
});
</script>

<template>
    <Head :title="employee.name">
        <link rel="preconnect" href="https://rsms.me/" />
        <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
    </Head>

    <div id="rsv-member">
        <AppNavbar />

        <article>
            <!-- Hero: foto de l'empleat a l'esquerra, nom i descripció a la dreta -->
            <header class="rsv-hero" :class="{ 'no-photo': !heroImage }">
                <div v-if="heroImage" class="rsv-hero-photo">
                    <img :src="heroImage" alt="" />
                </div>
                <div class="rsv-hero-text">
                    <h1 class="rsv-name">{{ employee.name }}</h1>
                    <p v-if="employee.description" class="rsv-bio">{{ employee.description }}</p>
                </div>
            </header>

            <!-- Obres a dalt: carrusel cinematogràfic (centre gran, laterals petits) -->
            <section v-if="employee.work_urls.length" class="rsv-works">
                <h2>{{ t('welcome.teamWorks') }}</h2>
                <div
                    class="rsv-works-carousel"
                    :class="{ 'is-single': !workUseCarousel }"
                    :style="{ '--w': workItemPct }"
                    @mouseenter="pauseWorksAuto"
                    @mouseleave="restartWorksAuto"
                >
                    <button
                        v-if="workUseCarousel"
                        type="button"
                        class="rsv-works-nav"
                        aria-label="Anterior"
                        @click="workPrev"
                    >
                        ‹
                    </button>
                    <div class="rsv-works-viewport">
                        <div
                            class="rsv-works-track"
                            :class="{ 'is-static': !workUseCarousel }"
                            :style="workTrackStyle"
                        >
                            <div
                                v-for="(url, i) in workDisplay"
                                :key="i"
                                class="rsv-works-item"
                                :class="{ 'is-center': workUseCarousel && i === workIndex }"
                            >
                                <img :src="url" alt="" :title="t('res.zoomImg')" @click="workClick(i, i % workCount)" />
                            </div>
                        </div>
                    </div>
                    <button
                        v-if="workUseCarousel"
                        type="button"
                        class="rsv-works-nav"
                        aria-label="Següent"
                        @click="workNext"
                    >
                        ›
                    </button>
                </div>
                <p v-if="workCenterCaption" class="rsv-works-caption">{{ workCenterCaption }}</p>
            </section>

            <!-- Serveis a sota: mateix aspecte que el home, només els d'aquest empleat -->
            <section v-if="serviceGroups.length" class="rsv-services">
                <h2>{{ t('welcome.teamServices') }}</h2>
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
                                    @click="openImages(group.image_urls, group.url)"
                                />
                                {{ group.name }}
                            </span>
                            <div v-for="block in group.services" :key="block.service.id" class="rsv-service-svcblock">
                                <span v-if="block.hasOptions" class="rsv-service-svcname">
                                    <img
                                        v-if="block.service.url"
                                        :src="block.service.url"
                                        alt=""
                                        class="rsv-service-svcimg"
                                        :title="t('res.zoomImg')"
                                        @click="openImages(block.service.image_urls, block.service.url)"
                                    />
                                    {{ block.service.name }}
                                </span>
                                <div class="rsv-service-chips">
                                    <Link
                                        v-for="item in block.items"
                                        :key="item.key"
                                        :href="itemHref(item)"
                                        :class="{ 'has-img': itemUrl(item) }"
                                    >
                                        <img
                                            v-if="itemUrl(item)"
                                            :src="itemUrl(item) ?? undefined"
                                            alt=""
                                            class="rsv-service-thumb"
                                            :title="t('res.zoomImg')"
                                            @click.prevent.stop="openImages(itemImages(item), itemUrl(item))"
                                        />
                                        <span class="rsv-service-info">
                                            <span class="rsv-service-name">{{ itemName(item) }}</span>
                                            <small v-if="itemMeta(item)" class="rsv-service-meta">{{ itemMeta(item) }}</small>
                                            <small v-if="itemDescription(item)" class="rsv-service-desc">{{ itemDescription(item) }}</small>
                                        </span>
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </article>

        <AppFooter />

        <Teleport to="body">
            <transition name="rsv-lb">
                <div v-if="lightbox.length" class="rsv-lightbox" @click="closeLightbox">
                    <button type="button" class="rsv-lb-close" aria-label="Tancar" @click="closeLightbox">×</button>
                    <button
                        v-if="lightbox.length > 1"
                        type="button"
                        class="rsv-lb-nav rsv-lb-prev"
                        aria-label="Anterior"
                        @click.stop="lightboxPrev"
                    >
                        ‹
                    </button>
                    <figure class="rsv-lb-stage" @click.stop>
                        <img :src="lightbox[lightboxIndex]" alt="" />
                        <figcaption v-if="lightboxCaption" class="rsv-lb-caption">{{ lightboxCaption }}</figcaption>
                    </figure>
                    <button
                        v-if="lightbox.length > 1"
                        type="button"
                        class="rsv-lb-nav rsv-lb-next"
                        aria-label="Següent"
                        @click.stop="lightboxNext"
                    >
                        ›
                    </button>
                    <span v-if="lightbox.length > 1" class="rsv-lb-counter">
                        {{ lightboxIndex + 1 }} / {{ lightbox.length }}
                    </span>
                </div>
            </transition>
        </Teleport>
    </div>
</template>
