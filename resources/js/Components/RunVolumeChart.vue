<script setup lang="ts">
import type { BarTone, VolumeData } from '@/types';

defineProps<{
    volume: VolumeData;
}>();

const BAR: Record<BarTone, string> = {
    info: 'bg-status-info/[0.28]',
    review: 'bg-status-review/[0.55]',
    critical: 'bg-status-critical/[0.55]',
    accent: 'bg-accent-cyan shadow-[0_0_12px_rgba(45,226,230,0.6)]',
};
</script>

<template>
    <section
        class="glass-panel px-[18px] py-4"
        aria-labelledby="run-volume-heading"
    >
        <h2 id="run-volume-heading" class="panel-eyebrow">
            RUN VOLUME · {{ volume.window_days }}D
        </h2>

        <ul class="mt-3 flex h-11 items-end gap-1" role="list">
            <li
                v-for="bar in volume.bars"
                :key="bar.day"
                class="min-h-[2px] flex-1 rounded-[2px]"
                :class="[
                    BAR[bar.tone],
                    // The newest day always reads as 'now', whatever its status colour.
                    bar.is_latest ? 'ring-1 ring-accent-cyan/70 brightness-125' : '',
                ]"
                :style="{ height: `${bar.percent}%` }"
            >
                <span class="sr-only">{{ bar.label }}: {{ bar.count }} runs</span>
                <span :title="`${bar.label} · ${bar.count} run${bar.count === 1 ? '' : 's'}`" class="block h-full w-full" />
            </li>
        </ul>
    </section>
</template>
