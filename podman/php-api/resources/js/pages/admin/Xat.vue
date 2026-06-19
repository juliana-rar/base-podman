<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { MessageSquare, Search, Send } from '@lucide/vue';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useI18n } from '@/lib/i18n';
import '../../../css/reserva/admin.css';
import '../../../css/reserva/chat.css';

interface Thread {
    id: number;
    name: string;
    email: string;
    last: string | null;
    last_at: string | null;
    unread: number;
}

interface Msg {
    id: number;
    sender: string;
    body: string;
    created_at: string;
}

const props = defineProps<{
    threads: Thread[];
    activeUser: { id: number; name: string; email: string } | null;
    messages: Msg[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Xat', href: '/admin/xat' }],
    },
});

const { t, localeTag } = useI18n();

const form = useForm<{ body: string }>({ body: '' });
const listEl = ref<HTMLElement | null>(null);

function scrollDown(): void {
    nextTick(() => {
        if (listEl.value) {
            listEl.value.scrollTop = listEl.value.scrollHeight;
        }
    });
}

function time(iso: string): string {
    return new Date(iso).toLocaleTimeString(localeTag(), { hour: '2-digit', minute: '2-digit' });
}

function dayTime(iso: string | null): string {
    if (!iso) {
        return '';
    }
    return new Date(iso).toLocaleDateString(localeTag(), { day: '2-digit', month: '2-digit' });
}

function initials(name: string): string {
    return (
        name
            .split(/\s+/)
            .filter(Boolean)
            .slice(0, 2)
            .map((w) => w[0]?.toUpperCase() ?? '')
            .join('') || '?'
    );
}

// Cerca de converses per nom o email.
const search = ref('');
const filteredThreads = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) {
        return props.threads;
    }
    return props.threads.filter(
        (th) => th.name.toLowerCase().includes(q) || th.email.toLowerCase().includes(q),
    );
});

// Canvi de conversa fluid: recàrrega parcial (manté la llista, la cerca i el scroll).
function openThread(id: number): void {
    router.get(
        '/admin/xat',
        { user: id },
        { only: ['activeUser', 'messages', 'threads'], preserveState: true, preserveScroll: true },
    );
}

function send(): void {
    if (!props.activeUser || !form.body.trim()) {
        return;
    }
    form.post(`/admin/xat/${props.activeUser.id}`, {
        preserveScroll: true,
        onSuccess: () => form.reset('body'),
    });
}

// Refresc periòdic (polling) de fils i conversa activa.
let timer: ReturnType<typeof setInterval> | undefined;
onMounted(() => {
    scrollDown();
    timer = setInterval(() => router.reload({ only: ['threads', 'messages'] }), 5000);
});
onBeforeUnmount(() => clearInterval(timer));
watch(() => props.messages.length, scrollDown);
</script>

<template>
    <Head :title="t('xat.title')" />

    <div id="rsv-xat">
        <header class="cht-pagehead">
            <h1>{{ t('xat.title') }}</h1>
            <p>{{ t('xat.adminSubtitle') }}</p>
        </header>

        <div class="cht-layout">
            <aside class="cht-threads">
                <div class="cht-search">
                    <Search class="cht-search-icon" />
                    <input v-model="search" type="search" :placeholder="t('usr.searchPh')" />
                </div>

                <div class="cht-threadlist">
                    <div v-if="!filteredThreads.length" class="cht-empty cht-empty-side">
                        <MessageSquare class="cht-empty-icon" />
                        <span>{{ threads.length ? t('xat.noResults') : t('xat.noThreads') }}</span>
                    </div>
                    <button
                        v-for="th in filteredThreads"
                        :key="th.id"
                        type="button"
                        class="cht-thread"
                        :class="{ 'is-active': activeUser?.id === th.id }"
                        @click="openThread(th.id)"
                    >
                        <span class="cht-ava">{{ initials(th.name) }}</span>
                        <span class="cht-thread-main">
                            <span class="cht-thread-top">
                                <span class="cht-thread-name">{{ th.name }}</span>
                                <span class="cht-thread-time">{{ dayTime(th.last_at) }}</span>
                            </span>
                            <span class="cht-thread-last">{{ th.last ?? '' }}</span>
                        </span>
                        <span v-if="th.unread" class="cht-badge">{{ th.unread }}</span>
                    </button>
                </div>
            </aside>

            <div class="cht-card cht-conv">
                <transition name="cht-swap" mode="out-in">
                    <div :key="activeUser?.id ?? 'none'" class="cht-conv-inner">
                        <template v-if="activeUser">
                            <header class="cht-head">
                                <span class="cht-ava">{{ initials(activeUser.name) }}</span>
                                <div class="cht-head-text">
                                    <strong>{{ activeUser.name }}</strong>
                                    <small>{{ activeUser.email }}</small>
                                </div>
                            </header>

                            <div ref="listEl" class="cht-list">
                                <div v-if="!messages.length" class="cht-empty">
                                    <Send class="cht-empty-icon" />
                                    <span>{{ t('xat.empty') }}</span>
                                </div>
                                <div
                                    v-for="m in messages"
                                    :key="m.id"
                                    class="cht-row"
                                    :class="{ mine: m.sender === 'admin', system: m.sender === 'system' }"
                                >
                                    <div v-if="m.sender === 'system'" class="cht-system">{{ m.body }}</div>
                                    <template v-else>
                                        <span v-if="m.sender !== 'admin'" class="cht-ava cht-ava-sm">{{ initials(activeUser.name) }}</span>
                                        <div class="cht-bubble">
                                            <span class="cht-text">{{ m.body }}</span>
                                            <span class="cht-time">{{ time(m.created_at) }}</span>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            <form class="cht-composer" @submit.prevent="send">
                                <input v-model="form.body" type="text" maxlength="2000" :placeholder="t('xat.placeholder')" />
                                <button type="submit" class="cht-send" :disabled="form.processing || !form.body.trim()" :aria-label="t('xat.send')">
                                    <Send />
                                </button>
                            </form>
                        </template>
                        <div v-else class="cht-pick">
                            <MessageSquare class="cht-empty-icon" />
                            <span>{{ t('xat.pick') }}</span>
                        </div>
                    </div>
                </transition>
            </div>
        </div>
    </div>
</template>
