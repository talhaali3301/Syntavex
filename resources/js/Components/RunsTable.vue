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

/**
 * The mockup track is 650px of fixed columns, so columns are shed as the
 * viewport narrows rather than letting the fixed cells crush the two fluid
 * ones. The track and the per-column breakpoints below are the single source
 * of truth, and each `AT_*` class is applied to both the header cell and the
 * row cell so a column can never survive in one and vanish from the other.
 *
 * The thresholds are deliberately not Tailwind's md/lg/xl. Those measure the
 * viewport, but the table only ever gets `viewport - 140px` (76px nav rail +
 * 2×32px gutters), so at a 1024px viewport the lg track was being handed 884px
 * and the objective cell collapsed to an ellipsis. Each threshold is therefore
 * its track's fixed width plus the ~520px the two fluid cells need to stay
 * readable, plus that 140px of chrome.
 *
 * Below the dropped columns' thresholds the values move into the objective
 * cell's meta line rather than leaving the screen.
 */
const GRID = [
    'grid items-center',
    'grid-cols-[4px_104px_minmax(0,1fr)_118px]',
    'min-[960px]:grid-cols-[4px_104px_minmax(0,1.05fr)_minmax(0,1.35fr)_118px]',
    'min-[1150px]:grid-cols-[4px_104px_minmax(0,1.05fr)_minmax(0,1.35fr)_150px_92px_118px]',
    'min-[1330px]:grid-cols-[4px_104px_minmax(0,1.05fr)_minmax(0,1.35fr)_150px_92px_90px_92px_118px]',
].join(' ');

const AT_WORKFLOW = 'hidden min-[960px]:block';

const AT_TIMING = 'hidden min-[1150px]:block';

const AT_USAGE = 'hidden min-[1330px]:block';

/** Inverses of the above: a value shows in the meta line exactly while its own column is gone. */
const BELOW_WORKFLOW = 'min-[960px]:hidden';

const BELOW_TIMING = 'min-[1150px]:hidden';

const BELOW_USAGE = 'min-[1330px]:hidden';

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
        class="glass-panel glass-panel-solid"
    >
        <!-- Header -->
        <div
            :class="GRID"
            class="border-b border-[rgba(160,205,245,0.10)] bg-panel-head py-2.5 pr-5 font-mono text-[9.5px] font-medium tracking-[0.12em] text-ink-800"
            role="row"
        >
            <span aria-hidden="true" />
            <span class="pl-4">RUN ID</span>
            <span :class="AT_WORKFLOW">WORKFLOW</span>
            <span>AGENT / OBJECTIVE</span>
            <span class="pr-3" :class="AT_TIMING">STARTED</span>
            <span class="pr-3" :class="AT_TIMING">DURATION</span>
            <span class="pr-3" :class="AT_USAGE">TOKENS</span>
            <span class="pr-3" :class="AT_USAGE">COST</span>
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
                    :class="[
                        AT_WORKFLOW,
                        expandedId === run.id ? 'font-medium text-glow-violet' : 'text-ink-400',
                    ]"
                >
                    {{ run.workflow }}
                </span>

                <span class="flex min-w-0 flex-col gap-0.5 pr-2.5 min-[1330px]:flex-row min-[1330px]:items-center min-[1330px]:gap-2.5">
                    <span class="flex min-w-0 items-center gap-2.5">
                        <span class="truncate text-[12.5px] font-medium" :class="expandedId === run.id ? 'text-ink-100' : 'text-ink-200'">
                            {{ run.objective }}
                        </span>
                        <span class="shrink-0 font-mono text-[10px] text-ink-900">{{ run.agent }}</span>
                    </span>

                    <span class="flex items-center gap-3 font-mono text-[10px] text-ink-800" :class="BELOW_USAGE">
                        <span class="truncate text-ink-700" :class="BELOW_WORKFLOW">{{ run.workflow }}</span>
                        <span :class="BELOW_TIMING">{{ run.started_label }}</span>
                        <span :class="BELOW_TIMING">{{ run.duration_label }}</span>
                        <span>{{ run.tokens_label }}</span>
                        <span>{{ run.cost_label }}</span>
                    </span>
                </span>

                <span class="pr-3 font-mono text-[11px] text-ink-600" :class="AT_TIMING">{{ run.started_label }}</span>
                <span class="pr-3 font-mono text-[11px] text-ink-300" :class="AT_TIMING">{{ run.duration_label }}</span>
                <span class="pr-3 font-mono text-[11px] text-ink-600" :class="AT_USAGE">{{ run.tokens_label }}</span>
                <span class="pr-3 font-mono text-[11px] font-medium text-ink-300" :class="AT_USAGE">{{ run.cost_label }}</span>

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
