<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useI18n } from '@/lib/i18n';
import '../../../css/reserva/admin.css';

const { t, localeTag } = useI18n();

interface Tag {
    id: number;
    name: string;
    color: string;
}

interface Post {
    id: number;
    title: string;
    body: string;
    body2: string | null;
    summary: string | null;
    cover_url: string | null;
    images: string[];
    image_urls: string[];
    tags: Tag[];
    created_at: string;
    author: { id: number; name: string } | null;
}

const props = defineProps<{
    posts: Post[];
    allTags: Tag[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Posts', href: '/admin/posts' }],
    },
});

const form = useForm<{
    title: string;
    body: string;
    body2: string;
    summary: string;
    cover: File | null;
    removeCover: boolean;
    images: File[];
    keepImages: string[];
    tags: string[];
}>({
    title: '',
    body: '',
    body2: '',
    summary: '',
    cover: null,
    removeCover: false,
    images: [],
    keepImages: [],
    tags: [],
});

const editingId = ref<number | null>(null);
const isEditing = computed(() => editingId.value !== null);

const coverPreview = ref<string | null>(null);
// Portada actual del post en edició (per poder veure-la i treure-la).
const editCover = ref<string | null>(null);
const imagePreviews = ref<string[]>([]);
// Imatges de galeria ja existents del post en edició (path + url públic).
const editImages = ref<{ path: string; url: string }[]>([]);
const keptExisting = computed(() => editImages.value.filter((img) => form.keepImages.includes(img.path)));
const tagInput = ref('');
const search = ref('');

// Totes les opcions d'etiqueta: catàleg + les seleccionades que encara no hi siguin.
const tagOptions = computed(() =>
    [...new Set([...props.allTags.map((t) => t.name), ...form.tags])].sort((a, b) => a.localeCompare(b)),
);

const colorOf = computed<Record<string, string>>(() =>
    Object.fromEntries(props.allTags.map((t) => [t.name, t.color])),
);

function tagColor(name: string): string {
    return colorOf.value[name] ?? '#4f46e5';
}

function chipStyle(name: string, on: boolean): Record<string, string> {
    const c = tagColor(name);
    return on
        ? { backgroundColor: c, color: '#fff', borderColor: c }
        : { backgroundColor: c + '18', color: c, borderColor: 'transparent' };
}

const filteredPosts = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) {
        return props.posts;
    }
    return props.posts.filter((p) =>
        [p.title, p.body, ...p.tags.map((t) => t.name)].join(' ').toLowerCase().includes(q),
    );
});

// Paginació: màxim 9 posts per pàgina.
const PER_PAGE = 9;
const currentPage = ref(1);
const totalPages = computed(() => Math.max(1, Math.ceil(filteredPosts.value.length / PER_PAGE)));

const pagedPosts = computed(() =>
    filteredPosts.value.slice((currentPage.value - 1) * PER_PAGE, currentPage.value * PER_PAGE),
);

// En cercar o si canvia el nombre de pàgines, tornem a una pàgina vàlida.
watch([search, totalPages], () => {
    if (currentPage.value > totalPages.value) {
        currentPage.value = totalPages.value;
    }
});

function goToPage(page: number): void {
    currentPage.value = page;
}

function clearPreviews(): void {
    if (coverPreview.value) {
        URL.revokeObjectURL(coverPreview.value);
    }
    imagePreviews.value.forEach((url) => URL.revokeObjectURL(url));
    coverPreview.value = null;
    imagePreviews.value = [];
    editImages.value = [];
    editCover.value = null;
}

function onCover(event: Event): void {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    form.cover = file;
    if (coverPreview.value) {
        URL.revokeObjectURL(coverPreview.value);
    }
    coverPreview.value = file ? URL.createObjectURL(file) : null;
    // Triar una portada nova anul·la la petició de treure l'actual.
    if (file) {
        form.removeCover = false;
    }
}

function clearNewCover(): void {
    if (coverPreview.value) {
        URL.revokeObjectURL(coverPreview.value);
    }
    coverPreview.value = null;
    form.cover = null;
}

function removeCurrentCover(): void {
    form.removeCover = true;
}

function onImages(event: Event): void {
    const input = event.target as HTMLInputElement;
    const files = Array.from(input.files ?? []);
    // Acumulem: afegim les noves a les ja triades en comptes de substituir-les.
    form.images = [...form.images, ...files];
    imagePreviews.value = [...imagePreviews.value, ...files.map((file) => URL.createObjectURL(file))];
    // Buidem l'input perquè es pugui tornar a triar (fins i tot els mateixos fitxers).
    input.value = '';
}

function removeNewImage(index: number): void {
    URL.revokeObjectURL(imagePreviews.value[index]);
    imagePreviews.value.splice(index, 1);
    form.images.splice(index, 1);
}

function removeExisting(path: string): void {
    form.keepImages = form.keepImages.filter((p) => p !== path);
}

function toggleTag(name: string): void {
    const i = form.tags.indexOf(name);
    if (i >= 0) {
        form.tags.splice(i, 1);
    } else {
        form.tags.push(name);
    }
}

function addTag(): void {
    const tag = tagInput.value.trim().replace(/,$/, '').trim();
    if (tag && tag.length <= 30 && !form.tags.includes(tag)) {
        form.tags.push(tag);
    }
    tagInput.value = '';
}

function onTagKeydown(event: KeyboardEvent): void {
    if (event.key === 'Enter' || event.key === ',') {
        event.preventDefault();
        addTag();
    }
}

function startEdit(post: Post): void {
    editingId.value = post.id;
    form.clearErrors();
    form.title = post.title;
    form.body = post.body;
    form.body2 = post.body2 ?? '';
    form.summary = post.summary ?? '';
    form.tags = post.tags.map((t) => t.name);
    form.cover = null;
    form.images = [];
    tagInput.value = '';
    clearPreviews();
    // Portada actual (es conserva tret que l'admin la tregui).
    editCover.value = post.cover_url;
    form.removeCover = false;
    // Carreguem la galeria actual del post (totes conservades per defecte).
    editImages.value = (post.images ?? []).map((path, i) => ({ path, url: post.image_urls[i] }));
    form.keepImages = [...(post.images ?? [])];
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function cancelEdit(): void {
    editingId.value = null;
    form.reset();
    form.clearErrors();
    tagInput.value = '';
    clearPreviews();
}

function formatDate(value: string): string {
    return new Date(value).toLocaleDateString(localeTag(), {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}

function submit(): void {
    addTag();
    const url = isEditing.value ? `/admin/posts/${editingId.value}` : '/admin/posts';

    form
        .transform((data) => (isEditing.value ? { ...data, _method: 'put' } : data))
        .post(url, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                editingId.value = null;
                form.reset();
                tagInput.value = '';
                clearPreviews();
            },
        });
}

function remove(id: number): void {
    if (editingId.value === id) {
        cancelEdit();
    }
    router.delete(`/admin/posts/${id}`, { preserveScroll: true });
}
</script>

<template>
    <Head :title="t('nav.posts')" />

    <div id="rsv-posts">
        <header>
            <h1>{{ t('post.title') }}</h1>
            <p>{{ t('post.subtitle') }}</p>
        </header>

        <form @submit.prevent="submit">
            <h2>{{ isEditing ? t('post.editPost') : t('post.newPost') }}</h2>

            <label for="title">{{ t('post.fieldTitle') }}</label>
            <input id="title" v-model="form.title" type="text" maxlength="255" required />
            <p v-if="form.errors.title" class="rsv-error">{{ form.errors.title }}</p>

            <label for="body">{{ t('post.body') }}</label>
            <textarea id="body" v-model="form.body" required></textarea>
            <p v-if="form.errors.body" class="rsv-error">{{ form.errors.body }}</p>

            <label for="body2">{{ t('post.body2') }}</label>
            <textarea id="body2" v-model="form.body2"></textarea>
            <p v-if="form.errors.body2" class="rsv-error">{{ form.errors.body2 }}</p>

            <label for="summary">{{ t('post.summary') }}</label>
            <textarea
                id="summary"
                v-model="form.summary"
                maxlength="500"
                rows="3"
                :placeholder="t('post.summaryPh')"
            ></textarea>
            <p class="rsv-tag">{{ form.summary.length }}/500</p>
            <p v-if="form.errors.summary" class="rsv-error">{{ form.errors.summary }}</p>

            <label>{{ t('post.tags') }}</label>
            <div v-if="tagOptions.length" class="rsv-tagpick">
                <button
                    v-for="name in tagOptions"
                    :key="name"
                    type="button"
                    :style="chipStyle(name, form.tags.includes(name))"
                    @click="toggleTag(name)"
                >
                    {{ name }}
                </button>
            </div>
            <div class="rsv-taginput">
                <input
                    id="tagfield"
                    v-model="tagInput"
                    type="text"
                    :placeholder="t('post.newTagPh')"
                    @keydown="onTagKeydown"
                    @blur="addTag"
                />
            </div>

            <label for="cover">{{ t('post.cover') }}</label>

            <!-- Portada actual (en edició) -->
            <div v-if="isEditing && editCover && !coverPreview && !form.removeCover" class="rsv-cover-current">
                <span class="rsv-tag">{{ t('post.coverCurrent') }}</span>
                <div class="rsv-thumb-wrap rsv-thumb-wrap-lg">
                    <img :src="editCover" alt="" />
                    <button type="button" class="rsv-thumb-x" @click="removeCurrentCover">×</button>
                </div>
            </div>
            <p v-else-if="isEditing && form.removeCover && !coverPreview" class="rsv-tag">
                {{ t('post.coverWillRemove') }}
            </p>

            <input :key="`cover-${editingId ?? 'new'}`" id="cover" type="file" accept="image/*" @change="onCover" />
            <div v-if="coverPreview" class="rsv-thumb-wrap rsv-thumb-wrap-lg">
                <img :src="coverPreview" alt="" />
                <button type="button" class="rsv-thumb-x" @click="clearNewCover">×</button>
            </div>
            <p v-if="form.errors.cover" class="rsv-error">{{ form.errors.cover }}</p>

            <label for="images">{{ t('post.gallery') }}</label>

            <!-- Imatges actuals del post (en edició) -->
            <div v-if="isEditing && keptExisting.length" class="rsv-gallery-current">
                <span class="rsv-tag">{{ t('post.imagesCurrent') }}</span>
                <div class="rsv-thumbs">
                    <div v-for="img in keptExisting" :key="img.path" class="rsv-thumb-wrap">
                        <img :src="img.url" alt="" />
                        <button type="button" class="rsv-thumb-x" @click="removeExisting(img.path)">×</button>
                    </div>
                </div>
            </div>

            <input :key="`images-${editingId ?? 'new'}`" id="images" type="file" accept="image/*" multiple @change="onImages" />
            <div v-if="imagePreviews.length" class="rsv-thumbs">
                <div v-for="(src, i) in imagePreviews" :key="i" class="rsv-thumb-wrap">
                    <img :src="src" alt="" />
                    <button type="button" class="rsv-thumb-x" @click="removeNewImage(i)">×</button>
                </div>
            </div>
            <p v-if="form.errors.images" class="rsv-error">{{ form.errors.images }}</p>

            <p v-if="isEditing" class="rsv-tag">{{ t('post.imagesHint') }}</p>

            <div class="rsv-form-actions">
                <button type="submit" :disabled="form.processing">
                    {{ isEditing ? t('post.saveChanges') : t('post.publish') }}
                </button>
                <button v-if="isEditing" type="button" class="rsv-cancel" @click="cancelEdit">{{ t('post.cancel') }}</button>
            </div>
        </form>

        <section>
            <div class="rsv-post-head">
                <h2>{{ t('post.published') }}</h2>
                <input v-model="search" type="search" class="rsv-search" :placeholder="t('post.searchPh')" />
            </div>

            <div v-if="filteredPosts.length" class="rsv-post-grid">
                <article v-for="post in pagedPosts" :key="post.id">
                    <div>
                        <span>{{ post.title }}</span>
                        <div class="rsv-actions">
                            <button type="button" class="rsv-edit" @click="startEdit(post)">{{ t('post.edit') }}</button>
                            <button type="button" class="rsv-del" @click="remove(post.id)">{{ t('post.delete') }}</button>
                        </div>
                    </div>
                    <img v-if="post.cover_url" :src="post.cover_url" alt="" class="rsv-cover-thumb" />
                    <div v-if="post.tags.length" class="rsv-taglist">
                        <span
                            v-for="tag in post.tags"
                            :key="tag.id"
                            class="rsv-tagchip"
                            :style="{ backgroundColor: tag.color + '22', color: tag.color }"
                        >{{ tag.name }}</span>
                    </div>
                    <p class="rsv-post-body">{{ post.body }}</p>
                    <span class="rsv-tag">{{ post.author?.name ?? t('post.team') }} · {{ formatDate(post.created_at) }}</span>
                </article>
            </div>
            <div v-else class="rsv-empty">{{ t('post.empty') }}</div>

            <div v-if="totalPages > 1" class="rsv-pagination">
                <button type="button" :disabled="currentPage === 1" @click="goToPage(currentPage - 1)">‹</button>
                <button
                    v-for="page in totalPages"
                    :key="page"
                    type="button"
                    :class="{ 'is-active': page === currentPage }"
                    @click="goToPage(page)"
                >
                    {{ page }}
                </button>
                <button type="button" :disabled="currentPage === totalPages" @click="goToPage(currentPage + 1)">›</button>
            </div>
        </section>
    </div>
</template>
