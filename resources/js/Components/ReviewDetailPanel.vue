<script setup lang="ts">
import ExecutionTraceMap from '@/Components/ExecutionTraceMap.vue';
import PolicyRiskPanel from '@/Components/PolicyRiskPanel.vue';
import ReasoningTrace from '@/Components/ReasoningTrace.vue';
import ToolCallCard from '@/Components/ToolCallCard.vue';
import type { InspectorRun, TraceNode } from '@/types';
import { Link } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';

const props = defineProps<{ run: InspectorRun }>();

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
</script>

<template>
    <div class="flex flex-col gap-[18px] border-t border-[rgba(160,205,245,0.10)] px-5 py-5">
        <ExecutionTraceMap :trace="run.trace" @select="focusStep" />

        <div class="grid grid-cols-1 gap-[18px] xl:grid-cols-[minmax(0,1fr)_330px]">
            <div class="grid min-w-0 grid-cols-1 gap-[18px] lg:grid-cols-[minmax(0,1fr)_340px]">
                <ReasoningTrace
                    :timeline="run.reasoning"
                    :active-step-id="selectedStepId"
                    class="max-h-[420px] min-h-[280px]"
                />

                <div class="flex min-w-0 flex-col gap-3">
                    <header class="flex items-center gap-2.5 px-0.5">
                        <h3 class="panel-heading">
                            Tool Calls
                        </h3>
                        <p class="font-mono text-[10.5px] text-ink-700">
                            {{ run.toolCalls.summary }}
                        </p>
                    </header>

                    <ToolCallCard v-for="call in run.toolCalls.items" :key="call.id" :call="call" />

                    <p
                        v-if="!run.toolCalls.items.length"
                        class="rounded-xl border border-dashed border-[rgba(160,205,245,0.16)] bg-[rgba(10,20,35,0.6)] px-3.5 py-[11px] font-mono text-[10.5px] text-ink-800"
                    >
                        no tool calls recorded for this run
                    </p>
                </div>
            </div>

            <aside class="flex min-w-0 flex-col gap-3.5">
                <PolicyRiskPanel :policy="run.policy" />

                <Link
                    :href="`/runs/${run.id}`"
                    class="flex items-center justify-center gap-2 rounded-[10px] border border-accent-cyan/40 px-4 py-3 font-mono text-[10.5px] font-semibold tracking-[0.06em] text-glow-cyan transition duration-200 hover:bg-accent-cyan/[0.12] hover:shadow-[0_0_22px_rgba(45,226,230,0.22)] focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-cyan"
                >
                    OPEN FULL INSPECTOR
                    <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="m10 6 6 6-6 6" />
                    </svg>
                </Link>
            </aside>
        </div>
    </div>
</template>
