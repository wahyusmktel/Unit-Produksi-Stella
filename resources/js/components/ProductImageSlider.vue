<script setup lang="ts">
import { ChevronLeft, ChevronRight, Image as ImageIcon } from '@lucide/vue';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        images: string[];
        alt: string;
        interval?: number;
    }>(),
    { interval: 4000 },
);

const activeIndex = ref(0);
let timer: ReturnType<typeof setInterval> | null = null;

function clearTimer(): void {
    if (timer) {
        clearInterval(timer);
        timer = null;
    }
}

function startTimer(): void {
    clearTimer();

    if (props.images.length > 1) {
        timer = setInterval(next, props.interval);
    }
}

function next(): void {
    activeIndex.value = (activeIndex.value + 1) % props.images.length;
}

function previous(): void {
    activeIndex.value =
        (activeIndex.value - 1 + props.images.length) % props.images.length;
}

function goTo(index: number): void {
    activeIndex.value = index;
    startTimer();
}

watch(
    () => props.images,
    () => {
        activeIndex.value = 0;
        startTimer();
    },
    { deep: true },
);

onMounted(startTimer);
onBeforeUnmount(clearTimer);
</script>

<template>
    <div class="product-image-slider">
        <img
            v-if="images.length"
            :src="images[activeIndex]"
            :alt="`${alt} - foto ${activeIndex + 1}`"
        />
        <ImageIcon v-else :size="22" />

        <template v-if="images.length > 1">
            <button
                type="button"
                class="product-image-slider__arrow product-image-slider__arrow--left"
                title="Foto sebelumnya"
                @click.stop="previous"
            >
                <ChevronLeft :size="14" />
            </button>
            <button
                type="button"
                class="product-image-slider__arrow product-image-slider__arrow--right"
                title="Foto berikutnya"
                @click.stop="next"
            >
                <ChevronRight :size="14" />
            </button>
            <div class="product-image-slider__dots" aria-label="Navigasi foto">
                <button
                    v-for="(_, index) in images"
                    :key="index"
                    type="button"
                    :class="{ active: index === activeIndex }"
                    :aria-label="`Tampilkan foto ${index + 1}`"
                    @click.stop="goTo(index)"
                ></button>
            </div>
            <span class="product-image-slider__count"
                >{{ activeIndex + 1 }}/{{ images.length }}</span
            >
        </template>
    </div>
</template>
