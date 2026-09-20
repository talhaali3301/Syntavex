<script setup lang="ts">
import RunExpansionPanel from '@/Components/RunExpansionPanel.vue';
import type { RunRow, StatusTone } from '@/types';

defineProps<{
    runs: RunRow[];
    expandedId: number | null;
}>();

const emit = defineEmits<{
    (e: 'toggle', id: number): void;
}>();

/** Mirrors the mockup's column track exactly. */
const GRID =
    'grid grid-cols-[4px_104px_minmax(0,1.05fr)_minmax(0,1.35fr)_136px_92px_90px_92px_118px] items-center';

const EDGE: Record<StatusTone, string> = {
    completed: 'bg-status-completed',
    review: 'bg-status-review',
    critical: 'bg-status-critical',
    info: 'bg-status-info',
};

const EDGE_ACTIVE: Record<StatusTone, string> = {
    completed: 'bg-status-completed shadow-[0_0_14px_rgba(85,217,139,0.8)]',
    review: 'bg-status-review shadow-[0_0_14px_rgba(248,198,93,0.8)]',
    critical: 'bg-status-critical shadow-[0_0_14px_rgba(242,108,120,0.8)]',
    info: 'bg-status-info shadow-[0_0_14px_rgba(90,168,255,0.8)]',
};

const PILL: Record<StatusTone, string> = {
    completed: 'text-status-completed bg-status-completed/[0.12] border-status-completed/[0.32]',
    review: 'text-status-review bg-status-review/[0.12] border-status-review/[0.32]',
    critical: 'text-status-critical bg-status-critical/[0.12] border-status-critical/[0.32]',
    info: 'text-status-info bg-status-info/[0.12] border-status-info/[0.32]',
};
</script>

<template>
    <div
        class="overflow-hidden rounded-2xl border border-[rgba(160,205,245,0.10)] bg-panel-base shadow-[0_20px_48px_rgba(2,8,18,0.55)]"
    >
        <!-- Header -->
        <div
            :class="GRID"
            class="border-b border-[rgba(160,205,245,0.10)] bg-panel-head py-2.5 pr-5 font-mono text-[9.5px] font-medium tracking-[0.12em] text-ink-800"
            role="row"
        >
            <span aria-hidden="true" />
            <span class="pl-4">RUN ID</span>
            <span>WORKFLOW</span>
            <span>AGENT / OBJECTIVE</span>
            <span>STARTED</span>
            <span>DURATION</span>
            <span>TOKENS</span>
            <span>COST</span>
            <span class="text-right">STATUS</span>
        </div>

        <!-- Rows -->
        <div
            v-for="run in runs"
            :key="run.id"
            :class="
                expandedId === run.id
                    ? 'border-b border-accent-cyan/[0.18] bg-panel-row shadow-[inset_0_0_40px_rgba(45,226,230,0.05)]'
                    : 'border-b border-[rgba(160,205,245,0.06)] last:border-b-0'
            "
        >
            <button
                type="button"
                :class="GRID"
                class="w-full cursor-pointer py-3 pr-5 text-left transition duration-150 hover:bg-panel-hover hover:shadow-[inset_0_0_0_1px_rgba(45,226,230,0.16),0_8px_22px_rgba(0,0,0,0.35)] focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-accent-cyan"
                :aria-expanded="expandedId === run.id"
                :aria-controls="`run-panel-${run.id}`"
                @click="emit('toggle', run.id)"
            >
                <span
                    class="h-full self-stretch"
                    :class="expandedId === run.id ? EDGE_ACTIVE[run.tone] : `${EDGE[run.tone]} opacity-85`"
                    aria-hidden="true"
                />

                <span
                    class="flex items-center gap-1.5 pl-4 font-mono text-[11.5px] font-medium transition duration-150"
                    :class="
                        expandedId === run.id
                            ? 'text-glow-cyan [text-shadow:0_0_12px_rgba(45,226,230,0.65)]'
                            : 'text-glow-blue'
                    "
                >
                    <svg
                        class="h-[11px] w-[11px] shrink-0 text-accent-cyan transition-transform duration-200"
                        :class="expandedId === run.id ? 'rotate-0' : '-rotate-90'"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2.4"
                        aria-hidden="true"
                    >
                        <path d="m6 9 6 6 6-6" />
                    </svg>
                    #{{ run.run_key }}
                </span>

                <span
                    class="truncate pr-2.5 text-xs"
                    :class="expandedId === run.id ? 'font-medium text-glow-violet' : 'text-ink-400'"
                >
                    {{ run.workflow }}
                </span>

                <span class="flex min-w-0 items-center gap-2.5 pr-2.5">
                    <span class="truncate text-[12.5px] font-medium" :class="expandedId === run.id ? 'text-ink-100' : 'text-ink-200'">
                        {{ run.objective }}
                    </span>
                    <span class="shrink-0 font-mono text-[10px] text-ink-900">{{ run.agent }}</span>
                </span>

                <span class="font-mono text-[11px] text-ink-600">{{ run.started_label }}</span>
                <span class="font-mono text-[11px] text-ink-300">{{ run.duration_label }}</span>
                <span class="font-mono text-[11px] text-ink-600">{{ run.tokens_label }}</span>
                <span class="font-mono text-[11px] font-medium text-ink-300">{{ run.cost_label }}</span>

                <span
                    class="justify-self-end rounded-[5px] border px-2 py-1 font-mono text-[9.5px] font-medium tracking-[0.08em]"
                    :class="PILL[run.tone]"
                >
                    {{ run.status_label }}
                </span>
            </button>

            <div :id="`run-panel-${run.id}`" v-show="expandedId === run.id">
                <RunExpansionPanel
                    v-if="run.expansion"
                    :run-id="run.id"
                    :expansion="run.expansion"
                />
            </div>
        </div>

        <slot name="footer" />
    </div>
</template>
