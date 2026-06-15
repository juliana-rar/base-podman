<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useI18n } from '@/lib/i18n';
import '../../../css/reserva/reserves.css';

interface Review {
    id: number;
    rating: number;
    review: string | null;
    review_images: string[] | null;
    review_image_urls: string[];
    review_published: boolean;
    note: string | null;
    slot: { id: number; starts_at: string };
    service: { id: number; name: string } | null;
    user: { id: number; name: string; email: string };
}

interface PageLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Paginator<T> {
    data: T[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    links: PageLink[];
}

const props = defineProps<{ reviews: Paginator<Review> }>();

// Enllaços numèrics de pàgina (sense el «Previous»/«Next» que afegim a mà).
const pageLinks = computed<PageLink[]>(() => props.reviews.links.slice(1, -1));

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Valoracions', href: '/reserves-admin' }],
    },
});

const { t, localeTag } = useI18n();

function fullLabel(iso: string): string {
    return new Date(iso).toLocaleString(localeTag(), {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        hour: '2-digit',
        minute: '2-digit',
    });
}

// --- Visor d'imatges en gran ---
const gallery = ref<string[]>([]);

function openGallery(urls: string[], start: number): void {
    if (urls.length) {
        gallery.value = urls.slice(start).concat(urls.slice(0, start));
    }
}

function closeGallery(): void {
    gallery.value = [];
}

// L'admin publica/retira la valoració de la pàgina d'inici.
function togglePublished(review: Review): void {
    router.post(`/reserves-admin/${review.id}/publica`, {}, { preserveScroll: true });
}

function onKey(event: KeyboardEvent): void {
    if (gallery.value.length && event.key === 'Escape') {
        closeGallery();
    }
}

onMounted(() => window.addEventListener('keydown', onKey));
onUnmounted(() => window.removeEventListener('keydown', onKey));
</script>

<template>
    <Head :title="t('rev.title')" />

    <div id="rsv-reserves">
        <header>
            <h1>{{ t('rev.title') }}</h1>
            <p>{{ t('rev.subtitle') }}</p>
        </header>

        <section>
            <div v-if="reviews.data.length" class="rsv-rsv-list">
                <div v-for="review in reviews.data" :key="review.id" class="rsv-rsv-item">
                    <div class="rsv-rsv-info">
                        <span class="rsv-rsv-date">{{ fullLabel(review.slot.starts_at) }}</span>
                        <span class="rsv-rsv-note">👤 {{ review.user.name }} · {{ review.user.email }}</span>
                        <span v-if="review.service" class="rsv-rsv-note">🔖 {{ review.service.name }}</span>
                    </div>

                    <div class="rsv-rsv-review">
                        <div class="rsv-stars" :aria-label="`${review.rating}/5`">
                            <span v-for="n in 5" :key="n" :class="{ on: n <= review.rating }">★</span>
                        </div>
                        <p v-if="review.review" class="rsv-rsv-reviewtext">{{ review.review }}</p>
                        <div v-if="review.review_image_urls.length" class="rsv-rsv-thumbs">
                            <img
                                v-for="(url, i) in review.review_image_urls"
                                :key="i"
                                :src="url"
                                alt=""
                                :title="t('res.zoomImg')"
                                @click="openGallery(review.review_image_urls, i)"
                            />
                        </div>

                        <div class="rsv-rev-publish">
                            <span class="rsv-rev-state" :class="{ on: review.review_published }">
                                {{ review.review_published ? t('rev.shown') : t('rev.hidden') }}
                            </span>
                            <button
                                type="button"
                                class="rsv-rsv-btn"
                                :class="{ primary: !review.review_published }"
                                @click="togglePublished(review)"
                            >
                                {{ review.review_published ? t('rev.unpublish') : t('rev.publish') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div v-else class="rsv-rsv-empty">{{ t('rev.empty') }}</div>

            <nav v-if="reviews.last_page > 1" class="rsv-pagination" :aria-label="t('rev.pagination')">
                <Link
                    v-if="reviews.prev_page_url"
                    :href="reviews.prev_page_url"
                    preserve-scroll
                    preserve-state
                    class="rsv-page-btn"
                    >‹ {{ t('rev.prev') }}</Link
                >
                <span v-else class="rsv-page-btn is-disabled">‹ {{ t('rev.prev') }}</span>

                <template v-for="link in pageLinks" :key="link.label">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        preserve-scroll
                        preserve-state
                        class="rsv-page-num"
                        :class="{ on: link.active }"
                        >{{ link.label }}</Link
                    >
                    <span v-else class="rsv-page-num is-gap">{{ link.label }}</span>
                </template>

                <Link
                    v-if="reviews.next_page_url"
                    :href="reviews.next_page_url"
                    preserve-scroll
                    preserve-state
                    class="rsv-page-btn"
                    >{{ t('rev.next') }} ›</Link
                >
                <span v-else class="rsv-page-btn is-disabled">{{ t('rev.next') }} ›</span>
            </nav>
        </section>

        <Teleport to="body">
            <transition name="rsv-fade">
                <div v-if="gallery.length" class="rsv-img-overlay" @click.self="closeGallery">
                    <div class="rsv-img-gallery" :class="{ 'is-single': gallery.length === 1 }">
                        <img v-for="(url, i) in gallery" :key="i" :src="url" alt="" class="rsv-img-zoom" />
                    </div>
                    <button type="button" class="rsv-img-close" aria-label="×" @click="closeGallery">×</button>
                </div>
            </transition>
        </Teleport>
    </div>
</template>
