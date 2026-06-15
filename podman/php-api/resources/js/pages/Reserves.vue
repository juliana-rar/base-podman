<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref, type Ref } from 'vue';
import ImagesField, { type ImageItem } from '@/components/ImagesField.vue';
import { useI18n } from '@/lib/i18n';
import '../../css/reserva/reserves.css';

interface Reservation {
    id: number;
    note: string | null;
    rating: number | null;
    review: string | null;
    review_images: string[] | null;
    review_image_urls: string[];
    slot: { id: number; starts_at: string; notes: string | null };
    service: { id: number; name: string } | null;
}

defineProps<{ reservations: Reservation[] }>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Reserves', href: '/reserves' }],
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

// --- Galeria d'imatges de la valoració (existents) ---
function imagesOf(reservation: Reservation): ImageItem[] {
    return (reservation.review_images ?? []).map((path, i) => ({ path, url: reservation.review_image_urls[i] ?? '' }));
}

function imagesPayload(items: ImageItem[]): { images: File[]; order: string } {
    const images: File[] = [];
    const order: string[] = [];
    for (const item of items) {
        if (item.file) {
            order.push(`new:${images.length}`);
            images.push(item.file);
        } else if (item.path) {
            order.push(item.path);
        }
    }
    return { images, order: JSON.stringify(order) };
}

function clearImages(list: Ref<ImageItem[]>): void {
    for (const item of list.value) {
        if (item.file && item.url.startsWith('blob:')) {
            URL.revokeObjectURL(item.url);
        }
    }
    list.value = [];
}

function imageError(errors: Record<string, string>): string | null {
    const key = Object.keys(errors).find((k) => k === 'images' || k.startsWith('images.'));
    return key ? errors[key] : null;
}

// --- Edició de la valoració ---
const editingId = ref<number | null>(null);
const reviewForm = useForm<{ rating: number; review: string }>({ rating: 0, review: '' });
const reviewImages = ref<ImageItem[]>([]);

function startReview(reservation: Reservation): void {
    editingId.value = reservation.id;
    reviewForm.reset();
    reviewForm.clearErrors();
    reviewForm.rating = reservation.rating ?? 0;
    reviewForm.review = reservation.review ?? '';
    clearImages(reviewImages);
    reviewImages.value = imagesOf(reservation);
}

function saveReview(): void {
    if (editingId.value === null) {
        return;
    }
    const { images, order } = imagesPayload(reviewImages.value);
    reviewForm.transform((data) => ({ ...data, images, order })).post(`/reservas/${editingId.value}/valoracio`, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            editingId.value = null;
            clearImages(reviewImages);
        },
    });
}

function cancelReview(): void {
    editingId.value = null;
    clearImages(reviewImages);
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

function onKey(event: KeyboardEvent): void {
    if (gallery.value.length && event.key === 'Escape') {
        closeGallery();
    }
}

onMounted(() => window.addEventListener('keydown', onKey));
onUnmounted(() => window.removeEventListener('keydown', onKey));
</script>

<template>
    <Head :title="t('res.done')" />

    <div id="rsv-reserves">
        <header>
            <h1>{{ t('res.done') }}</h1>
            <p>{{ t('res.doneSubtitle') }}</p>
        </header>

        <section>
            <div v-if="reservations.length" class="rsv-rsv-list">
                <div v-for="reservation in reservations" :key="reservation.id" class="rsv-rsv-item">
                    <div class="rsv-rsv-info">
                        <span class="rsv-rsv-date">{{ fullLabel(reservation.slot.starts_at) }}</span>
                        <span v-if="reservation.service" class="rsv-rsv-note">🔖 {{ reservation.service.name }}</span>
                        <span v-if="reservation.note" class="rsv-rsv-note">💬 {{ reservation.note }}</span>
                    </div>

                    <!-- Valoració existent -->
                    <div v-if="reservation.rating && editingId !== reservation.id" class="rsv-rsv-review">
                        <div class="rsv-stars" :aria-label="`${reservation.rating}/5`">
                            <span v-for="n in 5" :key="n" :class="{ on: n <= (reservation.rating ?? 0) }">★</span>
                        </div>
                        <p v-if="reservation.review" class="rsv-rsv-reviewtext">{{ reservation.review }}</p>
                        <div v-if="reservation.review_image_urls.length" class="rsv-rsv-thumbs">
                            <img
                                v-for="(url, i) in reservation.review_image_urls"
                                :key="i"
                                :src="url"
                                alt=""
                                :title="t('res.zoomImg')"
                                @click="openGallery(reservation.review_image_urls, i)"
                            />
                        </div>
                        <button type="button" class="rsv-rsv-btn" @click="startReview(reservation)">{{ t('res.editReview') }}</button>
                    </div>

                    <!-- Encara sense valorar -->
                    <button
                        v-else-if="editingId !== reservation.id"
                        type="button"
                        class="rsv-rsv-btn primary"
                        @click="startReview(reservation)"
                    >
                        {{ t('res.addReview') }}
                    </button>

                    <!-- Formulari de valoració -->
                    <div v-else class="rsv-rsv-form">
                        <div class="rsv-stars-pick" role="radiogroup" :aria-label="t('res.rating')">
                            <button
                                v-for="n in 5"
                                :key="n"
                                type="button"
                                :class="{ on: n <= reviewForm.rating }"
                                :aria-label="`${n}/5`"
                                @click="reviewForm.rating = n"
                            >
                                ★
                            </button>
                        </div>
                        <p v-if="reviewForm.errors.rating" class="rsv-rsv-err">{{ reviewForm.errors.rating }}</p>

                        <textarea
                            v-model="reviewForm.review"
                            class="rsv-rsv-textarea"
                            rows="3"
                            maxlength="2000"
                            :placeholder="t('res.reviewPh')"
                        ></textarea>
                        <p v-if="reviewForm.errors.review" class="rsv-rsv-err">{{ reviewForm.errors.review }}</p>

                        <ImagesField v-model="reviewImages" />
                        <p v-if="imageError(reviewForm.errors)" class="rsv-rsv-err">{{ imageError(reviewForm.errors) }}</p>

                        <div class="rsv-rsv-actions">
                            <button type="button" class="rsv-rsv-btn primary" :disabled="reviewForm.processing" @click="saveReview">
                                {{ t('res.saveReview') }}
                            </button>
                            <button type="button" class="rsv-rsv-btn" @click="cancelReview">{{ t('res.cancelReview') }}</button>
                        </div>
                    </div>
                </div>
            </div>
            <div v-else class="rsv-rsv-empty">{{ t('res.doneEmpty') }}</div>
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
