<script setup lang="ts">
import { ref } from 'vue';
import { useI18n } from '@/lib/i18n';

// Una imatge de la galeria: o bé existent (té `path`) o bé nova pujada (té `file`).
export interface ImageItem {
    path?: string;
    url: string;
    file?: File;
    caption?: string;
}

const props = withDefaults(
    defineProps<{
        modelValue: ImageItem[];
        max?: number;
        captions?: boolean;
    }>(),
    { max: 10, captions: false },
);

const emit = defineEmits<{ 'update:modelValue': [ImageItem[]] }>();

const { t } = useI18n();

function commit(list: ImageItem[]): void {
    emit('update:modelValue', list);
}

function onFiles(event: Event): void {
    const input = event.target as HTMLInputElement;
    const files = Array.from(input.files ?? []);
    const room = Math.max(0, props.max - props.modelValue.length);
    const added: ImageItem[] = files.slice(0, room).map((file) => ({ url: URL.createObjectURL(file), file }));
    commit([...props.modelValue, ...added]);
    input.value = '';
}

function removeAt(index: number): void {
    const item = props.modelValue[index];
    if (item.file && item.url.startsWith('blob:')) {
        URL.revokeObjectURL(item.url);
    }
    commit(props.modelValue.filter((_, i) => i !== index));
}

function move(index: number, delta: number): void {
    reorder(index, index + delta);
}

// Mou l'element de `from` a `to` (utilitzat per fletxes i per drag & drop).
function reorder(from: number, to: number): void {
    if (to < 0 || to >= props.modelValue.length || from === to) {
        return;
    }
    const list = [...props.modelValue];
    const [moved] = list.splice(from, 1);
    list.splice(to, 0, moved);
    commit(list);
}

function onCaption(index: number, value: string): void {
    const list = props.modelValue.map((item, i) => (i === index ? { ...item, caption: value } : item));
    commit(list);
}

// --- Drag & drop natiu (sense dependències) ---
const dragIndex = ref<number | null>(null);
const overIndex = ref<number | null>(null);

function onDragStart(index: number, event: DragEvent): void {
    dragIndex.value = index;
    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', String(index));
    }
}

function onDragOver(index: number, event: DragEvent): void {
    if (dragIndex.value === null) {
        return;
    }
    event.preventDefault();
    overIndex.value = index;
    if (event.dataTransfer) {
        event.dataTransfer.dropEffect = 'move';
    }
}

function onDrop(index: number): void {
    if (dragIndex.value !== null) {
        reorder(dragIndex.value, index);
    }
    onDragEnd();
}

function onDragEnd(): void {
    dragIndex.value = null;
    overIndex.value = null;
}
</script>

<template>
    <div class="rsv-imgs">
        <div
            v-for="(img, i) in modelValue"
            :key="img.path ?? img.url"
            class="rsv-imgs-item"
            :class="{ 'is-dragging': dragIndex === i, 'is-over': overIndex === i && dragIndex !== i }"
        >
            <div
                class="rsv-imgs-frame"
                draggable="true"
                @dragstart="onDragStart(i, $event)"
                @dragover="onDragOver(i, $event)"
                @drop="onDrop(i)"
                @dragend="onDragEnd"
            >
                <span class="rsv-imgs-grip" :title="t('srv.drag')">⠿</span>
                <img :src="img.url" alt="" />
                <span v-if="i === 0" class="rsv-imgs-cover">{{ t('srv.cover') }}</span>
                <div class="rsv-imgs-actions">
                    <button type="button" :disabled="i === 0" :title="t('srv.moveUp')" @click="move(i, -1)">‹</button>
                    <button type="button" :disabled="i === modelValue.length - 1" :title="t('srv.moveDown')" @click="move(i, 1)">›</button>
                    <button type="button" class="rsv-imgs-del" :title="t('srv.removeImg')" @click="removeAt(i)">×</button>
                </div>
            </div>
            <input
                v-if="captions"
                class="rsv-imgs-caption"
                type="text"
                maxlength="120"
                :value="img.caption ?? ''"
                :placeholder="t('srv.caption')"
                @input="onCaption(i, ($event.target as HTMLInputElement).value)"
            />
        </div>
        <label v-if="modelValue.length < max" class="rsv-imgs-add">
            <span>＋ {{ t('srv.addImages') }}</span>
            <input type="file" accept="image/*" multiple @change="onFiles" />
        </label>
    </div>
</template>
