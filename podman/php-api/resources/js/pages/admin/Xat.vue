<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
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
    return new Date(iso).toLocaleString(localeTag(), { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
}

function send(): void {
    if (!props.activeUser || !form.body.trim()) {
        return;
    }
    form.post(`/admin/xat/${props.activeUser.id}`, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => form.reset('body'),
    });
}

// Refresc periòdic (polling) de fils i conversa activa.
let timer: ReturnType<typeof setInterval> | undefined;
onMounted(() => {
    scrollDown();
    timer = setInterval(
        () => router.reload({ only: ['threads', 'messages'] }),
        5000,
    );
});
onBeforeUnmount(() => clearInterval(timer));
watch(() => props.messages.length, scrollDown);
</script>

<template>
    <Head :title="t('xat.title')" />

    <div id="rsv-xat">
        <header>
            <h1>{{ t('xat.title') }}</h1>
            <p>{{ t('xat.adminSubtitle') }}</p>
        </header>

        <div class="rsv-xat-grid">
            <aside class="rsv-xat-threads">
                <div v-if="!threads.length" class="rsv-empty">{{ t('xat.noThreads') }}</div>
                <Link
                    v-for="th in threads"
                    :key="th.id"
                    :href="`/admin/xat?user=${th.id}`"
                    class="rsv-xat-thread"
                    :class="{ 'is-active': activeUser?.id === th.id }"
                    preserve-scroll
                >
                    <span class="rsv-xat-thread-name">{{ th.name }}</span>
                    <span class="rsv-xat-thread-last">{{ th.last ?? '' }}</span>
                    <span v-if="th.unread" class="rsv-xat-badge">{{ th.unread }}</span>
                </Link>
            </aside>

            <div class="rsv-xat-conv">
                <template v-if="activeUser">
                    <div class="rsv-xat-convhead">
                        {{ activeUser.name }} <small>{{ activeUser.email }}</small>
                    </div>
                    <div ref="listEl" class="rsv-chat-list">
                        <div v-if="!messages.length" class="rsv-chat-empty">{{ t('xat.empty') }}</div>
                        <div
                            v-for="m in messages"
                            :key="m.id"
                            class="rsv-chat-msg"
                            :class="{ mine: m.sender === 'admin', system: m.sender === 'system' }"
                        >
                            <div v-if="m.sender === 'system'" class="rsv-chat-system">{{ m.body }}</div>
                            <template v-else>
                                <div class="rsv-chat-bubble">{{ m.body }}</div>
                                <span class="rsv-chat-time">{{ time(m.created_at) }}</span>
                            </template>
                        </div>
                    </div>
                    <form class="rsv-chat-form" @submit.prevent="send">
                        <input v-model="form.body" type="text" maxlength="2000" :placeholder="t('xat.placeholder')" />
                        <button type="submit" :disabled="form.processing || !form.body.trim()">{{ t('xat.send') }}</button>
                    </form>
                </template>
                <div v-else class="rsv-xat-pick">{{ t('xat.pick') }}</div>
            </div>
        </div>
    </div>
</template>
