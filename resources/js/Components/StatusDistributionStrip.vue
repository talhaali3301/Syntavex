<script setup lang="ts">
import type { DistributionData, StatusTone } from '@/types';

defineProps<{
    distribution: DistributionData;
}>();

const SEGMENT: Record<StatusTone, string> = {
    completed: 'bg-gradient-to-b from-[#6FE4A0] to-status-completed shadow-[0_0_16px_rgba(85,217,139,0.45)]',
    review: 'bg-gradient-to-b from-[#FBD378] to-status-review shadow-[0_0_16px_rgba(248,198,93,0.45)]',
    critical: 'bg-gradient-to-b from-[#F5808B] to-status-critical shadow-[0_0_16px_rgba(242,108,120,0.45)]',
    info: 'bg-gradient-to-b from-[#7FBEFF] to-status-info shadow-[0_0_16px_rgba(90,168,255,0.45)]',
};

const DOT: Record<StatusTone, string> = {
    completed: 'bg-status-completed',
    review: 'bg-status-review',
    critical: 'bg-status-critical',
    info: 'bg-status-info',
};
</script>

<template>
    <section
        class="relative overflow-hidden rounded-2xl border border-[rgba(160,222,245,0.18)] bg-gradient-to-br from-accent-cyan/10 via-[rgba(28,48,76,0.40)] to-accent-violet/[0.12] px-5 pb-[15px] pt-4 shadow-[0_20px_48px_rgba(2,8,18,0.6),inset_0_1px_0_rgba(215,245,255,0.14)] backdrop-blur-2xl"
        aria-labelledby="status-distribution-heading"
    >
        <div
            class="pointer-events-none absolute -top-20 left-[120px] h-[200px] w-[260px] rounded-full bg-accent-cyan/[0.18] blur-3xl"
            aria-hidden="true"
        />

        <div class="relative flex items-center gap-3">
            <h2
                id="status-distribution-heading"
                class="font-mono text-[10.5px] font-semibold tracking-[0.14em] text-glow-teal"
            >
                STATUS DISTRIBUTION
            </h2>
            <p class="font-mono text-[10.5px] text-ink-600">
                {{ distribution.total }} runs · {{ distribution.window_days }} days
            </p>
            <p class="ml-auto font-mono text-[11px] font-medium text-glow-green">
                {{ distribution.success_label }}
            </p>
        </div>

        <div class="relative mt-[13px] flex h-3 gap-[3px] overflow-hidden rounded-md">
            <div
                v-for="segment in distribution.segments"
                :key="segment.status"
                class="min-w-0"
                :class="SEGMENT[segment.tone]"
                :style="{ flexGrow: segment.count, flexBasis: 0 }"
                :title="`${segment.count} ${segment.label} · ${segment.percent}%`"
            />
        </div>

        <div class="relative mt-[11px] flex flex-wrap items-center gap-x-[26px] gap-y-1.5">
            <p
                v-for="segment in distribution.segments"
                :key="segment.status"
                class="flex items-center gap-[7px] font-mono text-[11px] text-ink-500"
            >
                <span class="h-[7px] w-[7px] rounded-full" :class="DOT[segment.tone]" aria-hidden="true" />
                {{ segment.count }} {{ segment.label }}
            </p>
            <p class="ml-auto font-mono text-[11px] text-ink-800">
                {{ distribution.avg_duration_label }} · {{ distribution.cost_window_label }}
            </p>
        </div>
    </section>
</template>
