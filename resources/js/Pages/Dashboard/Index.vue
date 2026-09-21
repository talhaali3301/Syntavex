<script setup lang="ts">
import DecisionGraph from '@/Components/DecisionGraph.vue';
import FleetTrustGauge from '@/Components/FleetTrustGauge.vue';
import GovernanceLedger from '@/Components/GovernanceLedger.vue';
import HumanAttentionCard from '@/Components/HumanAttentionCard.vue';
import KpiStatCard from '@/Components/KpiStatCard.vue';
import LiveExecutionPulse from '@/Components/LiveExecutionPulse.vue';
import PageHeader from '@/Components/PageHeader.vue';
import RecentRunsTable from '@/Components/RecentRunsTable.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { CommandCentreProps, DashboardRangeKey } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps<CommandCentreProps>();

const search = ref('');

/**
 * The range selector re-queries the server: every windowed figure on this page
 * (KPIs, Fleet Trust, the Decision Graph and Recent Runs) is recomputed from
 * the runs inside the chosen window. Pending approvals and the Governance
 * Ledger are current/cumulative state, so they deliberately stay whole.
 */
const selectRange = (key: string): void => {
    if (key === props.range.key) {
        return;
    }

    router.get(
        '/dashboard',
        key === '14d' ? {} : { range: key },
        { preserveScroll: true, preserveState: true, replace: true },
    );
};

const rangeKey = ref<DashboardRangeKey>(props.range.key);

watch(
    () => props.range.key,
    (value) => {
        rangeKey.value = value;
    },
);

/** The search box hands off to the Runs Explorer, which owns run search. */
const submitSearch = (): void => {
    const term = search.value.trim();

    router.get('/runs', term ? { search: term } : {});
};

const kpiCards = computed(() => [
    { key: 'total', metric: props.kpis.total_executions, accent: 'cyan' as const },
    { key: 'success', metric: props.kpis.success_rate, accent: 'emerald' as const },
    { key: 'latency', metric: props.kpis.avg_latency, accent: 'violet' as const },
    { key: 'cost', metric: props.kpis.cost_window, accent: 'amber' as const },
]);

const crumb = computed(
    () =>
        `workspace / ${props.workspace.slug} · ${props.workspace.tier.toLowerCase()}` +
        ` · ${props.workspace.active_workflow_count}/${props.workspace.workflow_count} workflows live`,
);

const pulseStats = computed(() => [
    { label: 'Executions', value: props.kpis.total_executions.display },
    { label: 'Avg latency', value: props.kpis.avg_latency.display },
    { label: 'Policies', value: props.governanceLedger.policies_evaluated.display },
]);
</script>

<template>
    <Head title="Command Centre" />

    <AppLayout>
        <div class="flex min-h-screen flex-col">
            <PageHeader tone="accent" :title="workspace.name" :meta="crumb">
                <template #badges>
                    <span
                        class="rounded border border-accent-cyan/35 px-1.5 py-[3px] font-mono text-[10px] font-medium tracking-[0.08em] text-accent-cyan"
                    >
                        OVERVIEW
                    </span>
                </template>

                <template #actions>
                    <div class="relative w-full min-w-[12rem] max-w-xs flex-1">
                        <label for="fleet-search" class="sr-only">Search runs, workflows and agents</label>
                        <svg
                            class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-800"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            aria-hidden="true"
                        >
                            <circle cx="11" cy="11" r="7" />
                            <path d="m20 20-3.5-3.5" stroke-linecap="round" />
                        </svg>
                        <input
                            id="fleet-search"
                            v-model="search"
                            type="search"
                            placeholder="Search runs, workflows, agents…"
                            class="field-input py-2 pl-9 pr-3"
                            @keydown.enter.prevent="submitSearch"
                        />
                    </div>

                    <span
                        class="flex shrink-0 items-center gap-2 rounded-full border border-accent-cyan/35 bg-accent-cyan/10 px-3 py-1.5 font-mono text-[10.5px] font-semibold tracking-[0.08em] text-accent-cyan"
                    >
                        <span
                            class="pulse-breathe h-1.5 w-1.5 rounded-full bg-accent-cyan shadow-[0_0_10px_2px_rgba(45,226,230,0.7)]"
                            aria-hidden="true"
                        />
                        FLEET LIVE
                    </span>

                    <div class="relative shrink-0">
                        <label for="range-select" class="sr-only">
                            Observation window, measured back from the newest run ({{ range.anchor_label }})
                        </label>
                        <select
                            id="range-select"
                            v-model="rangeKey"
                            :title="`Measured back from the newest run · ${range.anchor_label}`"
                            class="field-input w-auto appearance-none py-2 pl-3 pr-9 text-xs text-ink-300"
                            @change="selectRange(($event.target as HTMLSelectElement).value)"
                        >
                            <option
                                v-for="option in range.options"
                                :key="option.key"
                                :value="option.key"
                                class="bg-navy-raised"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                        <svg
                            class="pointer-events-none absolute right-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-ink-700"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            aria-hidden="true"
                        >
                            <path d="m6 9 6 6 6-6" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </div>
                </template>
            </PageHeader>

            <div class="flex flex-1 flex-col gap-[18px] px-8 pb-8 pt-[22px]">
                <div class="grid grid-cols-12 gap-[18px]">
                    <div class="col-span-12 lg:col-span-4 xl:col-span-3">
                        <FleetTrustGauge
                            :score="fleetTrust.score"
                            :band="fleetTrust.band"
                            :components="fleetTrust.components"
                        />
                    </div>

                    <div class="col-span-12 grid grid-cols-1 gap-[18px] sm:grid-cols-2 lg:col-span-8 xl:col-span-9">
                        <KpiStatCard
                            v-for="card in kpiCards"
                            :key="card.key"
                            :label="card.metric.label"
                            :value="card.metric.display"
                            :caption="card.metric.caption"
                            :accent="card.accent"
                        />
                    </div>
                </div>

                <div class="grid grid-cols-12 gap-[18px]">
                    <div class="col-span-12 xl:col-span-8">
                        <DecisionGraph :graph="decisionGraph" class="min-h-[30rem]" />
                    </div>

                    <div class="col-span-12 flex flex-col gap-[18px] xl:col-span-4">
                        <section class="glass-panel p-5" aria-labelledby="human-attention-heading">
                            <div
                                class="pointer-events-none absolute -right-12 -top-12 h-32 w-32 rounded-full bg-status-critical/15 blur-3xl"
                                aria-hidden="true"
                            />

                            <div class="relative flex items-center justify-between gap-2">
                                <div>
                                    <h2 id="human-attention-heading" class="panel-heading">Human Attention</h2>
                                    <p class="panel-note mt-0.5">Approvals blocking autonomous execution</p>
                                </div>
                                <span
                                    class="shrink-0 rounded-full border border-status-critical/40 bg-status-critical/10 px-2 py-0.5 font-mono text-xs text-status-critical"
                                >
                                    {{ humanAttention.length }}
                                </span>
                            </div>

                            <div class="relative mt-4 space-y-3">
                                <HumanAttentionCard
                                    v-for="item in humanAttention"
                                    :key="item.id"
                                    :item="item"
                                />

                                <p
                                    v-if="humanAttention.length === 0"
                                    class="py-6 text-center text-sm text-ink-600"
                                >
                                    Nothing waiting on a human right now.
                                </p>
                            </div>
                        </section>

                        <GovernanceLedger :ledger="governanceLedger" />
                    </div>
                </div>

                <RecentRunsTable :runs="recentRuns" />

                <LiveExecutionPulse :stats="pulseStats" />
            </div>
        </div>
    </AppLayout>
</template>
