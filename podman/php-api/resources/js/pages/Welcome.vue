<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import AppFooter from '@/components/AppFooter.vue';
import AppNavbar from '@/components/AppNavbar.vue';
import { useI18n } from '@/lib/i18n';
import '../../css/reserva/navbar.css';
import '../../css/reserva/welcome.css';

interface Post {
    id: number;
    title: string;
    slug: string;
    body: string;
    summary: string | null;
    cover_url: string | null;
    image_urls: string[];
    tags: { id: number; name: string; color: string }[];
    created_at: string;
    author: { id: number; name: string } | null;
}

interface Slide {
    id: number;
    url: string;
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
    category: { id: number; name: string; url: string | null; image_urls: string[] } | null;
    options: ServiceOption[];
}

// Element seleccionable: un servei sense opcions, o una opció concreta d'un servei.
interface Bookable {
    key: string;
    service: Service;
    option: ServiceOption | null;
}

// Bloc d'un servei dins d'una categoria: el servei i els seus elements (opcions o ell mateix).
interface ServiceBlock {
    service: Service;
    hasOptions: boolean;
    items: Bookable[];
}

interface Review {
    id: number;
    rating: number;
    review: string | null;
    review_images: string[] | null;
    review_image_urls: string[];
    user: { id: number; name: string } | null;
    service: { id: number; name: string } | null;
    employee: { id: number; name: string } | null;
    slot: { id: number; starts_at: string } | null;
}

const props = defineProps<{
    posts: Post[];
    slides: Slide[];
    services: Service[];
    reviews: Review[];
}>();

const page = usePage();
const user = computed(() => page.props.auth.user);
const { t, localeTag } = useI18n();

// Data d'una opinió (dia de la cita).
function reviewDate(iso: string): string {
    return new Date(iso).toLocaleDateString(localeTag(), { day: 'numeric', month: 'long', year: 'numeric' });
}

// Durada en minuts mostrada com a "1 h 30 min" / "45 min".
function formatDuration(total: number): string {
    const h = Math.floor(total / 60);
    const m = total % 60;
    if (h && m) return `${h} ${t('srv.hours')} ${m} ${t('srv.minutes')}`;
    if (h) return `${h} ${t('srv.hours')}`;
    return `${m} ${t('srv.minutes')}`;
}

// Bloc d'un servei: ell mateix (si no té opcions) o un element per opció.
function blockFor(service: Service): ServiceBlock {
    const hasOptions = service.options.length > 0;
    const items: Bookable[] = hasOptions
        ? service.options.map((o) => ({ key: `s${service.id}o${o.id}`, service, option: o }))
        : [{ key: `s${service.id}`, service, option: null }];
    return { service, hasOptions, items };
}

// Camps de presentació d'un element (l'opció mana sobre el servei).
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

// Preu i durada d'un element: els de l'opció si en té (>0), si no els del servei.
function itemMeta(item: Bookable): string {
    const price = item.option && Number(item.option.price) > 0 ? item.option.price : item.service.price;
    const duration = item.option && item.option.duration_minutes > 0 ? item.option.duration_minutes : item.service.duration_minutes;
    const parts: string[] = [];
    if (Number(price) > 0) parts.push(`${price} €`);
    if (duration > 0) parts.push(formatDuration(duration));
    return parts.join(' · ');
}

// Enllaç a /reservar amb el servei (i l'opció, si n'hi ha) preseleccionats.
function itemHref(item: Bookable): string {
    return item.option
        ? `/reservar?service=${item.service.id}&option=${item.option.id}`
        : `/reservar?service=${item.service.id}`;
}

// Serveis agrupats per categoria (igual que a /reservar); els sense categoria al final.
const serviceGroups = computed(() => {
    const byCat = new Map<number, { id: number | null; name: string; url: string | null; image_urls: string[]; services: ServiceBlock[] }>();
    const uncategorized: ServiceBlock[] = [];
    for (const s of props.services) {
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

// --- Cercador de serveis: desplaça el carrusel fins al servei trobat ---
const serviceSearch = ref('');
const highlightId = ref<number | null>(null);
let highlightTimer: ReturnType<typeof setTimeout> | undefined;

function findAndScroll(): void {
    const query = serviceSearch.value.trim().toLowerCase();
    if (!query) {
        highlightId.value = null;
        return;
    }
    const match = props.services.find(
        (s) => s.name.toLowerCase().includes(query) || s.options.some((o) => o.name.toLowerCase().includes(query)),
    );
    if (!match) {
        return;
    }
    const el = document.getElementById(`home-svc-${match.id}`);
    if (!el) {
        return;
    }
    el.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
    highlightId.value = match.id;
    clearTimeout(highlightTimer);
    highlightTimer = setTimeout(() => (highlightId.value = null), 1600);
}

// --- Carrusel d'imatges de presentació (4 visibles, bucle infinit, auto-play) ---
const slidePerView = ref(4);
const slideCount = computed(() => props.slides.length);
const slideUseCarousel = computed(() => slideCount.value > slidePerView.value);

const slideDisplay = computed(() =>
    slideUseCarousel.value ? [...props.slides, ...props.slides, ...props.slides] : props.slides,
);

const slideIndex = ref(props.slides.length);
const slideAnimate = ref(true);
let slideLocked = false;
let slideTimer: ReturnType<typeof setInterval> | undefined;

const slideTrackStyle = computed(() => ({
    transform: slideUseCarousel.value
        ? `translateX(calc(${-slideIndex.value} * 100% / ${slidePerView.value}))`
        : 'none',
    transition: slideAnimate.value ? `transform ${SLIDE_MS}ms ease` : 'none',
}));

function slideRecenter(delta: number): void {
    slideAnimate.value = false;
    slideIndex.value += delta;
    requestAnimationFrame(() => requestAnimationFrame(() => (slideAnimate.value = true)));
}

function slideStep(direction: number): void {
    if (slideLocked || !slideUseCarousel.value) {
        return;
    }
    slideLocked = true;
    slideIndex.value += direction;

    window.setTimeout(() => {
        if (slideIndex.value >= 2 * slideCount.value) {
            slideRecenter(-slideCount.value);
        } else if (slideIndex.value < slideCount.value) {
            slideRecenter(slideCount.value);
        }
        slideLocked = false;
    }, SLIDE_MS);
}

const slidePrev = () => slideStep(-1);
const slideNext = () => slideStep(1);

function restartAuto(): void {
    clearInterval(slideTimer);
    if (slideUseCarousel.value) {
        slideTimer = setInterval(slideNext, 4500);
    }
}

// --- Visor d'imatges en gran (slides i galeries de serveis/categories) ---
const lightbox = ref<string[]>([]);

function openLightbox(url: string): void {
    lightbox.value = [url];
}

// Obre la galeria amb totes les imatges (com a /reservar). Accepta el cover de
// reserva com a alternativa si encara no s'ha carregat la galeria.
function openImages(urls: string[], fallback?: string | null): void {
    const images = urls.length ? urls : fallback ? [fallback] : [];
    if (images.length) {
        lightbox.value = images;
    }
}

function closeLightbox(): void {
    lightbox.value = [];
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        closeLightbox();
    }
}

// --- Carrusel infinit (3 visibles, responsive) ---
const SLIDE_MS = 450;
const perView = ref(3);

function updatePerView(): void {
    const w = window.innerWidth;
    perView.value = w < 540 ? 1 : w < 820 ? 2 : w < 1100 ? 3 : 4;
    slidePerView.value = w < 540 ? 1 : w < 820 ? 2 : w < 1100 ? 3 : 4;
}

const count = computed(() => props.posts.length);
const useCarousel = computed(() => count.value > perView.value);

// Buffer triple per al bucle infinit; recentrem en silenci als extrems.
const display = computed(() =>
    useCarousel.value ? [...props.posts, ...props.posts, ...props.posts] : props.posts,
);

const index = ref(props.posts.length);
const animate = ref(true);
let locked = false;

const trackStyle = computed(() => ({
    transform: useCarousel.value ? `translateX(calc(${-index.value} * 100% / ${perView.value}))` : 'none',
    transition: animate.value ? `transform ${SLIDE_MS}ms ease` : 'none',
}));

function recenter(delta: number): void {
    animate.value = false;
    index.value += delta;
    requestAnimationFrame(() => requestAnimationFrame(() => (animate.value = true)));
}

function step(direction: number): void {
    if (locked || !useCarousel.value) {
        return;
    }
    locked = true;
    index.value += direction;

    window.setTimeout(() => {
        if (index.value >= 2 * count.value) {
            recenter(-count.value);
        } else if (index.value < count.value) {
            recenter(count.value);
        }
        locked = false;
    }, SLIDE_MS);
}

const prev = () => step(-1);
const next = () => step(1);

onMounted(() => {
    updatePerView();
    window.addEventListener('resize', updatePerView);
    window.addEventListener('keydown', onKeydown);
    restartAuto();
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', updatePerView);
    window.removeEventListener('keydown', onKeydown);
    clearInterval(slideTimer);
    clearTimeout(highlightTimer);
});
</script>

<template>
    <Head title="Reserva d'hores">
        <link rel="preconnect" href="https://rsms.me/" />
        <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
    </Head>

    <div id="rsv-welcome">
        <AppNavbar />

        <section>
            <h1>{{ t('welcome.heroA') }} <span>{{ t('welcome.heroB') }}</span></h1>
            <p>{{ t('welcome.heroText') }}</p>
            <Link :href="user ? '/reservar' : '/login'">
                {{ user ? t('welcome.ctaUser') : t('welcome.ctaGuest') }}
            </Link>

            <div v-if="slides.length" class="rsv-slides" :style="{ '--per': slidePerView }">
                <button v-if="slideUseCarousel" type="button" class="rsv-slide-nav" aria-label="Anterior" @click="slidePrev">
                    ‹
                </button>
                <div class="rsv-slide-viewport">
                    <div class="rsv-slide-track" :class="{ 'is-static': !slideUseCarousel }" :style="slideTrackStyle">
                        <div v-for="(s, i) in slideDisplay" :key="i" class="rsv-slide-item">
                            <img :src="s.url" alt="" @click="openLightbox(s.url)" />
                        </div>
                    </div>
                </div>
                <button v-if="slideUseCarousel" type="button" class="rsv-slide-nav" aria-label="Següent" @click="slideNext">
                    ›
                </button>
            </div>
        </section>

        <section class="rsv-services" aria-labelledby="rsv-services-title">
            <h2 id="rsv-services-title">{{ t('welcome.services') }}</h2>
            <div v-if="serviceGroups.length" class="rsv-svc-search">
                <span class="rsv-svc-search-icon">🔍</span>
                <input
                    v-model="serviceSearch"
                    type="search"
                    :placeholder="t('welcome.searchService')"
                    @input="findAndScroll"
                    @keydown.enter.prevent="findAndScroll"
                />
            </div>
            <div v-if="serviceGroups.length" class="rsv-service-scroller">
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
                        <div
                            v-for="block in group.services"
                            :id="`home-svc-${block.service.id}`"
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
            <div v-else class="rsv-empty">{{ t('welcome.servicesEmpty') }}</div>
        </section>

        <section>
            <h2>{{ t('welcome.novetats') }}</h2>
            <div v-if="posts.length" class="rsv-carousel" :style="{ '--per': perView }">
                <button v-if="useCarousel" type="button" class="rsv-arrow" aria-label="Anterior" @click="prev">
                    ‹
                </button>
                <div class="rsv-viewport">
                    <div class="rsv-track" :class="{ 'is-static': !useCarousel }" :style="trackStyle">
                        <div v-for="(post, i) in display" :key="i" class="rsv-slide">
                            <Link :href="`/posts/${post.slug}`" class="rsv-card">
                                <img v-if="post.cover_url" :src="post.cover_url" alt="" class="rsv-cover" />
                                <h3 class="rsv-title">{{ post.title }}</h3>
                                <div v-if="post.tags?.length" class="rsv-tags">
                                    <span
                                        v-for="tag in post.tags"
                                        :key="tag.id"
                                        :style="{ backgroundColor: tag.color + '22', color: tag.color }"
                                    >{{ tag.name }}</span>
                                </div>
                                <p v-if="post.summary" class="rsv-body">{{ post.summary }}</p>
                            </Link>
                        </div>
                    </div>
                </div>
                <button v-if="useCarousel" type="button" class="rsv-arrow" aria-label="Següent" @click="next">
                    ›
                </button>
            </div>
            <div v-else class="rsv-empty">{{ t('welcome.empty') }}</div>
        </section>

        <section v-if="reviews.length" class="rsv-reviews" aria-labelledby="rsv-reviews-title">
            <h2 id="rsv-reviews-title">{{ t('welcome.reviews') }}</h2>
            <div class="rsv-rev-grid">
                <div v-for="review in reviews" :key="review.id" class="rsv-rev-card">
                    <div class="rsv-rev-stars" :aria-label="`${review.rating}/5`">
                        <span v-for="n in 5" :key="n" :class="{ on: n <= review.rating }">★</span>
                    </div>

                    <span class="rsv-rev-author">{{ review.user?.name }}</span>

                    <div class="rsv-rev-fields">
                        <span v-if="review.service">
                            <span class="rsv-rev-lbl">{{ t('rev.byService') }}:</span> {{ review.service.name }}
                        </span>
                        <span v-if="review.employee">
                            <span class="rsv-rev-lbl">{{ t('rev.byEmployee') }}:</span> {{ review.employee.name }}
                        </span>
                        <span v-if="review.slot" class="rsv-rev-date">📅 {{ reviewDate(review.slot.starts_at) }}</span>
                    </div>

                    <p v-if="review.review" class="rsv-rev-text">{{ review.review }}</p>

                    <div v-if="review.review_image_urls.length" class="rsv-rev-imgs">
                        <img
                            v-for="(url, i) in review.review_image_urls"
                            :key="i"
                            :src="url"
                            alt=""
                            :title="t('res.zoomImg')"
                            @click="openImages(review.review_image_urls)"
                        />
                    </div>
                </div>
            </div>
        </section>

        <AppFooter />

        <Teleport to="body">
            <transition name="rsv-lb">
                <div v-if="lightbox.length" class="rsv-lightbox" @click="closeLightbox">
                    <button type="button" class="rsv-lb-close" aria-label="Tancar" @click="closeLightbox">×</button>
                    <div class="rsv-lb-gallery" :class="{ 'is-single': lightbox.length === 1 }" @click.stop>
                        <img v-for="(url, i) in lightbox" :key="i" :src="url" alt="" />
                    </div>
                </div>
            </transition>
        </Teleport>
    </div>
</template>
