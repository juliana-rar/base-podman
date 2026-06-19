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

interface ServiceOption extends WithImages {
    id: number;
    name: string;
    price: string;
    duration_minutes: number;
    description: string | null;
    url: string | null;
}

interface Service extends WithImages {
    id: number;
    name: string;
    price: string;
    vat_rate: string;
    duration_minutes: number;
    description: string | null;
    url: string | null;
    reservations_count: number;
    service_category_id: number | null;
    options: ServiceOption[];
}

// Tipus d'IVA disponibles (%): general, reduït, superreduït i exempt.
const VAT_RATES = [21, 10, 4, 0];

interface Category extends WithImages {
    id: number;
    name: string;
    description: string | null;
    url: string | null;
    services: Service[];
}

// --- Galeria d'imatges (compartit pels formularis de categoria, servei i opció) ---

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
    uncategorized: Service[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Serveis', href: '/admin/serveis' }],
    },
});

// Grups que es mostren al catàleg: categories reals + un grup final per als serveis sense categoria.
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
        services: c.services,
    })),
    ...(props.uncategorized.length
        ? [
              {
                  id: null as number | null,
                  name: t('srv.noCategory'),
                  description: null as string | null,
                  url: null as string | null,
                  image_path: null as string | null,
                  images: null as string[] | null,
                  image_urls: [] as string[],
                  isReal: false,
                  services: props.uncategorized,
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
    catForm.transform((data) => ({ ...data, images, order })).post('/admin/serveis-categories', {
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
    editCatForm.transform((data) => ({ ...data, images, order })).post(`/admin/serveis-categories/${editCatId.value}`, {
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
    router.delete(`/admin/serveis-categories/${id}`, { preserveScroll: true });
}

// =========================================================================
//  Serveis — crear
// =========================================================================
const form = useForm<{
    name: string;
    price: number;
    vat_rate: number;
    duration_minutes: number;
    description: string;
    service_category_id: number | '';
}>({
    name: '',
    price: 0,
    vat_rate: 21,
    duration_minutes: 0,
    description: '',
    service_category_id: '',
});
const newImages = ref<ImageItem[]>([]);

function create(): void {
    const { images, order } = imagesPayload(newImages.value);
    form.transform((data) => ({ ...data, images, order })).post('/admin/serveis', {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            form.reset();
            clearImages(newImages);
        },
    });
}

// =========================================================================
//  Serveis — editar
// =========================================================================
const editId = ref<number | null>(null);
const editForm = useForm<{
    name: string;
    price: number;
    vat_rate: number;
    duration_minutes: number;
    description: string;
    service_category_id: number | '';
}>({
    name: '',
    price: 0,
    vat_rate: 21,
    duration_minutes: 0,
    description: '',
    service_category_id: '',
});
const editImages = ref<ImageItem[]>([]);

// Durada repartida en hores i minuts; es desa sempre com a total de minuts.
const newHours = computed({
    get: () => Math.floor(form.duration_minutes / 60),
    set: (h: number) => {
        form.duration_minutes = (Number(h) || 0) * 60 + (form.duration_minutes % 60);
    },
});
const newMinutes = computed({
    get: () => form.duration_minutes % 60,
    set: (m: number) => {
        form.duration_minutes = Math.floor(form.duration_minutes / 60) * 60 + (Number(m) || 0);
    },
});
const editHours = computed({
    get: () => Math.floor(editForm.duration_minutes / 60),
    set: (h: number) => {
        editForm.duration_minutes = (Number(h) || 0) * 60 + (editForm.duration_minutes % 60);
    },
});
const editMinutes = computed({
    get: () => editForm.duration_minutes % 60,
    set: (m: number) => {
        editForm.duration_minutes = Math.floor(editForm.duration_minutes / 60) * 60 + (Number(m) || 0);
    },
});

// Mostra una durada en minuts com a "1 h 30 min" / "45 min".
function formatDuration(total: number): string {
    const h = Math.floor(total / 60);
    const m = total % 60;
    if (h && m) return `${h} ${t('srv.hours')} ${m} ${t('srv.minutes')}`;
    if (h) return `${h} ${t('srv.hours')}`;
    return `${m} ${t('srv.minutes')}`;
}

function startEdit(service: Service): void {
    editId.value = service.id;
    editForm.reset();
    editForm.clearErrors();
    editForm.name = service.name;
    editForm.price = Number(service.price);
    editForm.vat_rate = Number(service.vat_rate);
    editForm.duration_minutes = service.duration_minutes;
    editForm.description = service.description ?? '';
    editForm.service_category_id = service.service_category_id ?? '';
    clearImages(editImages);
    editImages.value = imagesOf(service);
}

function saveEdit(): void {
    if (editId.value === null) return;
    const { images, order } = imagesPayload(editImages.value);
    editForm.transform((data) => ({ ...data, images, order })).post(`/admin/serveis/${editId.value}`, {
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
    router.delete(`/admin/serveis/${id}`, { preserveScroll: true });
}

// =========================================================================
//  Opcions dels serveis
// =========================================================================
// --- Afegir opció ---
const optionFor = ref<number | null>(null);
const optionForm = useForm<{ name: string; price: number; duration_minutes: number; description: string }>({
    name: '',
    price: 0,
    duration_minutes: 0,
    description: '',
});
const optionImages = ref<ImageItem[]>([]);

function startAddOption(serviceId: number): void {
    optionFor.value = serviceId;
    optionForm.reset();
    optionForm.clearErrors();
    clearImages(optionImages);
}

function saveOption(): void {
    if (optionFor.value === null) return;
    const { images, order } = imagesPayload(optionImages.value);
    optionForm
        .transform((data) => ({ ...data, service_id: optionFor.value, images, order }))
        .post('/admin/serveis-options', {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                optionFor.value = null;
                clearImages(optionImages);
            },
        });
}

function cancelAddOption(): void {
    optionFor.value = null;
    clearImages(optionImages);
}

// --- Editar opció ---
const editOptionId = ref<number | null>(null);
const editOptionForm = useForm<{ name: string; price: number; duration_minutes: number; description: string }>({
    name: '',
    price: 0,
    duration_minutes: 0,
    description: '',
});
const editOptionImages = ref<ImageItem[]>([]);

function startEditOption(option: ServiceOption): void {
    editOptionId.value = option.id;
    editOptionForm.reset();
    editOptionForm.clearErrors();
    editOptionForm.name = option.name;
    editOptionForm.price = Number(option.price);
    editOptionForm.duration_minutes = option.duration_minutes;
    editOptionForm.description = option.description ?? '';
    clearImages(editOptionImages);
    editOptionImages.value = imagesOf(option);
}

function saveEditOption(): void {
    if (editOptionId.value === null) return;
    const { images, order } = imagesPayload(editOptionImages.value);
    editOptionForm.transform((data) => ({ ...data, images, order })).post(`/admin/serveis-options/${editOptionId.value}`, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            editOptionId.value = null;
            clearImages(editOptionImages);
        },
    });
}

function cancelEditOption(): void {
    editOptionId.value = null;
    clearImages(editOptionImages);
}

function removeOption(id: number): void {
    router.delete(`/admin/serveis-options/${id}`, { preserveScroll: true });
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

// Durada de les opcions repartida en hores i minuts.
const optHours = computed({
    get: () => Math.floor(optionForm.duration_minutes / 60),
    set: (h: number) => {
        optionForm.duration_minutes = (Number(h) || 0) * 60 + (optionForm.duration_minutes % 60);
    },
});
const optMinutes = computed({
    get: () => optionForm.duration_minutes % 60,
    set: (m: number) => {
        optionForm.duration_minutes = Math.floor(optionForm.duration_minutes / 60) * 60 + (Number(m) || 0);
    },
});
const editOptHours = computed({
    get: () => Math.floor(editOptionForm.duration_minutes / 60),
    set: (h: number) => {
        editOptionForm.duration_minutes = (Number(h) || 0) * 60 + (editOptionForm.duration_minutes % 60);
    },
});
const editOptMinutes = computed({
    get: () => editOptionForm.duration_minutes % 60,
    set: (m: number) => {
        editOptionForm.duration_minutes = Math.floor(editOptionForm.duration_minutes / 60) * 60 + (Number(m) || 0);
    },
});
</script>

<template>
    <Head :title="t('srv.title')" />

    <div id="rsv-serveis">
        <header>
            <h1>{{ t('srv.title') }}</h1>
            <p>{{ t('srv.subtitle') }}</p>
        </header>

        <section>
            <!-- ===================== Categories ===================== -->
            <h2 class="rsv-srv-center">{{ t('srv.categories') }}</h2>
            <div class="rsv-srv-form">
                <input
                    v-model="catForm.name"
                    type="text"
                    maxlength="100"
                    :placeholder="t('srv.categoryNamePh')"
                    @keydown.enter.prevent="createCategory"
                />
                <textarea
                    v-model="catForm.description"
                    class="rsv-srv-descfield"
                    rows="2"
                    maxlength="2000"
                    :placeholder="t('srv.catInfoPh')"
                ></textarea>
                <div class="rsv-srv-imgzone">
                    <span class="rsv-srv-imgzone-label">{{ t('srv.imageOpt') }}</span>
                    <ImagesField v-model="catImages" />
                </div>
                <button type="button" class="rsv-edit" :disabled="catForm.processing" @click="createCategory">
                    {{ t('srv.addCategory') }}
                </button>
            </div>
            <p v-if="catForm.errors.name" class="rsv-error">{{ catForm.errors.name }}</p>
            <p v-if="catForm.errors.description" class="rsv-error">{{ catForm.errors.description }}</p>
            <p v-if="imageError(catForm.errors)" class="rsv-error">{{ imageError(catForm.errors) }}</p>

            <!-- ===================== Nou servei ===================== -->
            <h2 class="rsv-srv-center">{{ t('srv.new') }}</h2>
            <div class="rsv-srv-form">
                <input
                    v-model="form.name"
                    type="text"
                    maxlength="100"
                    :placeholder="t('srv.namePh')"
                    @keydown.enter.prevent="create"
                />
                <span class="rsv-srv-num">
                    <input
                        v-model.number="form.price"
                        type="number"
                        min="0"
                        step="0.01"
                        :placeholder="t('srv.pricePh')"
                        @keydown.enter.prevent="create"
                    />
                    <i class="rsv-srv-unit">€</i>
                </span>
                <select v-model.number="form.vat_rate" class="rsv-srv-select" :title="t('srv.vat')">
                    <option v-for="r in VAT_RATES" :key="r" :value="r">{{ t('srv.vat') }} {{ r }}%</option>
                </select>
                <div class="rsv-srv-durfield" :title="t('srv.durationPh')">
                    <span class="rsv-srv-num">
                        <input
                            v-model.number="newHours"
                            type="number"
                            min="0"
                            step="1"
                            placeholder="0"
                            @keydown.enter.prevent="create"
                        />
                        <i class="rsv-srv-unit">{{ t('srv.hours') }}</i>
                    </span>
                    <span class="rsv-srv-num">
                        <input
                            v-model.number="newMinutes"
                            type="number"
                            min="0"
                            max="59"
                            step="5"
                            placeholder="0"
                            @keydown.enter.prevent="create"
                        />
                        <i class="rsv-srv-unit">{{ t('srv.minutes') }}</i>
                    </span>
                </div>
                <select v-model="form.service_category_id" class="rsv-srv-select">
                    <option value="">{{ t('srv.noCategory') }}</option>
                    <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
                <textarea
                    v-model="form.description"
                    class="rsv-srv-descfield"
                    rows="2"
                    maxlength="2000"
                    :placeholder="t('srv.infoPh')"
                ></textarea>
                <div class="rsv-srv-imgzone">
                    <span class="rsv-srv-imgzone-label">{{ t('srv.imageOpt') }}</span>
                    <ImagesField v-model="newImages" />
                </div>
                <button type="button" class="rsv-edit" :disabled="form.processing" @click="create">{{ t('srv.add') }}</button>
            </div>
            <p v-if="form.errors.name" class="rsv-error">{{ form.errors.name }}</p>
            <p v-if="form.errors.price" class="rsv-error">{{ form.errors.price }}</p>
            <p v-if="form.errors.duration_minutes" class="rsv-error">{{ form.errors.duration_minutes }}</p>
            <p v-if="form.errors.description" class="rsv-error">{{ form.errors.description }}</p>
            <p v-if="imageError(form.errors)" class="rsv-error">{{ imageError(form.errors) }}</p>

            <!-- ===================== Catàleg per categories ===================== -->
            <h2>{{ t('srv.catalog') }}</h2>
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
                                :placeholder="t('srv.catInfoPh')"
                            ></textarea>
                            <button type="button" class="rsv-edit" :disabled="editCatForm.processing" @click="saveEditCategory">
                                {{ t('srv.save') }}
                            </button>
                            <button type="button" class="rsv-del" @click="cancelEditCategory">{{ t('srv.cancel') }}</button>
                        </template>
                        <template v-else>
                            <div v-if="group.isReal" class="rsv-srv-thumb">
                                <img
                                    v-if="group.url"
                                    :src="group.url"
                                    alt=""
                                    class="rsv-srv-zoomable"
                                    :title="t('srv.zoom')"
                                    @click="openGallery(group.image_urls)"
                                />
                                <span v-else class="rsv-srv-noimg">—</span>
                            </div>
                            <h3>{{ group.name }}</h3>
                            <template v-if="group.isReal">
                                <button type="button" class="rsv-edit" @click="startEditCategory(group as Category)">
                                    {{ t('srv.edit') }}
                                </button>
                                <button type="button" class="rsv-del" @click="removeCategory(group.id as number)">
                                    {{ t('srv.delete') }}
                                </button>
                            </template>
                        </template>
                    </div>
                    <p v-if="editCatId === group.id && editCatForm.errors.name" class="rsv-error">{{ editCatForm.errors.name }}</p>
                    <p v-if="editCatId === group.id && editCatForm.errors.description" class="rsv-error">{{ editCatForm.errors.description }}</p>
                    <p v-if="editCatId === group.id && imageError(editCatForm.errors)" class="rsv-error">{{ imageError(editCatForm.errors) }}</p>

                    <p v-if="group.description && editCatId !== group.id" class="rsv-srv-catdesc">{{ group.description }}</p>

                    <div v-if="group.services.length" class="rsv-srv-rows">
                        <div v-for="service in group.services" :key="service.id" class="rsv-srv-row">
                            <!-- Vista normal -->
                            <template v-if="editId !== service.id">
                                <div class="rsv-srv-thumb">
                                    <img
                                        v-if="service.url"
                                        :src="service.url"
                                        alt=""
                                        class="rsv-srv-zoomable"
                                        :title="t('srv.zoom')"
                                        @click="openGallery(service.image_urls)"
                                    />
                                    <span v-else class="rsv-srv-noimg">—</span>
                                </div>
                                <span class="rsv-srv-name">{{ service.name }}</span>
                                <span class="rsv-srv-price">{{ service.price }} €</span>
                                <span class="rsv-srv-dur">{{ formatDuration(service.duration_minutes) }}</span>
                                <span class="rsv-srv-spacer"></span>
                                <button type="button" class="rsv-edit" @click="startEdit(service)">{{ t('srv.edit') }}</button>
                                <button type="button" class="rsv-del" @click="remove(service.id)">{{ t('srv.delete') }}</button>
                                <p v-if="service.description" class="rsv-srv-desc">{{ service.description }}</p>

                                <div class="rsv-srv-options">
                                    <div class="rsv-srv-opts-head">
                                        <span>{{ t('srv.options') }}</span>
                                        <button
                                            v-if="optionFor !== service.id"
                                            type="button"
                                            class="rsv-edit"
                                            @click="startAddOption(service.id)"
                                        >
                                            {{ t('srv.addOption') }}
                                        </button>
                                    </div>

                                    <div v-for="opt in service.options" :key="opt.id" class="rsv-srv-opt">
                                        <template v-if="editOptionId !== opt.id">
                                            <div class="rsv-srv-thumb rsv-srv-optthumb">
                                                <img
                                                    v-if="opt.url"
                                                    :src="opt.url"
                                                    alt=""
                                                    class="rsv-srv-zoomable"
                                                    :title="t('srv.zoom')"
                                                    @click="openGallery(opt.image_urls)"
                                                />
                                                <span v-else class="rsv-srv-noimg">—</span>
                                            </div>
                                            <div class="rsv-srv-optinfo">
                                                <span class="rsv-srv-optname">{{ opt.name }}</span>
                                                <span class="rsv-srv-optmeta">
                                                    <span v-if="Number(opt.price) > 0">{{ opt.price }} €</span>
                                                    <span v-if="opt.duration_minutes > 0">{{ formatDuration(opt.duration_minutes) }}</span>
                                                </span>
                                                <span v-if="opt.description" class="rsv-srv-optdesc">{{ opt.description }}</span>
                                            </div>
                                            <button type="button" class="rsv-edit" @click="startEditOption(opt)">{{ t('srv.edit') }}</button>
                                            <button type="button" class="rsv-del" @click="removeOption(opt.id)">{{ t('srv.delete') }}</button>
                                        </template>
                                        <template v-else>
                                            <div class="rsv-srv-optfields">
                                                <input
                                                    v-model="editOptionForm.name"
                                                    type="text"
                                                    maxlength="100"
                                                    :placeholder="t('srv.optionNamePh')"
                                                    @keydown.enter.prevent="saveEditOption"
                                                />
                                                <span class="rsv-srv-num">
                                                    <input v-model.number="editOptionForm.price" type="number" min="0" step="0.01" :placeholder="t('srv.pricePh')" />
                                                    <i class="rsv-srv-unit">€</i>
                                                </span>
                                                <div class="rsv-srv-durfield" :title="t('srv.durationPh')">
                                                    <span class="rsv-srv-num">
                                                        <input v-model.number="editOptHours" type="number" min="0" step="1" placeholder="0" />
                                                        <i class="rsv-srv-unit">{{ t('srv.hours') }}</i>
                                                    </span>
                                                    <span class="rsv-srv-num">
                                                        <input v-model.number="editOptMinutes" type="number" min="0" max="59" step="5" placeholder="0" />
                                                        <i class="rsv-srv-unit">{{ t('srv.minutes') }}</i>
                                                    </span>
                                                </div>
                                                <textarea
                                                    v-model="editOptionForm.description"
                                                    class="rsv-srv-descfield"
                                                    rows="2"
                                                    maxlength="2000"
                                                    :placeholder="t('srv.optionInfoPh')"
                                                ></textarea>
                                                <ImagesField v-model="editOptionImages" />
                                                <p v-if="editOptionForm.errors.name" class="rsv-error">{{ editOptionForm.errors.name }}</p>
                                                <p v-if="imageError(editOptionForm.errors)" class="rsv-error">{{ imageError(editOptionForm.errors) }}</p>
                                            </div>
                                            <button type="button" class="rsv-edit" :disabled="editOptionForm.processing" @click="saveEditOption">{{ t('srv.save') }}</button>
                                            <button type="button" class="rsv-del" @click="cancelEditOption">{{ t('srv.cancel') }}</button>
                                        </template>
                                    </div>

                                    <div v-if="optionFor === service.id" class="rsv-srv-opt">
                                        <div class="rsv-srv-optfields">
                                            <input
                                                v-model="optionForm.name"
                                                type="text"
                                                maxlength="100"
                                                :placeholder="t('srv.optionNamePh')"
                                                @keydown.enter.prevent="saveOption"
                                            />
                                            <span class="rsv-srv-num">
                                                <input v-model.number="optionForm.price" type="number" min="0" step="0.01" :placeholder="t('srv.pricePh')" />
                                                <i class="rsv-srv-unit">€</i>
                                            </span>
                                            <div class="rsv-srv-durfield" :title="t('srv.durationPh')">
                                                <span class="rsv-srv-num">
                                                    <input v-model.number="optHours" type="number" min="0" step="1" placeholder="0" />
                                                    <i class="rsv-srv-unit">{{ t('srv.hours') }}</i>
                                                </span>
                                                <span class="rsv-srv-num">
                                                    <input v-model.number="optMinutes" type="number" min="0" max="59" step="5" placeholder="0" />
                                                    <i class="rsv-srv-unit">{{ t('srv.minutes') }}</i>
                                                </span>
                                            </div>
                                            <textarea
                                                v-model="optionForm.description"
                                                class="rsv-srv-descfield"
                                                rows="2"
                                                maxlength="2000"
                                                :placeholder="t('srv.optionInfoPh')"
                                            ></textarea>
                                            <ImagesField v-model="optionImages" />
                                            <p v-if="optionForm.errors.name" class="rsv-error">{{ optionForm.errors.name }}</p>
                                            <p v-if="imageError(optionForm.errors)" class="rsv-error">{{ imageError(optionForm.errors) }}</p>
                                        </div>
                                        <button type="button" class="rsv-edit" :disabled="optionForm.processing" @click="saveOption">{{ t('srv.add') }}</button>
                                        <button type="button" class="rsv-del" @click="cancelAddOption">{{ t('srv.cancel') }}</button>
                                    </div>
                                </div>
                            </template>

                            <!-- Vista edició -->
                            <template v-else>
                                <div class="rsv-srv-editfields">
                                    <input v-model="editForm.name" type="text" maxlength="100" @keydown.enter.prevent="saveEdit" />
                                    <span class="rsv-srv-num">
                                        <input
                                            v-model.number="editForm.price"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            :placeholder="t('srv.pricePh')"
                                            @keydown.enter.prevent="saveEdit"
                                        />
                                        <i class="rsv-srv-unit">€</i>
                                    </span>
                                    <select v-model.number="editForm.vat_rate" class="rsv-srv-select" :title="t('srv.vat')">
                                        <option v-for="r in VAT_RATES" :key="r" :value="r">{{ t('srv.vat') }} {{ r }}%</option>
                                    </select>
                                    <div class="rsv-srv-durfield" :title="t('srv.durationPh')">
                                        <span class="rsv-srv-num">
                                            <input
                                                v-model.number="editHours"
                                                type="number"
                                                min="0"
                                                step="1"
                                                placeholder="0"
                                                @keydown.enter.prevent="saveEdit"
                                            />
                                            <i class="rsv-srv-unit">{{ t('srv.hours') }}</i>
                                        </span>
                                        <span class="rsv-srv-num">
                                            <input
                                                v-model.number="editMinutes"
                                                type="number"
                                                min="0"
                                                max="59"
                                                step="5"
                                                placeholder="0"
                                                @keydown.enter.prevent="saveEdit"
                                            />
                                            <i class="rsv-srv-unit">{{ t('srv.minutes') }}</i>
                                        </span>
                                    </div>
                                    <select v-model="editForm.service_category_id" class="rsv-srv-select">
                                        <option value="">{{ t('srv.noCategory') }}</option>
                                        <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                                    </select>
                                    <ImagesField v-model="editImages" />
                                    <textarea
                                        v-model="editForm.description"
                                        class="rsv-srv-descfield"
                                        rows="2"
                                        maxlength="2000"
                                        :placeholder="t('srv.infoPh')"
                                    ></textarea>
                                    <p v-if="editForm.errors.name" class="rsv-error">{{ editForm.errors.name }}</p>
                                    <p v-if="editForm.errors.price" class="rsv-error">{{ editForm.errors.price }}</p>
                                    <p v-if="editForm.errors.duration_minutes" class="rsv-error">{{ editForm.errors.duration_minutes }}</p>
                                    <p v-if="editForm.errors.description" class="rsv-error">{{ editForm.errors.description }}</p>
                                    <p v-if="imageError(editForm.errors)" class="rsv-error">{{ imageError(editForm.errors) }}</p>
                                </div>
                                <button type="button" class="rsv-edit" :disabled="editForm.processing" @click="saveEdit">{{ t('srv.save') }}</button>
                                <button type="button" class="rsv-del" @click="cancelEdit">{{ t('srv.cancel') }}</button>
                            </template>
                        </div>
                    </div>
                    <div v-else class="rsv-empty">{{ t('srv.catEmpty') }}</div>
                </div>
            </div>
            <div v-else class="rsv-empty">{{ t('srv.empty') }}</div>
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
