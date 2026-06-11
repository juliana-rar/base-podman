<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import '../../../css/reserva/admin.css';

interface Service {
    id: number;
    name: string;
    url: string | null;
    reservations_count: number;
}

defineProps<{
    services: Service[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Serveis', href: '/admin/serveis' }],
    },
});

// --- Crear ---
const form = useForm<{ name: string; image: File | null }>({ name: '', image: null });
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

// --- Editar ---
const editId = ref<number | null>(null);
const editForm = useForm<{ name: string; image: File | null }>({ name: '', image: null });
const editPreview = ref<string | null>(null);

function startEdit(service: Service): void {
    editId.value = service.id;
    editForm.reset();
    editForm.clearErrors();
    editForm.name = service.name;
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
    <Head title="Serveis" />

    <div id="rsv-serveis">
        <header>
            <h1>Serveis</h1>
            <p>Crea els serveis que els usuaris poden triar en fer una reserva.</p>
        </header>

        <section>
            <h2>Nou servei</h2>
            <div class="rsv-srv-form">
                <input
                    v-model="form.name"
                    type="text"
                    maxlength="100"
                    placeholder="Nom del servei (ex: Tall de cabell)"
                    @keydown.enter.prevent="create"
                />
                <label class="rsv-file">
                    <span>{{ form.image ? form.image.name : 'Imatge (opcional)' }}</span>
                    <input type="file" accept="image/*" @change="onNewFile" />
                </label>
                <button type="button" class="rsv-edit" :disabled="form.processing" @click="create">Afegir</button>
            </div>
            <div v-if="newPreview" class="rsv-srv-newprev">
                <img :src="newPreview" alt="" />
            </div>
            <p v-if="form.errors.name" class="rsv-error">{{ form.errors.name }}</p>
            <p v-if="form.errors.image" class="rsv-error">{{ form.errors.image }}</p>

            <h2>Catàleg</h2>
            <div v-if="services.length" class="rsv-srv-rows">
                <div v-for="service in services" :key="service.id" class="rsv-srv-row">
                    <!-- Vista normal -->
                    <template v-if="editId !== service.id">
                        <div class="rsv-srv-thumb">
                            <img v-if="service.url" :src="service.url" alt="" />
                            <span v-else class="rsv-srv-noimg">—</span>
                        </div>
                        <span class="rsv-srv-name">{{ service.name }}</span>
                        <span class="rsv-count">
                            {{ service.reservations_count }} {{ service.reservations_count === 1 ? 'reserva' : 'reserves' }}
                        </span>
                        <button type="button" class="rsv-edit" @click="startEdit(service)">Editar</button>
                        <button type="button" class="rsv-del" @click="remove(service.id)">Eliminar</button>
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
                            <label class="rsv-file">
                                <span>{{ editForm.image ? editForm.image.name : 'Canviar imatge' }}</span>
                                <input type="file" accept="image/*" @change="onEditFile" />
                            </label>
                            <p v-if="editForm.errors.name" class="rsv-error">{{ editForm.errors.name }}</p>
                            <p v-if="editForm.errors.image" class="rsv-error">{{ editForm.errors.image }}</p>
                        </div>
                        <button type="button" class="rsv-edit" :disabled="editForm.processing" @click="saveEdit">Desar</button>
                        <button type="button" class="rsv-del" @click="cancelEdit">Cancel·lar</button>
                    </template>
                </div>
            </div>
            <div v-else class="rsv-empty">Encara no hi ha cap servei.</div>
        </section>
    </div>
</template>
