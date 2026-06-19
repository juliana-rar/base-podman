<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import AppNavbar from '@/components/AppNavbar.vue';
import { useI18n } from '@/lib/i18n';
import '../../css/reserva/navbar.css';
import '../../css/reserva/chat.css';

interface Msg {
    id: number;
    sender: string;
    body: string;
    created_at: string;
}

const props = defineProps<{ messages: Msg[] }>();

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
    if (!form.body.trim()) {
        return;
    }
    form.post('/xat', {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => form.reset('body'),
    });
}

// Refresc periòdic (polling) per veure missatges nous de l'equip.
let timer: ReturnType<typeof setInterval> | undefined;
onMounted(() => {
    scrollDown();
    timer = setInterval(
        () => router.reload({ only: ['messages'] }),
        5000,
    );
});
onBeforeUnmount(() => clearInterval(timer));
watch(() => props.messages.length, scrollDown);
</script>

<template>
    <Head :title="t('xat.title')" />

    <div id="rsv-welcome">
        <AppNavbar />

        <section class="rsv-chat">
            <h1>{{ t('xat.title') }}</h1>
            <p class="rsv-chat-sub">{{ t('xat.subtitle') }}</p>

            <div ref="listEl" class="rsv-chat-list">
                <div v-if="!messages.length" class="rsv-chat-empty">{{ t('xat.empty') }}</div>
                <div
                    v-for="m in messages"
                    :key="m.id"
                    class="rsv-chat-msg"
                    :class="{ mine: m.sender === 'user', system: m.sender === 'system' }"
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
        </section>
    </div>
</template>
