<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import AppFooter from '@/components/AppFooter.vue';
import AppNavbar from '@/components/AppNavbar.vue';
import { useI18n } from '@/lib/i18n';
import '../../css/reserva/navbar.css';
import '../../css/reserva/postdetail.css';

interface Post {
    id: number;
    title: string;
    body: string;
    body2: string | null;
    cover_url: string | null;
    image_urls: string[];
    tags: { id: number; name: string; color: string }[];
    created_at: string;
    author: { id: number; name: string } | null;
}

const props = defineProps<{ post: Post }>();

const { localeTag } = useI18n();

// --- Galeria: carrusel de 3 imatges (responsive), més ample que el text ---
const images = computed(() => props.post.image_urls ?? []);
const perView = ref(3);
const index = ref(0);

function updatePerView(): void {
    const w = window.innerWidth;
    perView.value = w < 560 ? 1 : w < 900 ? 2 : 3;
    if (index.value > maxIndex.value) {
        index.value = maxIndex.value;
    }
}

const shownPer = computed(() => Math.min(perView.value, images.value.length || 1));
const useCarousel = computed(() => images.value.length > perView.value);
const maxIndex = computed(() => Math.max(0, images.value.length - perView.value));

const trackStyle = computed(() => ({
    transform: `translateX(calc(${-index.value} * 100% / ${perView.value}))`,
    transition: 'transform 0.4s ease',
}));

function prev(): void {
    if (index.value > 0) {
        index.value -= 1;
    }
}

function next(): void {
    if (index.value < maxIndex.value) {
        index.value += 1;
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

onMounted(() => {
    updatePerView();
    window.addEventListener('resize', updatePerView);
    window.addEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', updatePerView);
    window.removeEventListener('keydown', onKeydown);
});

function formatDate(value: string): string {
    return new Date(value).toLocaleDateString(localeTag(), {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}
</script>

<template>
    <Head :title="post.title">
        <link rel="preconnect" href="https://rsms.me/" />
        <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
    </Head>

    <div id="rsv-post">
        <AppNavbar />

        <article>
            <!-- Portada amb títol superposat (estil revista) -->
            <div
                v-if="post.cover_url"
                class="rsv-hero"
                :style="{ backgroundImage: `url(${post.cover_url})` }"
            >
                <div class="rsv-hero-content">
                    <div v-if="post.tags?.length" class="rsv-tags">
                        <span
                            v-for="tag in post.tags"
                            :key="tag.id"
                            :style="{ backgroundColor: tag.color + '22', color: tag.color }"
                        >{{ tag.name }}</span>
                    </div>
                    <h1>{{ post.title }}</h1>
                    <div class="rsv-meta">
                        <span class="rsv-avatar">{{ (post.author?.name ?? 'E').charAt(0) }}</span>
                        <span>{{ post.author?.name ?? 'Equip' }}</span>
                        <span class="rsv-dot">·</span>
                        <span>{{ formatDate(post.created_at) }}</span>
                    </div>
                </div>
            </div>

            <!-- Encapçalament sense portada -->
            <div v-else class="rsv-heading">
                <div v-if="post.tags?.length" class="rsv-tags">
                    <span
                        v-for="tag in post.tags"
                        :key="tag.id"
                        :style="{ backgroundColor: tag.color + '22', color: tag.color }"
                    >{{ tag.name }}</span>
                </div>
                <h1>{{ post.title }}</h1>
                <div class="rsv-meta">
                    <span class="rsv-avatar">{{ (post.author?.name ?? 'E').charAt(0) }}</span>
                    <span>{{ post.author?.name ?? 'Equip' }}</span>
                    <span class="rsv-dot">·</span>
                    <span>{{ formatDate(post.created_at) }}</span>
                </div>
            </div>

            <div class="rsv-content">
                <p class="rsv-body">{{ post.body }}</p>
            </div>

            <!-- Galeria ampla (entre els dos continguts): 3 imatges, fletxes i lightbox -->
            <div v-if="images.length" class="rsv-gallery" :style="{ '--per': shownPer }">
                <button
                    v-if="useCarousel"
                    type="button"
                    class="rsv-gal-nav"
                    aria-label="Anterior"
                    :disabled="index === 0"
                    @click="prev"
                >‹</button>
                <div class="rsv-gal-viewport">
                    <div
                        class="rsv-gal-track"
                        :class="{ 'is-static': !useCarousel }"
                        :style="useCarousel ? trackStyle : undefined"
                    >
                        <div v-for="(src, i) in images" :key="i" class="rsv-gal-item">
                            <img :src="src" alt="" @click="openLightbox(src)" />
                        </div>
                    </div>
                </div>
                <button
                    v-if="useCarousel"
                    type="button"
                    class="rsv-gal-nav"
                    aria-label="Següent"
                    :disabled="index === maxIndex"
                    @click="next"
                >›</button>
            </div>

            <div v-if="post.body2" class="rsv-content">
                <p class="rsv-body">{{ post.body2 }}</p>
            </div>
        </article>

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
