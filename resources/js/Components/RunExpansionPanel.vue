<script setup lang="ts">
import type { RunExpansion, StatusTone, StepTone } from '@/types';
import { Link } from '@inertiajs/vue3';

defineProps<{
    runId: number;
    expansion: RunExpansion;
}>();

const DOT: Record<StepTone, string> = {
    completed: 'bg-status-completed',
    review: 'bg-status-review shadow-[0_0_10px_#F8C65D]',
    critical: 'bg-status-critical shadow-[0_0_10px_#F26C78]',
    info: 'bg-status-info',
    idle: 'bg-[rgba(150,195,235,0.25)]',
};

const RISK_TEXT: Record<StatusTone, string> = {
    critical: 'text-status-critical',
    review: 'text-status-review',
    completed: 'text-status-completed',
    info: 'text-status-info',
};
</script>

<template>
    <div
        class="mb-4 ml-6 mr-5 rounded-xl border border-[rgba(160,222,245,0.16)] bg-gradient-to-br from-accent-cyan/[0.08] to-accent-violet/[0.08] px-4 py-3.5 backdrop-blur-lg"
    >
        <div class="flex flex-wrap items-stretch gap-4">
            <!-- Trace preview -->
            <div class="flex min-w-[190px] flex-col gap-[7px]">
                <h4 class="font-mono text-[9.5px] font-medium tracking-[0.12em] text-ink-600">
                    TRACE PREVIEW
                </h4>
                <ol class="flex items-center gap-[5px]" aria-label="Step sequence">
                    <template v-for="(step, index) in expansion.trace" :key="step.id">
                        <li
                            class="h-[9px] w-[9px] shrink-0 rounded-full"
                            :class="DOT[step.tone]"
                            :title="`${index + 1}. ${step.name}, ${step.status}`"
                        >
                            <span class="sr-only">{{ step.name }}, {{ step.status }}</span>
                        </li>
                        <li
                            v-if="index < expansion.trace.length - 1"
                            class="h-0.5 min-w-[10px] flex-1 bg-[rgba(150,195,235,0.25)]"
                            aria-hidden="true"
                        />
                    </template>
                </ol>
                <p class="font-mono text-[10.5px] text-ink-700">
                    {{ expansion.steps }} steps · {{ expansion.tool_calls }} tool calls ·
                    {{ expansion.policy_hits }} policy hit{{ expansion.policy_hits === 1 ? '' : 's' }}
                </p>
            </div>

            <div class="w-px self-stretch bg-[rgba(160,205,245,0.12)]" aria-hidden="true" />

            <!-- Risk -->
            <div class="flex flex-col gap-[5px]">
                <h4 class="font-mono text-[9.5px] font-medium tracking-[0.12em] text-ink-600">
                    RISK LEVEL
                </h4>
                <p
                    v-if="expansion.risk"
                    class="font-display text-[13px] font-semibold"
                    :class="RISK_TEXT[expansion.risk.tone]"
                >
                    {{ expansion.risk.level_label }} · {{ expansion.risk.score_label }}
                </p>
                <p v-else class="font-display text-[13px] font-semibold text-ink-600">NONE</p>
                <p class="font-mono text-[10.5px] text-ink-700">
                    {{ expansion.risk?.rule ?? 'no policy gate tripped' }}
                </p>
            </div>

            <div class="w-px self-stretch bg-[rgba(160,205,245,0.12)]" aria-hidden="true" />

            <!-- Decision -->
            <div class="flex min-w-0 flex-col gap-[5px]">
                <h4 class="font-mono text-[9.5px] font-medium tracking-[0.12em] text-ink-600">
                    DECISION
                </h4>
                <p class="font-display text-[13px] font-semibold text-ink-100">
                    {{ expansion.decision?.headline ?? '—' }}
                </p>
                <p class="font-mono text-[10.5px] text-ink-700">
                    {{ expansion.decision?.confidence_label }} · {{ expansion.decision?.signed_label }}
                    <template v-if="expansion.model"> · {{ expansion.model }}</template>
                </p>
            </div>

            <div class="ml-auto flex items-center">
                <Link
                    :href="`/runs/${runId}`"
                    class="rounded-lg border border-accent-cyan/45 px-3.5 py-2.5 font-mono text-[10.5px] font-semibold tracking-[0.06em] text-glow-cyan transition duration-200 hover:bg-accent-cyan/[0.14] hover:shadow-[0_0_20px_rgba(45,226,230,0.22)] focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-cyan focus-visible:ring-offset-2 focus-visible:ring-offset-navy-base"
                >
                    OPEN RUN INSPECTOR →
                </Link>
            </div>
        </div>

        <!-- Seeded approval summary, verbatim -->
        <p
            v-if="expansion.decision?.summary"
            class="mt-3 border-t border-[rgba(160,205,245,0.12)] pt-2.5 text-[11.5px] leading-relaxed text-ink-400"
        >
            {{ expansion.decision.summary }}
        </p>
    </div>
</template>
