<script setup lang="ts">
import DecisionRecordBar from '@/Components/DecisionRecordBar.vue';
import ExecutionTraceMap from '@/Components/ExecutionTraceMap.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PolicyRiskPanel from '@/Components/PolicyRiskPanel.vue';
import ReasoningTrace from '@/Components/ReasoningTrace.vue';
import ToolCallCard from '@/Components/ToolCallCard.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { RunInspectorProps, StatusTone, TraceNode } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';

const props = defineProps<RunInspectorProps>();

const STATUS_PILL: Record<StatusTone, string> = {
    review: 'border-status-review/45 bg-status-review/[0.14] text-[#FFE1A6] shadow-[0_0_22px_rgba(248,198,93,0.25)]',
    completed: 'border-status-completed/45 bg-status-completed/[0.14] text-glow-green',
    critical: 'border-status-critical/45 bg-status-critical/[0.14] text-[#FFC3C9] shadow-[0_0_22px_rgba(242,108,120,0.22)]',
    info: 'border-status-info/45 bg-status-info/[0.14] text-glow-blue',
};

const STATUS_DOT: Record<StatusTone, string> = {
    review: 'bg-status-review shadow-[0_0_10px_#F8C65D]',
    completed: 'bg-status-completed shadow-[0_0_10px_#55D98B]',
    critical: 'bg-status-critical shadow-[0_0_10px_#F26C78]',
    info: 'bg-status-info shadow-[0_0_10px_#5AA8FF]',
};

const RELATED_DOT: Record<StatusTone, string> = {
    review: 'bg-status-review',
    completed: 'bg-status-completed',
    critical: 'bg-status-critical',
    info: 'bg-status-info',
};

const selectedStepId = ref<number | null>(
    props.run.trace.nodes.find((node) => node.selected)?.id ?? null,
);

const focusStep = async (node: TraceNode): Promise<void> => {
    selectedStepId.value = node.id;
    await nextTick();
    document
        .getElementById(`reasoning-step-${node.id}`)
        ?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
};

const breadcrumb = computed(() =>
    [props.run.workspace.slug, props.run.workspace.tier]
        .filter(Boolean)
        .join(' · '),
);
</script>

<template>
    <Head :title="`Run #${run.run_key}`" />

    <AppLayout>
        <div class="flex min-h-screen flex-col">
            <PageHeader tone="review">
                <template #above>
                    <nav class="relative flex flex-wrap items-center gap-3.5" aria-label="Breadcrumb">
                        <Link
                            href="/runs"
                            class="flex items-center gap-[7px] rounded-lg border border-[rgba(160,205,245,0.16)] px-[11px] py-1.5 font-mono text-[11px] font-medium text-ink-500 transition duration-200 hover:border-accent-cyan/45 hover:text-glow-cyan hover:shadow-[0_0_18px_rgba(45,226,230,0.16)] focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-cyan focus-visible:ring-offset-2 focus-visible:ring-offset-navy-base"
                        >
                            <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path d="m14 6-6 6 6 6" />
                            </svg>
                            Runs Explorer
                        </Link>
                        <p class="page-meta">
                            workspace / {{ breadcrumb }} / runs /
                            <span class="text-glow-blue">{{ run.run_key }}</span>
                        </p>
                    </nav>
                </template>

                <template #title>
                    Run <span class="font-mono text-glow-blue">#{{ run.run_key }}</span>
                </template>

                <template #badges>
                    <span
                        class="flex items-center gap-[7px] rounded-full border px-[11px] py-[5px] font-mono text-[10px] font-semibold tracking-[0.1em]"
                        :class="STATUS_PILL[run.tone]"
                    >
                        <span class="h-[7px] w-[7px] rounded-full" :class="STATUS_DOT[run.tone]" aria-hidden="true" />
                        {{ run.status_label }}
                    </span>
                    <span
                        v-if="run.policy.risk"
                        class="rounded-full border border-status-critical/[0.42] bg-status-critical/[0.14] px-[11px] py-[5px] font-mono text-[10px] font-semibold tracking-[0.1em] text-[#FFC3C9]"
                    >
                        {{ run.policy.risk.level_label }} {{ run.policy.risk.score_label }}
                    </span>
                </template>

                <template #meta>
                    {{ run.workflow.name }} ·
                    <span class="text-glow-violet">{{ run.agent }}</span> ·
                    {{ run.objective }}
                </template>

                <template #actions>
                    <dl
                        class="flex flex-wrap gap-px overflow-hidden rounded-xl border border-[rgba(160,205,245,0.12)] bg-[rgba(160,205,245,0.10)]"
                    >
                        <div
                            v-for="stat in header"
                            :key="stat.label"
                            class="flex min-w-[106px] flex-col gap-[3px] bg-[rgba(10,20,35,0.85)] px-[18px] py-2.5"
                        >
                            <dt class="panel-eyebrow">{{ stat.label }}</dt>
                            <dd
                                class="font-mono text-[13px] font-medium"
                                :class="stat.tone === 'accent' ? 'text-glow-cyan' : 'text-ink-200'"
                            >
                                {{ stat.value }}
                            </dd>
                            <dd v-if="stat.caption" class="font-mono text-[9.5px] text-ink-900">
                                {{ stat.caption }}
                            </dd>
                        </div>
                    </dl>
                </template>

                <template v-if="run.error_message" #below>
                    <p class="relative font-mono text-[11.5px] text-glow-red">
                        {{ run.error_message }}
                    </p>
                </template>
            </PageHeader>

            <div class="grid flex-1 grid-cols-1 gap-[18px] px-8 pb-7 pt-5 xl:grid-cols-[minmax(0,1fr)_330px]">
                <div class="flex min-w-0 flex-col gap-[18px]">
                    <ExecutionTraceMap :trace="run.trace" @select="focusStep" />

                    <div class="grid min-h-0 flex-1 grid-cols-1 gap-[18px] lg:grid-cols-[minmax(0,1fr)_388px]">
                        <ReasoningTrace
                            :timeline="run.reasoning"
                            :active-step-id="selectedStepId"
                            class="min-h-[420px] lg:max-h-[620px]"
                        />

                        <div class="flex min-w-0 flex-col gap-3">
                            <header class="flex items-center gap-2.5 px-0.5">
                                <h2 class="panel-heading">
                                    Tool Calls
                                </h2>
                                <p class="font-mono text-[10.5px] text-ink-700">
                                    {{ run.toolCalls.summary }}
                                </p>
                            </header>

                            <ToolCallCard
                                v-for="call in run.toolCalls.items"
                                :key="call.id"
                                :call="call"
                            />

                            <p
                                class="flex items-center gap-[9px] rounded-xl border border-dashed border-[rgba(160,205,245,0.16)] bg-[rgba(10,20,35,0.6)] px-3.5 py-[11px] font-mono text-[10.5px] text-ink-800"
                            >
                                <svg class="h-3 w-3 shrink-0" viewBox="0 0 24 24" fill="none" stroke="#4A5D73" stroke-width="2" aria-hidden="true">
                                    <circle cx="12" cy="12" r="8" />
                                    <path d="M12 8v5" />
                                </svg>
                                {{ run.toolCalls.note ?? 'no tool calls recorded for this run' }}
                            </p>
                        </div>
                    </div>

                    <DecisionRecordBar :decision="run.decision" />
                </div>

                <aside class="flex min-w-0 flex-col gap-3.5">
                    <PolicyRiskPanel :policy="run.policy" />

                    <section
                        class="glass-panel px-4 py-3.5"
                        aria-labelledby="related-runs-heading"
                    >
                        <h2
                            id="related-runs-heading"
                            class="panel-eyebrow"
                        >
                            RELATED RUNS
                        </h2>
                        <ul v-if="related.length" class="mt-2.5 flex flex-col gap-0.5">
                            <li v-for="item in related" :key="item.id">
                                <Link
                                    :href="`/runs/${item.id}`"
                                    class="flex items-center gap-[9px] rounded-[9px] px-[9px] py-2 transition duration-150 hover:bg-accent-cyan/[0.08] focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-cyan"
                                >
                                    <span
                                        class="h-1.5 w-1.5 flex-none rounded-full"
                                        :class="RELATED_DOT[item.tone]"
                                        aria-hidden="true"
                                    />
                                    <span class="flex-none font-mono text-[11px] font-medium text-glow-blue">
                                        #{{ item.run_key }}
                                    </span>
                                    <span class="min-w-0 flex-1 truncate text-[11px] text-ink-500">
                                        {{ item.label }}
                                    </span>
                                    <span class="flex-none font-mono text-[10px] text-ink-900">
                                        {{ item.relation }}
                                    </span>
                                </Link>
                            </li>
                        </ul>
                        <p v-else class="mt-2.5 font-mono text-[10.5px] text-ink-800">
                            No other runs in this workspace yet.
                        </p>
                    </section>

                    <section
                        class="glass-panel px-4 py-3.5"
                        aria-labelledby="metadata-heading"
                    >
                        <h2
                            id="metadata-heading"
                            class="panel-eyebrow"
                        >
                            METADATA
                        </h2>
                        <dl class="mt-[11px] flex flex-col gap-[9px]">
                            <div
                                v-for="row in run.metadata"
                                :key="row.label"
                                class="flex items-center gap-2.5"
                            >
                                <dt class="flex-1 text-[11px] text-ink-600">{{ row.label }}</dt>
                                <dd class="min-w-0 truncate font-mono text-[11px] font-medium">
                                    <Link
                                        v-if="row.href"
                                        :href="row.href"
                                        class="text-glow-cyan transition duration-150 hover:text-accent-violet focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-cyan"
                                    >
                                        {{ row.value }}
                                    </Link>
                                    <span v-else class="text-ink-200">{{ row.value }}</span>
                                </dd>
                            </div>
                        </dl>
                    </section>

                    <p
                        class="flex cursor-not-allowed items-center gap-[9px] rounded-[14px] border border-accent-violet/[0.32] bg-[linear-gradient(150deg,rgba(139,124,255,0.14),rgba(16,26,44,0.6))] px-3.5 py-3 shadow-[0_0_26px_rgba(139,124,255,0.12)]"
                        title="Audit ledger — not built yet"
                    >
                        <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="#B5ABFC" stroke-width="1.8" aria-hidden="true">
                            <path d="M6 3h8l4 4v14H6z" />
                            <path d="M14 3v5h4M9 13h6M9 17h4" />
                        </svg>
                        <span class="text-[11.5px] text-glow-violet">Audit record</span>
                        <span class="flex-1" />
                        <span class="font-mono text-[10px] text-[#8F86C4]">{{ run.audit.label }}</span>
                    </p>
                </aside>
            </div>
        </div>
    </AppLayout>
</template>
