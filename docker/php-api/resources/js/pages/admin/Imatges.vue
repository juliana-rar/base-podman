<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import '../../../css/reserva/admin.css';

interface SlideImage {
    id: number;
    path: string;
    url: string;
}

defineProps<{
    images: SlideImage[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Imatges', href: '/admin/imatges' }],
    },
});

const form = useForm<{ images: File[] }>({ images: [] });
const previews = ref<string[]>([]);

function onFiles(event: Event): void {
    const files = Array.from((event.target as HTMLInputElement).files ?? []);
    form.images = files;
    previews.value.forEach((url) => URL.revokeObjectURL(url));
    previews.value = files.map((file) => URL.createObjectURL(file));
}

function submit(): void {
    form.post('/admin/imatges', {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            form.reset();
            previews.value.forEach((url) => URL.revokeObjectURL(url));
            previews.value = [];
        },
    });
}

function remove(id: number): void {
    router.delete(`/admin/imatges/${id}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Imatges" />

    <div id="rsv-imatges">
        <header>
            <h1>Imatges de presentació</h1>
            <p>Puja imatges per al carrusel de la pàgina d'inici.</p>
        </header>

        <section>
            <h2>Pujar imatges</h2>
            <input :key="previews.length" type="file" accept="image/*" multiple @change="onFiles" />
            <div v-if="previews.length" class="rsv-thumbs">
                <img v-for="(src, i) in previews" :key="i" :src="src" alt="" />
            </div>
            <p v-if="form.errors.images" class="rsv-error">{{ form.errors.images }}</p>
            <button type="button" :disabled="!form.images.length || form.processing" @click="submit">Pujar</button>
        </section>

        <section>
            <h2>Galeria ({{ images.length }})</h2>
            <p class="rsv-hint">Totes aquestes imatges surten al carrusel de la pàgina d'inici. Per treure'n una, prem Eliminar.</p>
            <div v-if="images.length" class="rsv-img-grid">
                <div v-for="img in images" :key="img.id" class="rsv-img-card">
                    <div class="rsv-img-thumb">
                        <img :src="img.url" alt="" />
                    </div>
                    <div class="rsv-img-actions">
                        <button type="button" class="rsv-del" @click="remove(img.id)">Eliminar</button>
                    </div>
                </div>
            </div>
            <div v-else class="rsv-empty">Encara no hi ha imatges.</div>
        </section>
    </div>
</template>
