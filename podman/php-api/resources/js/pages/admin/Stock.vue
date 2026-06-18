<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, type Ref } from 'vue';
import ImagesField, { type ImageItem } from '@/components/ImagesField.vue';
import { useI18n } from '@/lib/i18n';
import '../../../css/reserva/admin.css';

const { t } = useI18n();

// Entitat que té galeria d'imatges (per construir l'editor i el payload).
interface WithImages {
    image_path: string | null;
    images: string[] | null;
    image_urls: string[];
}

interface Stock extends WithImages {
    id: number;
    name: string;
    quantity: number;
    price: string;
    description: string | null;
    url: string | null;
    stock_category_id: number | null;
}

interface Category extends WithImages {
    id: number;
    name: string;
    description: string | null;
    url: string | null;
    stocks: Stock[];
}

// --- Galeria d'imatges (compartit pels formularis de categoria i article) ---

// Construeix la llista inicial d'imatges existents d'una entitat.
function imagesOf(entity: WithImages): ImageItem[] {
    const paths = entity.images && entity.images.length ? entity.images : entity.image_path ? [entity.image_path] : [];
    return paths.map((path, i) => ({ path, url: entity.image_urls[i] ?? '' }));
}

// Converteix la llista de l'editor en el payload que espera el servidor.
// `order` viatja com a string JSON perquè un array buit no es perdria en multipart.
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

// Allibera els object URLs de les imatges noves i buida la llista.
function clearImages(list: Ref<ImageItem[]>): void {
    for (const item of list.value) {
        if (item.file && item.url.startsWith('blob:')) {
            URL.revokeObjectURL(item.url);
        }
    }
    list.value = [];
}

// Primer error de validació relacionat amb la galeria (clau `images` o `images.N`).
function imageError(errors: Record<string, string>): string | null {
    const key = Object.keys(errors).find((k) => k === 'images' || k.startsWith('images.'));
    return key ? errors[key] : null;
}

const props = defineProps<{
    categories: Category[];
    uncategorized: Stock[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Stock', href: '/admin/stock' }],
    },
});

// Grups que es mostren al catàleg: categories reals + un grup final per als articles sense categoria.
const groups = computed(() => [
    ...props.categories.map((c) => ({
        id: c.id as number | null,
        name: c.name,
        description: c.description,
        url: c.url,
        image_path: c.image_path,
        images: c.images,
        image_urls: c.image_urls,
        isReal: true,
        stocks: c.stocks,
    })),
    ...(props.uncategorized.length
        ? [
              {
                  id: null as number | null,
                  name: t('stk.noCategory'),
                  description: null as string | null,
                  url: null as string | null,
                  image_path: null as string | null,
                  images: null as string[] | null,
                  image_urls: [] as string[],
                  isReal: false,
                  stocks: props.uncategorized,
              },
          ]
        : []),
]);

// =========================================================================
//  Categories
// =========================================================================
const catForm = useForm<{ name: string; description: string }>({ name: '', description: '' });
const catImages = ref<ImageItem[]>([]);

function createCategory(): void {
    const { images, order } = imagesPayload(catImages.value);
    catForm.transform((data) => ({ ...data, images, order })).post('/admin/stock-categories', {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            catForm.reset();
            clearImages(catImages);
        },
    });
}

const editCatId = ref<number | null>(null);
const editCatForm = useForm<{ name: string; description: string }>({ name: '', description: '' });
const editCatImages = ref<ImageItem[]>([]);

function startEditCategory(category: Category): void {
    editCatId.value = category.id;
    editCatForm.reset();
    editCatForm.clearErrors();
    editCatForm.name = category.name;
    editCatForm.description = category.description ?? '';
    clearImages(editCatImages);
    editCatImages.value = imagesOf(category);
}

function saveEditCategory(): void {
    if (editCatId.value === null) return;
    const { images, order } = imagesPayload(editCatImages.value);
    editCatForm.transform((data) => ({ ...data, images, order })).post(`/admin/stock-categories/${editCatId.value}`, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            editCatId.value = null;
            clearImages(editCatImages);
        },
    });
}

function cancelEditCategory(): void {
    editCatId.value = null;
    clearImages(editCatImages);
}

function removeCategory(id: number): void {
    router.delete(`/admin/stock-categories/${id}`, { preserveScroll: true });
}

// =========================================================================
//  Articles — crear
// =========================================================================
const form = useForm<{
    name: string;
    quantity: number;
    price: number;
    description: string;
    stock_category_id: number | '';
}>({
    name: '',
    quantity: 0,
    price: 0,
    description: '',
    stock_category_id: '',
});
const newImages = ref<ImageItem[]>([]);

function create(): void {
    const { images, order } = imagesPayload(newImages.value);
    form.transform((data) => ({ ...data, images, order })).post('/admin/stock', {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            form.reset();
            clearImages(newImages);
        },
    });
}

// =========================================================================
//  Articles — editar
// =========================================================================
const editId = ref<number | null>(null);
const editForm = useForm<{
    name: string;
    quantity: number;
    price: number;
    description: string;
    stock_category_id: number | '';
}>({
    name: '',
    quantity: 0,
    price: 0,
    description: '',
    stock_category_id: '',
});
const editImages = ref<ImageItem[]>([]);

function startEdit(stock: Stock): void {
    editId.value = stock.id;
    editForm.reset();
    editForm.clearErrors();
    editForm.name = stock.name;
    editForm.quantity = stock.quantity;
    editForm.price = Number(stock.price);
    editForm.description = stock.description ?? '';
    editForm.stock_category_id = stock.stock_category_id ?? '';
    clearImages(editImages);
    editImages.value = imagesOf(stock);
}

function saveEdit(): void {
    if (editId.value === null) return;
    const { images, order } = imagesPayload(editImages.value);
    editForm.transform((data) => ({ ...data, images, order })).post(`/admin/stock/${editId.value}`, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            editId.value = null;
            clearImages(editImages);
        },
    });
}

function cancelEdit(): void {
    editId.value = null;
    clearImages(editImages);
}

function remove(id: number): void {
    router.delete(`/admin/stock/${id}`, { preserveScroll: true });
}

// =========================================================================
//  Visor d'imatges del catàleg (lightbox): en clicar una miniatura es mostra
//  tota la galeria ampliada. Es tanca amb la tecla Escape o clicant el fons.
// =========================================================================
const galleryImages = ref<string[]>([]);

function openGallery(urls: string[]): void {
    if (!urls.length) return;
    galleryImages.value = urls;
}

function closeGallery(): void {
    galleryImages.value = [];
}

function onGalleryKey(event: KeyboardEvent): void {
    if (galleryImages.value.length && event.key === 'Escape') {
        closeGallery();
    }
}

onMounted(() => window.addEventListener('keydown', onGalleryKey));
onUnmounted(() => window.removeEventListener('keydown', onGalleryKey));
</script>

<template>
    <Head :title="t('stk.title')" />

    <div id="rsv-stock">
        <header>
            <h1>{{ t('stk.title') }}</h1>
            <p>{{ t('stk.subtitle') }}</p>
        </header>

        <section>
            <!-- ===================== Categories ===================== -->
            <h2 class="rsv-srv-center">{{ t('stk.categories') }}</h2>
            <div class="rsv-srv-form">
                <input
                    v-model="catForm.name"
                    type="text"
                    maxlength="100"
                    :placeholder="t('stk.categoryNamePh')"
                    @keydown.enter.prevent="createCategory"
                />
                <textarea
                    v-model="catForm.description"
                    class="rsv-srv-descfield"
                    rows="2"
                    maxlength="2000"
                    :placeholder="t('stk.catInfoPh')"
                ></textarea>
                <div class="rsv-srv-imgzone">
                    <span class="rsv-srv-imgzone-label">{{ t('stk.imagesLabel') }}</span>
                    <ImagesField v-model="catImages" />
                </div>
                <button type="button" class="rsv-edit" :disabled="catForm.processing" @click="createCategory">
                    {{ t('stk.addCategory') }}
                </button>
            </div>
            <p v-if="catForm.errors.name" class="rsv-error">{{ catForm.errors.name }}</p>
            <p v-if="catForm.errors.description" class="rsv-error">{{ catForm.errors.description }}</p>
            <p v-if="imageError(catForm.errors)" class="rsv-error">{{ imageError(catForm.errors) }}</p>

            <!-- ===================== Nou article ===================== -->
            <h2 class="rsv-srv-center">{{ t('stk.new') }}</h2>
            <div class="rsv-srv-form">
                <input
                    v-model="form.name"
                    type="text"
                    maxlength="100"
                    :placeholder="t('stk.namePh')"
                    @keydown.enter.prevent="create"
                />
                <span class="rsv-srv-num">
                    <input
                        v-model.number="form.quantity"
                        type="number"
                        min="0"
                        step="1"
                        :placeholder="t('stk.qtyPh')"
                        @keydown.enter.prevent="create"
                    />
                    <i class="rsv-srv-unit">{{ t('stk.unit') }}</i>
                </span>
                <span class="rsv-srv-num">
                    <input
                        v-model.number="form.price"
                        type="number"
                        min="0"
                        step="0.01"
                        :placeholder="t('stk.pricePh')"
                        @keydown.enter.prevent="create"
                    />
                    <i class="rsv-srv-unit">€</i>
                </span>
                <select v-model="form.stock_category_id" class="rsv-srv-select">
                    <option value="">{{ t('stk.noCategory') }}</option>
                    <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
                <textarea
                    v-model="form.description"
                    class="rsv-srv-descfield"
                    rows="2"
                    maxlength="2000"
                    :placeholder="t('stk.infoPh')"
                ></textarea>
                <div class="rsv-srv-imgzone">
                    <span class="rsv-srv-imgzone-label">{{ t('stk.imagesLabel') }}</span>
                    <ImagesField v-model="newImages" />
                </div>
                <button type="button" class="rsv-edit" :disabled="form.processing" @click="create">{{ t('stk.add') }}</button>
            </div>
            <p v-if="form.errors.name" class="rsv-error">{{ form.errors.name }}</p>
            <p v-if="form.errors.quantity" class="rsv-error">{{ form.errors.quantity }}</p>
            <p v-if="form.errors.price" class="rsv-error">{{ form.errors.price }}</p>
            <p v-if="form.errors.description" class="rsv-error">{{ form.errors.description }}</p>
            <p v-if="imageError(form.errors)" class="rsv-error">{{ imageError(form.errors) }}</p>

            <!-- ===================== Catàleg per categories ===================== -->
            <h2>{{ t('stk.catalog') }}</h2>
            <div v-if="groups.length" class="rsv-srv-groups">
                <div v-for="group in groups" :key="group.id ?? 'none'" class="rsv-srv-group">
                    <div class="rsv-srv-grouphead">
                        <template v-if="group.isReal && editCatId === group.id">
                            <input
                                v-model="editCatForm.name"
                                type="text"
                                maxlength="100"
                                @keydown.enter.prevent="saveEditCategory"
                            />
                            <ImagesField v-model="editCatImages" />
                            <textarea
                                v-model="editCatForm.description"
                                class="rsv-srv-descfield"
                                rows="2"
                                maxlength="2000"
                                :placeholder="t('stk.catInfoPh')"
                            ></textarea>
                            <button type="button" class="rsv-edit" :disabled="editCatForm.processing" @click="saveEditCategory">
                                {{ t('stk.save') }}
                            </button>
                            <button type="button" class="rsv-del" @click="cancelEditCategory">{{ t('stk.cancel') }}</button>
                        </template>
                        <template v-else>
                            <div v-if="group.isReal" class="rsv-srv-thumb">
                                <img
                                    v-if="group.url"
                                    :src="group.url"
                                    alt=""
                                    class="rsv-srv-zoomable"
                                    :title="t('stk.zoom')"
                                    @click="openGallery(group.image_urls)"
                                />
                                <span v-else class="rsv-srv-noimg">—</span>
                            </div>
                            <h3>{{ group.name }}</h3>
                            <template v-if="group.isReal">
                                <button type="button" class="rsv-edit" @click="startEditCategory(group as Category)">
                                    {{ t('stk.edit') }}
                                </button>
                                <button type="button" class="rsv-del" @click="removeCategory(group.id as number)">
                                    {{ t('stk.delete') }}
                                </button>
                            </template>
                        </template>
                    </div>
                    <p v-if="editCatId === group.id && editCatForm.errors.name" class="rsv-error">{{ editCatForm.errors.name }}</p>
                    <p v-if="editCatId === group.id && editCatForm.errors.description" class="rsv-error">{{ editCatForm.errors.description }}</p>
                    <p v-if="editCatId === group.id && imageError(editCatForm.errors)" class="rsv-error">{{ imageError(editCatForm.errors) }}</p>

                    <p v-if="group.description && editCatId !== group.id" class="rsv-srv-catdesc">{{ group.description }}</p>

                    <div v-if="group.stocks.length" class="rsv-srv-rows">
                        <div v-for="stock in group.stocks" :key="stock.id" class="rsv-srv-row">
                            <!-- Vista normal -->
                            <template v-if="editId !== stock.id">
                                <div class="rsv-srv-thumb">
                                    <img
                                        v-if="stock.url"
                                        :src="stock.url"
                                        alt=""
                                        class="rsv-srv-zoomable"
                                        :title="t('stk.zoom')"
                                        @click="openGallery(stock.image_urls)"
                                    />
                                    <span v-else class="rsv-srv-noimg">—</span>
                                </div>
                                <span class="rsv-srv-name">{{ stock.name }}</span>
                                <span class="rsv-srv-price">{{ stock.price }} €</span>
                                <span class="rsv-srv-dur">{{ stock.quantity }} {{ t('stk.unit') }}</span>
                                <span class="rsv-srv-spacer"></span>
                                <button type="button" class="rsv-edit" @click="startEdit(stock)">{{ t('stk.edit') }}</button>
                                <button type="button" class="rsv-del" @click="remove(stock.id)">{{ t('stk.delete') }}</button>
                                <p v-if="stock.description" class="rsv-srv-desc">{{ stock.description }}</p>
                            </template>

                            <!-- Vista edició -->
                            <template v-else>
                                <div class="rsv-srv-editfields">
                                    <input v-model="editForm.name" type="text" maxlength="100" @keydown.enter.prevent="saveEdit" />
                                    <span class="rsv-srv-num">
                                        <input
                                            v-model.number="editForm.quantity"
                                            type="number"
                                            min="0"
                                            step="1"
                                            :placeholder="t('stk.qtyPh')"
                                            @keydown.enter.prevent="saveEdit"
                                        />
                                        <i class="rsv-srv-unit">{{ t('stk.unit') }}</i>
                                    </span>
                                    <span class="rsv-srv-num">
                                        <input
                                            v-model.number="editForm.price"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            :placeholder="t('stk.pricePh')"
                                            @keydown.enter.prevent="saveEdit"
                                        />
                                        <i class="rsv-srv-unit">€</i>
                                    </span>
                                    <select v-model="editForm.stock_category_id" class="rsv-srv-select">
                                        <option value="">{{ t('stk.noCategory') }}</option>
                                        <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                                    </select>
                                    <ImagesField v-model="editImages" />
                                    <textarea
                                        v-model="editForm.description"
                                        class="rsv-srv-descfield"
                                        rows="2"
                                        maxlength="2000"
                                        :placeholder="t('stk.infoPh')"
                                    ></textarea>
                                    <p v-if="editForm.errors.name" class="rsv-error">{{ editForm.errors.name }}</p>
                                    <p v-if="editForm.errors.quantity" class="rsv-error">{{ editForm.errors.quantity }}</p>
                                    <p v-if="editForm.errors.price" class="rsv-error">{{ editForm.errors.price }}</p>
                                    <p v-if="editForm.errors.description" class="rsv-error">{{ editForm.errors.description }}</p>
                                    <p v-if="imageError(editForm.errors)" class="rsv-error">{{ imageError(editForm.errors) }}</p>
                                </div>
                                <button type="button" class="rsv-edit" :disabled="editForm.processing" @click="saveEdit">{{ t('stk.save') }}</button>
                                <button type="button" class="rsv-del" @click="cancelEdit">{{ t('stk.cancel') }}</button>
                            </template>
                        </div>
                    </div>
                    <div v-else class="rsv-empty">{{ t('stk.catEmpty') }}</div>
                </div>
            </div>
            <div v-else class="rsv-empty">{{ t('stk.empty') }}</div>
        </section>

        <Teleport to="body">
            <transition name="rsv-srv-fade">
                <div v-if="galleryImages.length" class="rsv-srv-overlay" @click.self="closeGallery">
                    <div class="rsv-srv-gallery" :class="{ 'is-single': galleryImages.length === 1 }">
                        <img v-for="(url, i) in galleryImages" :key="i" :src="url" alt="" class="rsv-srv-zoom" />
                    </div>
                    <button type="button" class="rsv-srv-close" aria-label="×" @click="closeGallery">×</button>
                </div>
            </transition>
        </Teleport>
    </div>
</template>
