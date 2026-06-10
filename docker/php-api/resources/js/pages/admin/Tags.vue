<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import '../../../css/reserva/admin.css';

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

function remove(id: number): void {
    router.delete(`/admin/tags/${id}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Etiquetes" />

    <div id="rsv-tags">
        <header>
            <h1>Etiquetes</h1>
            <p>Crea, acoloreix i elimina les etiquetes que pots assignar als posts.</p>
        </header>

        <section>
            <h2>Nova etiqueta</h2>
            <div class="rsv-taginput">
                <input v-model="newColor" type="color" class="rsv-color" aria-label="Color" />
                <input
                    v-model="newName"
                    type="text"
                    maxlength="30"
                    placeholder="Nom de l'etiqueta"
                    @keydown.enter.prevent="create"
                />
                <button type="button" class="rsv-edit" @click="create">Afegir</button>
            </div>

            <h2>Catàleg</h2>
            <div v-if="tags.length" class="rsv-tagrows">
                <div v-for="tag in tags" :key="tag.id" class="rsv-tagrow">
                    <input
                        type="color"
                        class="rsv-color"
                        :value="tag.color"
                        aria-label="Color"
                        @change="updateColor(tag.id, $event)"
                    />
                    <span class="rsv-tagchip" :style="{ backgroundColor: tag.color + '22', color: tag.color }">
                        {{ tag.name }}
                    </span>
                    <span class="rsv-count">{{ tag.posts_count }} {{ tag.posts_count === 1 ? 'post' : 'posts' }}</span>
                    <button type="button" class="rsv-del" @click="remove(tag.id)">Eliminar</button>
                </div>
            </div>
            <div v-else class="rsv-empty">Encara no hi ha cap etiqueta al catàleg.</div>
        </section>
    </div>
</template>
