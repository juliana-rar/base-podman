<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import '../../../css/reserva/admin.css';

interface Tag {
    id: number;
    name: string;
    color: string;
}

interface Post {
    id: number;
    title: string;
    body: string;
    cover_url: string | null;
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

const form = useForm<{ title: string; body: string; cover: File | null; images: File[]; tags: string[] }>({
    title: '',
    body: '',
    cover: null,
    images: [],
    tags: [],
});

const editingId = ref<number | null>(null);
const isEditing = computed(() => editingId.value !== null);

const coverPreview = ref<string | null>(null);
const imagePreviews = ref<string[]>([]);
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

function clearPreviews(): void {
    if (coverPreview.value) {
        URL.revokeObjectURL(coverPreview.value);
    }
    imagePreviews.value.forEach((url) => URL.revokeObjectURL(url));
    coverPreview.value = null;
    imagePreviews.value = [];
}

function onCover(event: Event): void {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    form.cover = file;
    if (coverPreview.value) {
        URL.revokeObjectURL(coverPreview.value);
    }
    coverPreview.value = file ? URL.createObjectURL(file) : null;
}

function onImages(event: Event): void {
    const files = Array.from((event.target as HTMLInputElement).files ?? []);
    form.images = files;
    imagePreviews.value.forEach((url) => URL.revokeObjectURL(url));
    imagePreviews.value = files.map((file) => URL.createObjectURL(file));
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
    form.tags = post.tags.map((t) => t.name);
    form.cover = null;
    form.images = [];
    tagInput.value = '';
    clearPreviews();
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
    return new Date(value).toLocaleDateString('ca-ES', {
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
    <Head title="Posts" />

    <div id="rsv-posts">
        <header>
            <h1>Posts de la presentació</h1>
            <p>El que publiquis aquí apareixerà a la pàgina pública d'inici, amb les seves imatges i etiquetes.</p>
        </header>

        <form @submit.prevent="submit">
            <h2>{{ isEditing ? 'Editar post' : 'Nou post' }}</h2>

            <label for="title">Títol</label>
            <input id="title" v-model="form.title" type="text" maxlength="255" required />
            <p v-if="form.errors.title" class="rsv-error">{{ form.errors.title }}</p>

            <label for="body">Contingut</label>
            <textarea id="body" v-model="form.body" required></textarea>
            <p v-if="form.errors.body" class="rsv-error">{{ form.errors.body }}</p>

            <label>Etiquetes</label>
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
                    placeholder="Nova etiqueta i prem Enter"
                    @keydown="onTagKeydown"
                    @blur="addTag"
                />
            </div>

            <label for="cover">Imatge de portada</label>
            <input :key="`cover-${editingId ?? 'new'}`" id="cover" type="file" accept="image/*" @change="onCover" />
            <img v-if="coverPreview" :src="coverPreview" alt="Portada" class="rsv-preview" />
            <p v-if="form.errors.cover" class="rsv-error">{{ form.errors.cover }}</p>

            <label for="images">Altres imatges (galeria)</label>
            <input :key="`images-${editingId ?? 'new'}`" id="images" type="file" accept="image/*" multiple @change="onImages" />
            <div v-if="imagePreviews.length" class="rsv-thumbs">
                <img v-for="(src, i) in imagePreviews" :key="i" :src="src" alt="" />
            </div>
            <p v-if="form.errors.images" class="rsv-error">{{ form.errors.images }}</p>

            <p v-if="isEditing" class="rsv-tag">Deixa les imatges buides per mantenir les actuals.</p>

            <div class="rsv-form-actions">
                <button type="submit" :disabled="form.processing">
                    {{ isEditing ? 'Desar canvis' : 'Publicar' }}
                </button>
                <button v-if="isEditing" type="button" class="rsv-cancel" @click="cancelEdit">Cancel·lar</button>
            </div>
        </form>

        <section>
            <div class="rsv-post-head">
                <h2>Posts publicats</h2>
                <input v-model="search" type="search" class="rsv-search" placeholder="Cerca per títol, text o etiqueta…" />
            </div>

            <div v-if="filteredPosts.length" class="rsv-post-grid">
                <article v-for="post in filteredPosts" :key="post.id">
                    <div>
                        <span>{{ post.title }}</span>
                        <div class="rsv-actions">
                            <button type="button" class="rsv-edit" @click="startEdit(post)">Editar</button>
                            <button type="button" class="rsv-del" @click="remove(post.id)">Eliminar</button>
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
                    <span class="rsv-tag">{{ post.author?.name ?? 'Equip' }} · {{ formatDate(post.created_at) }}</span>
                </article>
            </div>
            <div v-else class="rsv-empty">Cap post coincideix amb la cerca.</div>
        </section>
    </div>
</template>
