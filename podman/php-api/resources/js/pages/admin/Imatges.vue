<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from '@/lib/i18n';
import '../../../css/reserva/admin.css';

const { t } = useI18n();

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
    <Head :title="t('img.title')" />

    <div id="rsv-imatges">
        <header>
            <h1>{{ t('img.title') }}</h1>
            <p>{{ t('img.subtitle') }}</p>
        </header>

        <section>
            <h2>{{ t('img.upload') }}</h2>
            <input :key="previews.length" type="file" accept="image/*" multiple @change="onFiles" />
            <div v-if="previews.length" class="rsv-thumbs">
                <img v-for="(src, i) in previews" :key="i" :src="src" alt="" />
            </div>
            <p v-if="form.errors.images" class="rsv-error">{{ form.errors.images }}</p>
            <button type="button" :disabled="!form.images.length || form.processing" @click="submit">{{ t('img.uploadBtn') }}</button>
        </section>

        <section>
            <h2>{{ t('img.gallery') }} ({{ images.length }})</h2>
            <p class="rsv-hint">{{ t('img.hint') }}</p>
            <div v-if="images.length" class="rsv-img-grid">
                <div v-for="img in images" :key="img.id" class="rsv-img-card">
                    <div class="rsv-img-thumb">
                        <img :src="img.url" alt="" />
                    </div>
                    <div class="rsv-img-actions">
                        <button type="button" class="rsv-del" @click="remove(img.id)">{{ t('img.delete') }}</button>
                    </div>
                </div>
            </div>
            <div v-else class="rsv-empty">{{ t('img.empty') }}</div>
        </section>
    </div>
</template>
