<script setup lang="ts">
import DecisionGraph from '@/Components/DecisionGraph.vue';
import FleetTrustGauge from '@/Components/FleetTrustGauge.vue';
import GovernanceLedger from '@/Components/GovernanceLedger.vue';
import HumanAttentionCard from '@/Components/HumanAttentionCard.vue';
import KpiStatCard from '@/Components/KpiStatCard.vue';
import LiveExecutionPulse from '@/Components/LiveExecutionPulse.vue';
import RecentRunsTable from '@/Components/RecentRunsTable.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { CommandCentreProps } from '@/types';
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<CommandCentreProps>();

/**
 * Presentational only for Phase 3 — range filtering ships with the Runs
 * Explorer, so changing this does not re-query the server yet.
 */
const RANGES = ['Last 24 hours', 'Last 7 days', 'Last 14 days', 'Last 30 days'];
const range = ref(RANGES[2]);
const search = ref('');

const kpiCards = computed(() => [
    { key: 'total', label: 'Total Executions', metric: props.kpis.total_executions, accent: 'cyan' as const },
    { key: 'success', label: 'Success Rate', metric: props.kpis.success_rate, accent: 'emerald' as const },
    { key: 'latency', label: 'Avg Latency', metric: props.kpis.avg_latency, accent: 'violet' as const },
    { key: 'cost', label: '24h AI Cost', metric: props.kpis.cost_24h, accent: 'amber' as const },
]);

const pulseStats = computed(() => [
    { label: 'Executions', value: props.kpis.total_executions.display },
    { label: 'Avg latency', value: props.kpis.avg_latency.display },
    { label: 'Policies', value: props.governanceLedger.policies_evaluated.display },
]);
</script>

<template>
    <Head title="Command Centre" />

    <AppLayout>
        <div class="mx-auto w-full max-w-[112rem] space-y-5 px-5 py-6 sm:px-7 lg:px-8">
            <!-- Top bar -->
            <header class="glass-panel flex flex-wrap items-center gap-4 px-5 py-4">
                <div class="min-w-0">
                    <p class="panel-eyebrow">Command Centre</p>
                    <h1 class="mt-1 font-display text-xl font-semibold tracking-tight text-gradient-cyan">
                        {{ workspace.name }}
                    </h1>
                </div>

                <span
                    class="rounded-full border border-white/[0.12] bg-white/5 px-2.5 py-1 font-mono text-[0.65rem] uppercase tracking-[0.12em] text-white/55"
                >
                    {{ workspace.tier }} · {{ workspace.active_workflow_count }}/{{
                        workspace.workflow_count
                    }}
                    workflows live
                </span>

                <div class="relative ml-auto w-full min-w-[12rem] max-w-xs flex-1">
                    <label for="fleet-search" class="sr-only">Search runs, workflows and agents</label>
                    <svg
                        class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-white/35"
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
                        class="w-full rounded-lg border-white/10 bg-white/[0.04] py-2 pl-9 pr-3 text-sm text-white placeholder:text-white/30 focus:border-accent-cyan/50 focus:ring-2 focus:ring-accent-cyan/30"
                    />
                </div>

                <span
                    class="flex shrink-0 items-center gap-2 rounded-full border border-accent-cyan/35 bg-accent-cyan/10 px-3 py-1.5 text-xs font-semibold text-accent-cyan"
                >
                    <span
                        class="h-1.5 w-1.5 rounded-full bg-accent-cyan pulse-breathe shadow-[0_0_10px_2px_rgba(45,226,230,0.7)]"
                        aria-hidden="true"
                    />
                    Fleet Live
                </span>

                <div class="relative shrink-0">
                    <label for="range-select" class="sr-only">Date range</label>
                    <select
                        id="range-select"
                        v-model="range"
                        class="appearance-none rounded-lg border-white/10 bg-white/[0.04] py-2 pl-3 pr-9 text-xs text-white/75 focus:border-accent-cyan/50 focus:ring-2 focus:ring-accent-cyan/30"
                    >
                        <option v-for="option in RANGES" :key="option" class="bg-navy-raised">
                            {{ option }}
                        </option>
                    </select>
                    <svg
                        class="pointer-events-none absolute right-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-white/40"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        aria-hidden="true"
                    >
                        <path d="m6 9 6 6 6-6" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </div>
            </header>

            <!-- Fleet Trust + KPI row -->
            <div class="grid grid-cols-12 gap-5">
                <div class="col-span-12 lg:col-span-4 xl:col-span-3">
                    <FleetTrustGauge
                        :score="fleetTrust.score"
                        :band="fleetTrust.band"
                        :components="fleetTrust.components"
                    />
                </div>

                <div class="col-span-12 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:col-span-8 xl:col-span-9">
                    <KpiStatCard
                        v-for="card in kpiCards"
                        :key="card.key"
                        :label="card.label"
                        :value="card.metric.display"
                        :caption="card.metric.caption"
                        :accent="card.accent"
                    />
                </div>
            </div>

            <!-- Decision Graph + attention sidebar -->
            <div class="grid grid-cols-12 gap-5">
                <div class="col-span-12 xl:col-span-8">
                    <DecisionGraph :graph="decisionGraph" class="min-h-[30rem]" />
                </div>

                <div class="col-span-12 space-y-5 xl:col-span-4">
                    <section class="glass-panel p-5" aria-labelledby="human-attention-heading">
                        <div
                            class="pointer-events-none absolute -right-12 -top-12 h-32 w-32 rounded-full bg-status-critical/15 blur-3xl"
                            aria-hidden="true"
                        />

                        <div class="relative flex items-center justify-between gap-2">
                            <div>
                                <h2 id="human-attention-heading" class="panel-heading">Human Attention</h2>
                                <p class="mt-0.5 text-xs text-white/40">Approvals blocking autonomous execution</p>
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
                                class="py-6 text-center text-sm text-white/40"
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
    </AppLayout>
</template>
