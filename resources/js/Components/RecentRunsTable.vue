<script setup lang="ts">
import type { RunListItem, RunStatus, StatusTone } from '@/types';
import { Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{
    runs: RunListItem[];
}>();

type FilterKey = 'all' | 'failed' | 'review';

const FILTERS: { key: FilterKey; label: string; status: RunStatus | null }[] = [
    { key: 'all', label: 'All', status: null },
    { key: 'failed', label: 'Failed', status: 'failed' },
    { key: 'review', label: 'Review', status: 'needs_review' },
];

const active = ref<FilterKey>('all');

const counts = computed<Record<FilterKey, number>>(() => ({
    all: props.runs.length,
    failed: props.runs.filter((run) => run.status === 'failed').length,
    review: props.runs.filter((run) => run.status === 'needs_review').length,
}));

const visibleRuns = computed(() => {
    const filter = FILTERS.find((entry) => entry.key === active.value);

    if (!filter || filter.status === null) {
        return props.runs;
    }

    return props.runs.filter((run) => run.status === filter.status);
});

const PILL: Record<StatusTone, string> = {
    completed: 'border-status-completed/40 bg-status-completed/10 text-status-completed',
    review: 'border-status-review/40 bg-status-review/10 text-status-review',
    critical: 'border-status-critical/40 bg-status-critical/10 text-status-critical',
    info: 'border-status-info/40 bg-status-info/10 text-status-info',
};

const DOT: Record<StatusTone, string> = {
    completed: 'bg-status-completed',
    review: 'bg-status-review',
    critical: 'bg-status-critical',
    info: 'bg-status-info',
};

/** The run key is the keyboard-reachable link; the row is the pointer target. */
const open = (id: number): void => {
    router.visit(`/runs/${id}`);
};
</script>

<template>
    <section class="glass-panel p-5" aria-labelledby="recent-runs-heading">
        <header class="relative flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 id="recent-runs-heading" class="panel-heading">Recent Runs</h2>
                <p class="panel-note mt-0.5">
                    Latest {{ runs.length }} execution{{ runs.length === 1 ? '' : 's' }} across the fleet
                </p>
            </div>

            <div
                class="flex items-center gap-1 rounded-lg border border-[rgba(160,205,245,0.12)] bg-white/[0.03] p-1"
                role="tablist"
                aria-label="Filter runs by status"
            >
                <button
                    v-for="filter in FILTERS"
                    :key="filter.key"
                    type="button"
                    role="tab"
                    :aria-selected="active === filter.key"
                    class="rounded-md px-3 py-1 text-xs font-medium transition duration-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-cyan"
                    :class="
                        active === filter.key
                            ? 'bg-accent-cyan/15 text-accent-cyan shadow-[inset_0_0_0_1px_rgba(45,226,230,0.3)]'
                            : 'text-ink-500 hover:bg-white/5 hover:text-ink-200'
                    "
                    @click="active = filter.key"
                >
                    {{ filter.label }}
                    <span class="ml-1 font-mono text-[0.65rem] opacity-70">
                        {{ counts[filter.key] }}
                    </span>
                </button>
            </div>
        </header>

        <div class="relative mt-4 overflow-x-auto">
            <table class="w-full min-w-[46rem] border-collapse text-left">
                <thead>
                    <tr class="border-b border-[rgba(160,205,245,0.12)]">
                        <th scope="col" class="panel-eyebrow py-2 pr-4 font-semibold">Run</th>
                        <th scope="col" class="panel-eyebrow py-2 pr-4 font-semibold">Workflow</th>
                        <th scope="col" class="panel-eyebrow py-2 pr-4 font-semibold">Agent · Step</th>
                        <th scope="col" class="panel-eyebrow py-2 pr-4 text-right font-semibold">Latency</th>
                        <th scope="col" class="panel-eyebrow py-2 pr-4 text-right font-semibold">Tokens</th>
                        <th scope="col" class="panel-eyebrow py-2 pr-4 text-right font-semibold">Cost</th>
                        <th scope="col" class="panel-eyebrow py-2 pr-4 font-semibold">Status</th>
                        <th scope="col" class="panel-eyebrow py-2 text-right font-semibold">Started</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="run in visibleRuns"
                        :key="run.id"
                        class="cursor-pointer border-b border-[rgba(160,205,245,0.06)] transition duration-150 last:border-0 hover:bg-white/[0.035]"
                        @click="open(run.id)"
                    >
                        <td class="py-3 pr-4">
                            <Link
                                :href="`/runs/${run.id}`"
                                class="rounded font-mono text-sm text-ink-100 transition duration-150 hover:text-accent-cyan focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-cyan"
                                :aria-label="`Open run ${run.run_key} in the Run Inspector`"
                                @click.stop
                            >
                                #{{ run.run_key }}
                            </Link>
                        </td>
                        <td class="py-3 pr-4 text-sm text-ink-300">{{ run.workflow }}</td>
                        <td class="py-3 pr-4">
                            <span class="font-mono text-xs text-accent-cyan/85">{{ run.agent }}</span>
                            <span class="text-xs text-ink-900"> · </span>
                            <span class="text-xs text-ink-500">{{ run.step }}</span>
                        </td>
                        <td class="py-3 pr-4 text-right font-mono text-xs text-ink-300">
                            {{ run.latency_label }}
                        </td>
                        <td class="py-3 pr-4 text-right font-mono text-xs text-ink-300">
                            {{ run.tokens_label }}
                        </td>
                        <td class="py-3 pr-4 text-right font-mono text-xs text-ink-300">
                            {{ run.cost_label }}
                        </td>
                        <td class="py-3 pr-4">
                            <span
                                class="inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-[0.65rem] font-semibold"
                                :class="PILL[run.tone]"
                            >
                                <span
                                    class="h-1.5 w-1.5 rounded-full"
                                    :class="DOT[run.tone]"
                                    aria-hidden="true"
                                />
                                {{ run.status_label }}
                            </span>
                        </td>
                        <td class="py-3 text-right font-mono text-xs text-ink-600">
                            {{ run.created_at_label }}
                        </td>
                    </tr>

                    <tr v-if="visibleRuns.length === 0">
                        <td colspan="8" class="py-8 text-center text-sm text-ink-600">
                            No runs match this filter.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</template>
