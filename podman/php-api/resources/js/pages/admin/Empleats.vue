<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref, type Ref } from 'vue';
import ImagesField, { type ImageItem } from '@/components/ImagesField.vue';
import { useI18n } from '@/lib/i18n';
import '../../../css/reserva/admin.css';

const { t } = useI18n();

// --- Galeria d'obres de l'empleat (mateix patró que serveis/stock) ---

// Construeix la llista inicial d'imatges d'obres existents.
function worksOf(employee: Employee): ImageItem[] {
    return (employee.works ?? []).map((path, i) => ({ path, url: employee.work_urls[i] ?? '' }));
}

// Converteix la llista de l'editor en el payload (images[] + order JSON).
function worksPayload(items: ImageItem[]): { images: File[]; order: string } {
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
function clearWorks(list: Ref<ImageItem[]>): void {
    for (const item of list.value) {
        if (item.file && item.url.startsWith('blob:')) {
            URL.revokeObjectURL(item.url);
        }
    }
    list.value = [];
}

interface OptionRef {
    id: number;
    name: string;
}

interface ServiceRef {
    id: number;
    name: string;
    options: OptionRef[];
}

interface Category {
    id: number;
    name: string;
    services: ServiceRef[];
}

interface Employee {
    id: number;
    name: string;
    url: string | null;
    works: string[];
    work_urls: string[];
    service_ids: number[];
    option_ids: number[];
}

const props = defineProps<{
    employees: Employee[];
    categories: Category[];
    uncategorized: ServiceRef[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Empleats', href: '/admin/empleats' }],
    },
});

// Grups de serveis per assignar: categories + un grup final per als sense categoria.
const assignGroups = computed(() => [
    ...props.categories.map((c) => ({ id: c.id as number | null, name: c.name, services: c.services })),
    ...(props.uncategorized.length
        ? [{ id: null as number | null, name: t('emp.noCategory'), services: props.uncategorized }]
        : []),
]);

// Mapa id -> nom de servei, per mostrar els serveis assignats a la llista.
const serviceNameById = computed(() => {
    const map = new Map<number, string>();
    props.categories.forEach((c) => c.services.forEach((s) => map.set(s.id, s.name)));
    props.uncategorized.forEach((s) => map.set(s.id, s.name));
    return map;
});

function serviceNames(ids: number[]): string[] {
    return ids.map((id) => serviceNameById.value.get(id)).filter((n): n is string => Boolean(n));
}

// --- Helpers d'assignació (operen sobre el service_ids d'un formulari) ---
function toggleService(form: { service_ids: number[] }, id: number): void {
    const i = form.service_ids.indexOf(id);
    if (i === -1) {
        form.service_ids.push(id);
    } else {
        form.service_ids.splice(i, 1);
    }
}

function allChecked(form: { service_ids: number[] }, services: ServiceRef[]): boolean {
    return services.length > 0 && services.every((s) => form.service_ids.includes(s.id));
}

function toggleAll(form: { service_ids: number[] }, services: ServiceRef[]): void {
    if (allChecked(form, services)) {
        const ids = new Set(services.map((s) => s.id));
        form.service_ids = form.service_ids.filter((id) => !ids.has(id));
    } else {
        const set = new Set(form.service_ids);
        services.forEach((s) => set.add(s.id));
        form.service_ids = [...set];
    }
}

// En marcar una opció, s'assegura que el servei pare també quedi marcat.
function toggleOption(form: { service_ids: number[]; option_ids: number[] }, optionId: number, serviceId: number): void {
    const i = form.option_ids.indexOf(optionId);
    if (i === -1) {
        form.option_ids.push(optionId);
        if (!form.service_ids.includes(serviceId)) {
            form.service_ids.push(serviceId);
        }
    } else {
        form.option_ids.splice(i, 1);
    }
}

// --- Crear ---
const form = useForm<{ name: string; service_ids: number[]; option_ids: number[]; image: File | null }>({
    name: '',
    service_ids: [],
    option_ids: [],
    image: null,
});
const newPreview = ref<string | null>(null);
const newWorks = ref<ImageItem[]>([]);

function onNewFile(event: Event): void {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    form.image = file;
    if (newPreview.value) URL.revokeObjectURL(newPreview.value);
    newPreview.value = file ? URL.createObjectURL(file) : null;
}

function create(): void {
    const { images, order } = worksPayload(newWorks.value);
    form.transform((data) => ({ ...data, images, order })).post('/admin/empleats', {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            form.reset();
            if (newPreview.value) URL.revokeObjectURL(newPreview.value);
            newPreview.value = null;
            clearWorks(newWorks);
        },
    });
}

// --- Editar ---
const editId = ref<number | null>(null);
const editForm = useForm<{ name: string; service_ids: number[]; option_ids: number[]; image: File | null }>({
    name: '',
    service_ids: [],
    option_ids: [],
    image: null,
});
const editPreview = ref<string | null>(null);
const editWorks = ref<ImageItem[]>([]);

function startEdit(employee: Employee): void {
    editId.value = employee.id;
    editForm.reset();
    editForm.clearErrors();
    editForm.name = employee.name;
    editForm.service_ids = [...employee.service_ids];
    editForm.option_ids = [...employee.option_ids];
    editForm.image = null;
    if (editPreview.value) URL.revokeObjectURL(editPreview.value);
    editPreview.value = null;
    clearWorks(editWorks);
    editWorks.value = worksOf(employee);
}

function onEditFile(event: Event): void {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    editForm.image = file;
    if (editPreview.value) URL.revokeObjectURL(editPreview.value);
    editPreview.value = file ? URL.createObjectURL(file) : null;
}

function saveEdit(): void {
    if (editId.value === null) return;
    const { images, order } = worksPayload(editWorks.value);
    editForm.transform((data) => ({ ...data, images, order })).post(`/admin/empleats/${editId.value}`, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            editId.value = null;
            if (editPreview.value) URL.revokeObjectURL(editPreview.value);
            editPreview.value = null;
            clearWorks(editWorks);
        },
    });
}

function cancelEdit(): void {
    editId.value = null;
    if (editPreview.value) URL.revokeObjectURL(editPreview.value);
    editPreview.value = null;
    clearWorks(editWorks);
}

function remove(id: number): void {
    router.delete(`/admin/empleats/${id}`, { preserveScroll: true });
}
</script>

<template>
    <Head :title="t('emp.title')" />

    <div id="rsv-empleats">
        <header>
            <h1>{{ t('emp.title') }}</h1>
            <p>{{ t('emp.subtitle') }}</p>
        </header>

        <section>
            <!-- ===================== Nou empleat ===================== -->
            <h2>{{ t('emp.new') }}</h2>
            <div class="rsv-emp-form">
                <div class="rsv-emp-fields">
                    <input
                        v-model="form.name"
                        type="text"
                        maxlength="100"
                        :placeholder="t('emp.namePh')"
                        @keydown.enter.prevent="create"
                    />
                    <label class="rsv-file">
                        <span>{{ form.image ? form.image.name : t('emp.imageOpt') }}</span>
                        <input type="file" accept="image/*" @change="onNewFile" />
                    </label>
                    <div v-if="newPreview" class="rsv-emp-prev">
                        <img :src="newPreview" alt="" />
                    </div>
                </div>

                <div class="rsv-emp-works">
                    <span class="rsv-emp-assign-label">{{ t('emp.works') }}</span>
                    <ImagesField v-model="newWorks" />
                </div>

                <div class="rsv-emp-assign">
                    <span class="rsv-emp-assign-label">{{ t('emp.services') }}</span>
                    <div class="rsv-emp-groups">
                        <div v-for="group in assignGroups" :key="group.id ?? 'none'" class="rsv-emp-group">
                            <label class="rsv-emp-grouphead">
                                <input
                                    type="checkbox"
                                    :checked="allChecked(form, group.services)"
                                    @change="toggleAll(form, group.services)"
                                />
                                <span>{{ group.name }}</span>
                            </label>
                            <div v-for="s in group.services" :key="s.id" class="rsv-emp-srvitem">
                                <label class="rsv-emp-srv">
                                    <input
                                        type="checkbox"
                                        :checked="form.service_ids.includes(s.id)"
                                        @change="toggleService(form, s.id)"
                                    />
                                    <span>{{ s.name }}</span>
                                </label>
                                <label v-for="o in s.options" :key="o.id" class="rsv-emp-opt">
                                    <input
                                        type="checkbox"
                                        :checked="form.option_ids.includes(o.id)"
                                        @change="toggleOption(form, o.id, s.id)"
                                    />
                                    <span>{{ o.name }}</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <button type="button" class="rsv-edit" :disabled="form.processing" @click="create">{{ t('emp.add') }}</button>
            </div>
            <p v-if="form.errors.name" class="rsv-error">{{ form.errors.name }}</p>
            <p v-if="form.errors.image" class="rsv-error">{{ form.errors.image }}</p>

            <!-- ===================== Llista ===================== -->
            <h2>{{ t('emp.list') }}</h2>
            <div v-if="employees.length" class="rsv-emp-rows">
                <div v-for="employee in employees" :key="employee.id" class="rsv-emp-row">
                    <!-- Vista normal -->
                    <template v-if="editId !== employee.id">
                        <div class="rsv-emp-thumb">
                            <img v-if="employee.url" :src="employee.url" alt="" />
                            <span v-else class="rsv-emp-noimg">—</span>
                        </div>
                        <div class="rsv-emp-info">
                            <span class="rsv-emp-name">{{ employee.name }}</span>
                            <span class="rsv-emp-tags">
                                <template v-if="employee.service_ids.length">
                                    <span v-for="name in serviceNames(employee.service_ids)" :key="name" class="rsv-emp-tag">{{ name }}</span>
                                </template>
                                <span v-else class="rsv-emp-none">{{ t('emp.noServices') }}</span>
                            </span>
                            <span v-if="employee.work_urls.length" class="rsv-emp-worksmini">
                                <img v-for="(u, i) in employee.work_urls" :key="i" :src="u" alt="" />
                            </span>
                        </div>
                        <button type="button" class="rsv-edit" @click="startEdit(employee)">{{ t('emp.edit') }}</button>
                        <button type="button" class="rsv-del" @click="remove(employee.id)">{{ t('emp.delete') }}</button>
                    </template>

                    <!-- Vista edició -->
                    <template v-else>
                        <div class="rsv-emp-editmain">
                            <div class="rsv-emp-thumb">
                                <img v-if="editPreview" :src="editPreview" alt="" />
                                <img v-else-if="employee.url" :src="employee.url" alt="" />
                                <span v-else class="rsv-emp-noimg">—</span>
                            </div>
                            <div class="rsv-emp-fields">
                                <input v-model="editForm.name" type="text" maxlength="100" @keydown.enter.prevent="saveEdit" />
                                <label class="rsv-file">
                                    <span>{{ editForm.image ? editForm.image.name : t('emp.changeImage') }}</span>
                                    <input type="file" accept="image/*" @change="onEditFile" />
                                </label>
                            </div>
                        </div>

                        <div class="rsv-emp-works">
                            <span class="rsv-emp-assign-label">{{ t('emp.works') }}</span>
                            <ImagesField v-model="editWorks" />
                        </div>

                        <div class="rsv-emp-assign">
                            <span class="rsv-emp-assign-label">{{ t('emp.services') }}</span>
                            <div class="rsv-emp-groups">
                                <div v-for="group in assignGroups" :key="group.id ?? 'none'" class="rsv-emp-group">
                                    <label class="rsv-emp-grouphead">
                                        <input
                                            type="checkbox"
                                            :checked="allChecked(editForm, group.services)"
                                            @change="toggleAll(editForm, group.services)"
                                        />
                                        <span>{{ group.name }}</span>
                                    </label>
                                    <div v-for="s in group.services" :key="s.id" class="rsv-emp-srvitem">
                                        <label class="rsv-emp-srv">
                                            <input
                                                type="checkbox"
                                                :checked="editForm.service_ids.includes(s.id)"
                                                @change="toggleService(editForm, s.id)"
                                            />
                                            <span>{{ s.name }}</span>
                                        </label>
                                        <label v-for="o in s.options" :key="o.id" class="rsv-emp-opt">
                                            <input
                                                type="checkbox"
                                                :checked="editForm.option_ids.includes(o.id)"
                                                @change="toggleOption(editForm, o.id, s.id)"
                                            />
                                            <span>{{ o.name }}</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="rsv-emp-editactions">
                            <button type="button" class="rsv-edit" :disabled="editForm.processing" @click="saveEdit">{{ t('emp.save') }}</button>
                            <button type="button" class="rsv-del" @click="cancelEdit">{{ t('emp.cancel') }}</button>
                        </div>
                        <p v-if="editForm.errors.name" class="rsv-error">{{ editForm.errors.name }}</p>
                        <p v-if="editForm.errors.image" class="rsv-error">{{ editForm.errors.image }}</p>
                    </template>
                </div>
            </div>
            <div v-else class="rsv-empty">{{ t('emp.empty') }}</div>
        </section>
    </div>
</template>
