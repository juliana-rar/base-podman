<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from '@/lib/i18n';
import '../../../css/reserva/admin.css';

const { t } = useI18n();

interface Service {
    id: number;
    name: string;
    price: string;
    duration_minutes: number;
    url: string | null;
    reservations_count: number;
    service_category_id: number | null;
}

interface Category {
    id: number;
    name: string;
    url: string | null;
    services: Service[];
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
    ...props.categories.map((c) => ({ id: c.id as number | null, name: c.name, url: c.url, isReal: true, services: c.services })),
    ...(props.uncategorized.length
        ? [{ id: null as number | null, name: t('srv.noCategory'), url: null as string | null, isReal: false, services: props.uncategorized }]
        : []),
]);

// =========================================================================
//  Categories
// =========================================================================
const catForm = useForm<{ name: string; image: File | null }>({ name: '', image: null });
const newCatPreview = ref<string | null>(null);

function onNewCatFile(event: Event): void {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    catForm.image = file;
    if (newCatPreview.value) URL.revokeObjectURL(newCatPreview.value);
    newCatPreview.value = file ? URL.createObjectURL(file) : null;
}

function createCategory(): void {
    catForm.post('/admin/serveis-categories', {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            catForm.reset();
            if (newCatPreview.value) URL.revokeObjectURL(newCatPreview.value);
            newCatPreview.value = null;
        },
    });
}

const editCatId = ref<number | null>(null);
const editCatForm = useForm<{ name: string; image: File | null }>({ name: '', image: null });
const editCatPreview = ref<string | null>(null);

function startEditCategory(category: Category): void {
    editCatId.value = category.id;
    editCatForm.reset();
    editCatForm.clearErrors();
    editCatForm.name = category.name;
    editCatForm.image = null;
    if (editCatPreview.value) URL.revokeObjectURL(editCatPreview.value);
    editCatPreview.value = null;
}

function onEditCatFile(event: Event): void {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    editCatForm.image = file;
    if (editCatPreview.value) URL.revokeObjectURL(editCatPreview.value);
    editCatPreview.value = file ? URL.createObjectURL(file) : null;
}

function saveEditCategory(): void {
    if (editCatId.value === null) return;
    editCatForm.post(`/admin/serveis-categories/${editCatId.value}`, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            editCatId.value = null;
            if (editCatPreview.value) URL.revokeObjectURL(editCatPreview.value);
            editCatPreview.value = null;
        },
    });
}

function cancelEditCategory(): void {
    editCatId.value = null;
    if (editCatPreview.value) URL.revokeObjectURL(editCatPreview.value);
    editCatPreview.value = null;
}

function removeCategory(id: number): void {
    router.delete(`/admin/serveis-categories/${id}`, { preserveScroll: true });
}

// =========================================================================
//  Serveis — crear
// =========================================================================
const form = useForm<{ name: string; price: number; duration_minutes: number; service_category_id: number | ''; image: File | null }>({
    name: '',
    price: 0,
    duration_minutes: 0,
    service_category_id: '',
    image: null,
});
const newPreview = ref<string | null>(null);

function onNewFile(event: Event): void {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    form.image = file;
    if (newPreview.value) URL.revokeObjectURL(newPreview.value);
    newPreview.value = file ? URL.createObjectURL(file) : null;
}

function create(): void {
    form.post('/admin/serveis', {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            form.reset();
            if (newPreview.value) URL.revokeObjectURL(newPreview.value);
            newPreview.value = null;
        },
    });
}

// =========================================================================
//  Serveis — editar
// =========================================================================
const editId = ref<number | null>(null);
const editForm = useForm<{ name: string; price: number; duration_minutes: number; service_category_id: number | ''; image: File | null }>({
    name: '',
    price: 0,
    duration_minutes: 0,
    service_category_id: '',
    image: null,
});
const editPreview = ref<string | null>(null);

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
    editForm.duration_minutes = service.duration_minutes;
    editForm.service_category_id = service.service_category_id ?? '';
    editForm.image = null;
    if (editPreview.value) URL.revokeObjectURL(editPreview.value);
    editPreview.value = null;
}

function onEditFile(event: Event): void {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    editForm.image = file;
    if (editPreview.value) URL.revokeObjectURL(editPreview.value);
    editPreview.value = file ? URL.createObjectURL(file) : null;
}

function saveEdit(): void {
    if (editId.value === null) return;
    editForm.post(`/admin/serveis/${editId.value}`, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            editId.value = null;
            if (editPreview.value) URL.revokeObjectURL(editPreview.value);
            editPreview.value = null;
        },
    });
}

function cancelEdit(): void {
    editId.value = null;
    if (editPreview.value) URL.revokeObjectURL(editPreview.value);
    editPreview.value = null;
}

function remove(id: number): void {
    router.delete(`/admin/serveis/${id}`, { preserveScroll: true });
}
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
                <label class="rsv-file">
                    <span>{{ catForm.image ? catForm.image.name : t('srv.imageOpt') }}</span>
                    <input type="file" accept="image/*" @change="onNewCatFile" />
                </label>
                <button type="button" class="rsv-edit" :disabled="catForm.processing" @click="createCategory">
                    {{ t('srv.addCategory') }}
                </button>
            </div>
            <div v-if="newCatPreview" class="rsv-srv-newprev">
                <img :src="newCatPreview" alt="" />
            </div>
            <p v-if="catForm.errors.name" class="rsv-error">{{ catForm.errors.name }}</p>
            <p v-if="catForm.errors.image" class="rsv-error">{{ catForm.errors.image }}</p>

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
                <label class="rsv-file">
                    <span>{{ form.image ? form.image.name : t('srv.imageOpt') }}</span>
                    <input type="file" accept="image/*" @change="onNewFile" />
                </label>
                <button type="button" class="rsv-edit" :disabled="form.processing" @click="create">{{ t('srv.add') }}</button>
            </div>
            <div v-if="newPreview" class="rsv-srv-newprev">
                <img :src="newPreview" alt="" />
            </div>
            <p v-if="form.errors.name" class="rsv-error">{{ form.errors.name }}</p>
            <p v-if="form.errors.price" class="rsv-error">{{ form.errors.price }}</p>
            <p v-if="form.errors.duration_minutes" class="rsv-error">{{ form.errors.duration_minutes }}</p>
            <p v-if="form.errors.image" class="rsv-error">{{ form.errors.image }}</p>

            <!-- ===================== Catàleg per categories ===================== -->
            <h2>{{ t('srv.catalog') }}</h2>
            <div v-if="groups.length" class="rsv-srv-groups">
                <div v-for="group in groups" :key="group.id ?? 'none'" class="rsv-srv-group">
                    <div class="rsv-srv-grouphead">
                        <template v-if="group.isReal && editCatId === group.id">
                            <div class="rsv-srv-thumb">
                                <img v-if="editCatPreview" :src="editCatPreview" alt="" />
                                <img v-else-if="group.url" :src="group.url" alt="" />
                                <span v-else class="rsv-srv-noimg">—</span>
                            </div>
                            <input
                                v-model="editCatForm.name"
                                type="text"
                                maxlength="100"
                                @keydown.enter.prevent="saveEditCategory"
                            />
                            <label class="rsv-file">
                                <span>{{ editCatForm.image ? editCatForm.image.name : t('srv.changeImage') }}</span>
                                <input type="file" accept="image/*" @change="onEditCatFile" />
                            </label>
                            <button type="button" class="rsv-edit" :disabled="editCatForm.processing" @click="saveEditCategory">
                                {{ t('srv.save') }}
                            </button>
                            <button type="button" class="rsv-del" @click="cancelEditCategory">{{ t('srv.cancel') }}</button>
                        </template>
                        <template v-else>
                            <div v-if="group.isReal" class="rsv-srv-thumb">
                                <img v-if="group.url" :src="group.url" alt="" />
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
                    <p v-if="editCatId === group.id && editCatForm.errors.image" class="rsv-error">{{ editCatForm.errors.image }}</p>

                    <div v-if="group.services.length" class="rsv-srv-rows">
                        <div v-for="service in group.services" :key="service.id" class="rsv-srv-row">
                            <!-- Vista normal -->
                            <template v-if="editId !== service.id">
                                <div class="rsv-srv-thumb">
                                    <img v-if="service.url" :src="service.url" alt="" />
                                    <span v-else class="rsv-srv-noimg">—</span>
                                </div>
                                <span class="rsv-srv-name">{{ service.name }}</span>
                                <span class="rsv-srv-price">{{ service.price }} €</span>
                                <span class="rsv-srv-dur">{{ formatDuration(service.duration_minutes) }}</span>
                                <span class="rsv-count">
                                    {{ service.reservations_count }}
                                    {{ service.reservations_count === 1 ? t('srv.reservaOne') : t('srv.reservaMany') }}
                                </span>
                                <button type="button" class="rsv-edit" @click="startEdit(service)">{{ t('srv.edit') }}</button>
                                <button type="button" class="rsv-del" @click="remove(service.id)">{{ t('srv.delete') }}</button>
                            </template>

                            <!-- Vista edició -->
                            <template v-else>
                                <div class="rsv-srv-thumb">
                                    <img v-if="editPreview" :src="editPreview" alt="" />
                                    <img v-else-if="service.url" :src="service.url" alt="" />
                                    <span v-else class="rsv-srv-noimg">—</span>
                                </div>
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
                                    <label class="rsv-file">
                                        <span>{{ editForm.image ? editForm.image.name : t('srv.changeImage') }}</span>
                                        <input type="file" accept="image/*" @change="onEditFile" />
                                    </label>
                                    <p v-if="editForm.errors.name" class="rsv-error">{{ editForm.errors.name }}</p>
                                    <p v-if="editForm.errors.price" class="rsv-error">{{ editForm.errors.price }}</p>
                                    <p v-if="editForm.errors.duration_minutes" class="rsv-error">{{ editForm.errors.duration_minutes }}</p>
                                    <p v-if="editForm.errors.image" class="rsv-error">{{ editForm.errors.image }}</p>
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
    </div>
</template>
