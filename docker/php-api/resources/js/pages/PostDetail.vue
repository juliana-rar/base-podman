<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from '@/lib/i18n';
import '../../css/reserva/postdetail.css';

interface Post {
    id: number;
    title: string;
    body: string;
    cover_url: string | null;
    image_urls: string[];
    tags: { id: number; name: string; color: string }[];
    created_at: string;
    author: { id: number; name: string } | null;
}

const props = defineProps<{ post: Post }>();

const { t, localeTag } = useI18n();

const images = computed(() => props.post.image_urls ?? []);
const current = ref(0);

function prev(): void {
    current.value = (current.value - 1 + images.value.length) % images.value.length;
}

function next(): void {
    current.value = (current.value + 1) % images.value.length;
}

function formatDate(value: string): string {
    return new Date(value).toLocaleDateString(localeTag(), {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}
</script>

<template>
    <Head :title="post.title">
        <link rel="preconnect" href="https://rsms.me/" />
        <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
    </Head>

    <div id="rsv-post">
        <header>
            <Link href="/">{{ t('post.tornar') }}</Link>
        </header>

        <article>
            <img v-if="post.cover_url" :src="post.cover_url" alt="" class="rsv-hero" />
            <h1>{{ post.title }}</h1>
            <div class="rsv-meta">
                <span>{{ post.author?.name ?? 'Equip' }}</span>
                <span>{{ formatDate(post.created_at) }}</span>
            </div>
            <div v-if="post.tags?.length" class="rsv-tags">
                <span
                    v-for="tag in post.tags"
                    :key="tag.id"
                    :style="{ backgroundColor: tag.color + '22', color: tag.color }"
                >{{ tag.name }}</span>
            </div>
            <p class="rsv-body">{{ post.body }}</p>

            <div v-if="images.length" class="rsv-carousel">
                <div>
                    <button type="button" aria-label="Anterior" @click="prev">‹</button>
                    <img :src="images[current]" alt="" />
                    <button type="button" aria-label="Següent" @click="next">›</button>
                </div>
                <div class="rsv-thumbs">
                    <button
                        v-for="(src, i) in images"
                        :key="i"
                        type="button"
                        :class="{ 'is-active': i === current }"
                        @click="current = i"
                    >
                        <img :src="src" alt="" />
                    </button>
                </div>
            </div>
        </article>
    </div>
</template>
