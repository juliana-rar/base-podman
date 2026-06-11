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

const props = defineProps<{
    posts: Post[];
    slides: Slide[];
}>();

const page = usePage();
const user = computed(() => page.props.auth.user);
const { t } = useI18n();

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

// --- Lightbox: veure la imatge en gran ---
const lightbox = ref<string | null>(null);

function openLightbox(url: string): void {
    lightbox.value = url;
}

function closeLightbox(): void {
    lightbox.value = null;
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
    perView.value = w < 540 ? 1 : w < 820 ? 2 : 3;
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

        <AppFooter />

        <Teleport to="body">
            <transition name="rsv-lb">
                <div v-if="lightbox" class="rsv-lightbox" @click="closeLightbox">
                    <button type="button" class="rsv-lb-close" aria-label="Tancar" @click="closeLightbox">×</button>
                    <img :src="lightbox" alt="" @click.stop />
                </div>
            </transition>
        </Teleport>
    </div>
</template>
