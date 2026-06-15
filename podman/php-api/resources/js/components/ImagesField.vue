<script setup lang="ts">
import { useI18n } from '@/lib/i18n';

// Una imatge de la galeria: o bé existent (té `path`) o bé nova pujada (té `file`).
export interface ImageItem {
    path?: string;
    url: string;
    file?: File;
}

const props = withDefaults(
    defineProps<{
        modelValue: ImageItem[];
        max?: number;
    }>(),
    { max: 10 },
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
    const target = index + delta;
    if (target < 0 || target >= props.modelValue.length) {
        return;
    }
    const list = [...props.modelValue];
    [list[index], list[target]] = [list[target], list[index]];
    commit(list);
}
</script>

<template>
    <div class="rsv-imgs">
        <div v-for="(img, i) in modelValue" :key="img.path ?? img.url" class="rsv-imgs-item">
            <img :src="img.url" alt="" />
            <span v-if="i === 0" class="rsv-imgs-cover">{{ t('srv.cover') }}</span>
            <div class="rsv-imgs-actions">
                <button type="button" :disabled="i === 0" :title="t('srv.moveUp')" @click="move(i, -1)">‹</button>
                <button type="button" :disabled="i === modelValue.length - 1" :title="t('srv.moveDown')" @click="move(i, 1)">›</button>
                <button type="button" class="rsv-imgs-del" :title="t('srv.removeImg')" @click="removeAt(i)">×</button>
            </div>
        </div>
        <label v-if="modelValue.length < max" class="rsv-imgs-add">
            <span>＋ {{ t('srv.addImages') }}</span>
            <input type="file" accept="image/*" multiple @change="onFiles" />
        </label>
    </div>
</template>
