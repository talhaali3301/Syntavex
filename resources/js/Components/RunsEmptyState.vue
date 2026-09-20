<script setup lang="ts">
import type { RunsFilters, WorkflowOption } from '@/types';
import { computed } from 'vue';

const props = defineProps<{
    filters: RunsFilters;
    workflowOptions: WorkflowOption[];
}>();

const emit = defineEmits<{
    (e: 'clear'): void;
    (e: 'widen'): void;
}>();

const workflowName = computed(
    () => props.workflowOptions.find((option) => option.id === props.filters.workflow)?.name ?? null,
);

const statusLabel = computed(() =>
    props.filters.status === 'all' ? null : props.filters.status.replace('_', ' '),
);
</script>

<template>
    <div class="relative overflow-hidden px-8 py-12">
        <div
            class="pointer-events-none absolute -top-44 left-[40%] h-[600px] w-[700px] rounded-full bg-accent-violet/[0.14] blur-3xl"
            aria-hidden="true"
        />

        <div
            class="relative mx-auto flex max-w-[35rem] flex-col items-center gap-3.5 rounded-[18px] border border-[rgba(160,205,245,0.14)] bg-gradient-to-br from-[rgba(40,58,86,0.38)] to-[rgba(12,22,38,0.55)] px-10 py-9 shadow-[0_24px_56px_rgba(2,8,18,0.6)] backdrop-blur-xl"
        >
            <svg class="h-[62px] w-[62px]" viewBox="0 0 24 24" fill="none" stroke="rgba(45,226,230,0.55)" stroke-width="1.1" aria-hidden="true">
                <circle cx="11" cy="11" r="7.2" />
                <path d="m16.4 16.4 4.4 4.4" />
                <path d="M8.2 11h5.6" stroke="rgba(139,124,255,0.8)" />
            </svg>

            <h3 class="font-display text-[17px] font-semibold text-ink-100">
                No runs match these filters
            </h3>

            <p class="text-center text-[12.5px] leading-relaxed text-ink-500">
                Nothing in
                <span class="font-mono text-ink-300">{{ filters.range_label }}</span>
                <template v-if="workflowName">
                    matches workflow <span class="font-mono text-glow-violet">{{ workflowName }}</span>
                </template>
                <template v-if="statusLabel">
                    with status <span class="font-mono text-glow-red">{{ statusLabel }}</span>
                </template>
                <template v-if="filters.search">
                    for <span class="font-mono text-glow-cyan">“{{ filters.search }}”</span>
                </template>.
                Widen the date range or clear a filter.
            </p>

            <div class="mt-1.5 flex flex-wrap justify-center gap-2.5">
                <button
                    type="button"
                    class="rounded-lg border border-accent-cyan/45 px-[15px] py-2.5 font-mono text-[10.5px] font-semibold tracking-[0.06em] text-glow-cyan transition duration-200 hover:bg-accent-cyan/[0.14] hover:shadow-[0_0_20px_rgba(45,226,230,0.22)] focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-cyan"
                    @click="emit('clear')"
                >
                    CLEAR ALL FILTERS
                </button>
                <button
                    type="button"
                    class="rounded-lg border border-[rgba(160,205,245,0.16)] px-[15px] py-2.5 font-mono text-[10.5px] font-semibold tracking-[0.06em] text-ink-400 transition duration-200 hover:border-[rgba(160,205,245,0.35)] hover:text-ink-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-cyan"
                    @click="emit('widen')"
                >
                    WIDEN TO 90 DAYS
                </button>
            </div>
        </div>
    </div>
</template>
