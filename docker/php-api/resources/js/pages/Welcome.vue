<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useI18n } from '@/lib/i18n';
import '../../css/reserva/welcome.css';

interface Post {
    id: number;
    title: string;
    slug: string;
    body: string;
    cover_url: string | null;
    image_urls: string[];
    tags: { id: number; name: string; color: string }[];
    created_at: string;
    author: { id: number; name: string } | null;
}

const props = defineProps<{
    posts: Post[];
}>();

const page = usePage();
const user = computed(() => page.props.auth.user);
const { t, localeTag } = useI18n();

function formatDate(value: string): string {
    return new Date(value).toLocaleDateString(localeTag(), {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}

// --- Carrusel infinit (3 visibles, responsive) ---
const SLIDE_MS = 450;
const perView = ref(3);

function updatePerView(): void {
    const w = window.innerWidth;
    perView.value = w < 640 ? 1 : w < 960 ? 2 : 3;
}

const count = computed(() => props.posts.length);
const useCarousel = computed(() => count.value > perView.value);

// Buffer triple per al bucle infinit; recentrem en silenci als extrems.
const display = computed(() =>
    useCarousel.value ? [...props.posts, ...props.posts, ...props.posts] : props.posts,
);

const index = ref(props.posts.length);
const animate = ref(true);
let locked = false;

const trackStyle = computed(() => ({
    transform: useCarousel.value ? `translateX(calc(${-index.value} * 100% / ${perView.value}))` : 'none',
    transition: animate.value ? `transform ${SLIDE_MS}ms ease` : 'none',
}));

function recenter(delta: number): void {
    animate.value = false;
    index.value += delta;
    requestAnimationFrame(() => requestAnimationFrame(() => (animate.value = true)));
}

function step(direction: number): void {
    if (locked || !useCarousel.value) {
        return;
    }
    locked = true;
    index.value += direction;

    window.setTimeout(() => {
        if (index.value >= 2 * count.value) {
            recenter(-count.value);
        } else if (index.value < count.value) {
            recenter(count.value);
        }
        locked = false;
    }, SLIDE_MS);
}

const prev = () => step(-1);
const next = () => step(1);

onMounted(() => {
    updatePerView();
    window.addEventListener('resize', updatePerView);
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', updatePerView);
});
</script>

<template>
    <Head title="Reserva d'hores">
        <link rel="preconnect" href="https://rsms.me/" />
        <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
    </Head>

    <div id="rsv-welcome">
        <header>
            <div>ReservaHores</div>
            <nav>
                <Link v-if="user" href="/dashboard">{{ t('welcome.panell') }}</Link>
                <template v-else>
                    <Link href="/login">{{ t('welcome.entrar') }}</Link>
                    <Link href="/register">{{ t('welcome.crear') }}</Link>
                </template>
            </nav>
        </header>

        <section>
            <h1>{{ t('welcome.heroA') }} <span>{{ t('welcome.heroB') }}</span></h1>
            <p>{{ t('welcome.heroText') }}</p>
            <Link :href="user ? '/reservar' : '/login'">
                {{ user ? t('welcome.ctaUser') : t('welcome.ctaGuest') }}
            </Link>
        </section>

        <section>
            <h2>{{ t('welcome.novetats') }}</h2>
            <div v-if="posts.length" class="rsv-carousel" :style="{ '--per': perView }">
                <button v-if="useCarousel" type="button" class="rsv-arrow" aria-label="Anterior" @click="prev">
                    ‹
                </button>
                <div class="rsv-viewport">
                    <div class="rsv-track" :class="{ 'is-static': !useCarousel }" :style="trackStyle">
                        <div v-for="(post, i) in display" :key="i" class="rsv-slide">
                            <Link :href="`/posts/${post.slug}`" class="rsv-card">
                                <img v-if="post.cover_url" :src="post.cover_url" alt="" class="rsv-cover" />
                                <h3 class="rsv-title">{{ post.title }}</h3>
                                <div v-if="post.tags?.length" class="rsv-tags">
                                    <span
                                        v-for="tag in post.tags"
                                        :key="tag.id"
                                        :style="{ backgroundColor: tag.color + '22', color: tag.color }"
                                    >{{ tag.name }}</span>
                                </div>
                                <p class="rsv-body">{{ post.body }}</p>
                                <div class="rsv-meta">
                                    <span>{{ post.author?.name ?? 'Equip' }}</span>
                                    <span>{{ formatDate(post.created_at) }}</span>
                                </div>
                            </Link>
                        </div>
                    </div>
                </div>
                <button v-if="useCarousel" type="button" class="rsv-arrow" aria-label="Següent" @click="next">
                    ›
                </button>
            </div>
            <div v-else class="rsv-empty">{{ t('welcome.empty') }}</div>
        </section>

        <footer>© ReservaHores · {{ t('welcome.footer') }}</footer>
    </div>
</template>
