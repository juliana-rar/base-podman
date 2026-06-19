<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { Send } from '@lucide/vue';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
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
const page = usePage();

const companyName = computed(() => String(page.props.siteName ?? 'Empresa'));
const bizInitial = computed(() => companyName.value.trim()[0]?.toUpperCase() ?? '·');

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

function send(): void {
    if (!form.body.trim()) {
        return;
    }
    form.post('/xat', {
        preserveScroll: true,
        onSuccess: () => form.reset('body'),
    });
}

// Refresc periòdic (polling) per veure missatges nous de l'equip.
let timer: ReturnType<typeof setInterval> | undefined;
onMounted(() => {
    scrollDown();
    timer = setInterval(() => router.reload({ only: ['messages'] }), 5000);
});
onBeforeUnmount(() => clearInterval(timer));
watch(() => props.messages.length, scrollDown);
</script>

<template>
    <Head :title="t('xat.title')" />

    <div id="rsv-welcome">
        <AppNavbar />

        <section class="cht-page">
            <div class="cht-card">
                <header class="cht-head">
                    <span class="cht-ava cht-ava-biz">{{ bizInitial }}</span>
                    <div class="cht-head-text">
                        <strong>{{ companyName }}</strong>
                        <small>{{ t('xat.subtitle') }}</small>
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
                        :class="{ mine: m.sender === 'user', system: m.sender === 'system' }"
                    >
                        <div v-if="m.sender === 'system'" class="cht-system">{{ m.body }}</div>
                        <template v-else>
                            <span v-if="m.sender !== 'user'" class="cht-ava cht-ava-biz cht-ava-sm">{{ bizInitial }}</span>
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
            </div>
        </section>
    </div>
</template>
