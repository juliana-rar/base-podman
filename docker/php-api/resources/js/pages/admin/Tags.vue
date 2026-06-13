<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from '@/lib/i18n';
import '../../../css/reserva/admin.css';

const { t } = useI18n();

interface Tag {
    id: number;
    name: string;
    color: string;
    posts_count: number;
}

defineProps<{
    tags: Tag[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Etiquetes', href: '/admin/etiquetes' }],
    },
});

const newName = ref('');
const newColor = ref('#4f46e5');

function create(): void {
    const name = newName.value.trim();
    if (!name) {
        return;
    }
    router.post(
        '/admin/tags',
        { name, color: newColor.value },
        { preserveScroll: true, onSuccess: () => (newName.value = '') },
    );
}

function updateColor(id: number, event: Event): void {
    const color = (event.target as HTMLInputElement).value;
    router.put(`/admin/tags/${id}`, { color }, { preserveScroll: true });
}

function updateName(id: number, event: Event): void {
    const name = (event.target as HTMLInputElement).value.trim();
    if (name) {
        router.put(`/admin/tags/${id}`, { name }, { preserveScroll: true });
    }
}

function remove(id: number): void {
    router.delete(`/admin/tags/${id}`, { preserveScroll: true });
}
</script>

<template>
    <Head :title="t('tag.title')" />

    <div id="rsv-tags">
        <header>
            <h1>{{ t('tag.title') }}</h1>
            <p>{{ t('tag.subtitle') }}</p>
        </header>

        <section>
            <h2>{{ t('tag.new') }}</h2>
            <div class="rsv-taginput">
                <input v-model="newColor" type="color" class="rsv-color" aria-label="Color" />
                <input
                    v-model="newName"
                    type="text"
                    maxlength="30"
                    :placeholder="t('tag.namePh')"
                    @keydown.enter.prevent="create"
                />
                <button type="button" class="rsv-edit" @click="create">{{ t('tag.add') }}</button>
            </div>

            <h2>{{ t('tag.catalog') }}</h2>
            <div v-if="tags.length" class="rsv-tagrows">
                <div v-for="tag in tags" :key="tag.id" class="rsv-tagrow">
                    <input
                        type="color"
                        class="rsv-color"
                        :value="tag.color"
                        aria-label="Color"
                        @change="updateColor(tag.id, $event)"
                    />
                    <input
                        class="rsv-tagname"
                        type="text"
                        maxlength="30"
                        :value="tag.name"
                        :style="{ backgroundColor: tag.color + '22', color: tag.color }"
                        :aria-label="t('tag.namePh')"
                        @change="updateName(tag.id, $event)"
                        @keydown.enter="($event.target as HTMLInputElement).blur()"
                    />
                    <span class="rsv-count">{{ tag.posts_count }} {{ tag.posts_count === 1 ? t('tag.postOne') : t('tag.postMany') }}</span>
                    <button type="button" class="rsv-del" @click="remove(tag.id)">{{ t('tag.delete') }}</button>
                </div>
            </div>
            <div v-else class="rsv-empty">{{ t('tag.empty') }}</div>
        </section>
    </div>
</template>
