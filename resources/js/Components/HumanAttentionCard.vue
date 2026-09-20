<script setup lang="ts">
import type { ApprovalRequestSummary, StatusTone } from '@/types';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    item: ApprovalRequestSummary;
}>();

const BADGE: Record<StatusTone, string> = {
    critical: 'border-status-critical/45 bg-status-critical/[0.12] text-status-critical',
    review: 'border-status-review/45 bg-status-review/[0.12] text-status-review',
    completed: 'border-status-completed/45 bg-status-completed/[0.12] text-status-completed',
    info: 'border-status-info/45 bg-status-info/[0.12] text-status-info',
};

const EDGE: Record<StatusTone, string> = {
    critical: 'bg-status-critical',
    review: 'bg-status-review',
    completed: 'bg-status-completed',
    info: 'bg-status-info',
};

const badgeClass = computed(() => BADGE[props.item.tone] ?? BADGE.info);
const edgeClass = computed(() => EDGE[props.item.tone] ?? EDGE.info);
</script>

<template>
    <article
        class="relative overflow-hidden rounded-xl border border-white/10 bg-white/[0.03] p-3.5 transition duration-200 hover:border-white/20 hover:bg-white/[0.05]"
    >
        <span class="absolute inset-y-0 left-0 w-0.5" :class="edgeClass" aria-hidden="true" />

        <div class="flex items-center justify-between gap-2">
            <span
                class="rounded-full border px-2 py-0.5 text-[0.6rem] font-semibold uppercase tracking-[0.14em]"
                :class="badgeClass"
            >
                {{ item.risk_label }}
            </span>
            <span class="font-mono text-[0.7rem] text-white/45">#{{ item.run_key }}</span>
        </div>

        <p class="mt-2 text-xs leading-relaxed text-white/75">
            {{ item.summary }}
        </p>

        <dl class="mt-2.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-[0.68rem] text-white/40">
            <div class="flex items-center gap-1">
                <dt class="sr-only">Workflow</dt>
                <dd>{{ item.workflow }}</dd>
            </div>
            <span aria-hidden="true">·</span>
            <div class="flex items-center gap-1">
                <dt class="sr-only">Blocked step</dt>
                <dd class="font-mono text-white/55">{{ item.step_name }}</dd>
            </div>
            <span aria-hidden="true">·</span>
            <div class="flex items-center gap-1">
                <dt class="sr-only">Run cost</dt>
                <dd class="font-mono text-white/55">{{ item.cost_label }}</dd>
            </div>
            <span aria-hidden="true">·</span>
            <div class="flex items-center gap-1">
                <dt class="sr-only">Waiting</dt>
                <dd>{{ item.waiting_label }}</dd>
            </div>
        </dl>

        <div class="mt-3 flex items-center gap-2">
            <!-- Opens the run behind the approval; the Review Queue desk that
                 resolves it in place is not built yet, so Escalate is marked
                 unavailable rather than left as a button that does nothing. -->
            <Link
                v-if="item.run_id !== null"
                :href="`/runs/${item.run_id}`"
                class="rounded-lg border border-accent-cyan/40 bg-accent-cyan/10 px-3 py-1.5 text-xs font-semibold text-accent-cyan transition duration-200 hover:border-accent-cyan/70 hover:bg-accent-cyan/20 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-cyan focus-visible:ring-offset-2 focus-visible:ring-offset-navy-base"
                :aria-label="`Review run ${item.run_key}`"
            >
                Review
            </Link>
            <button
                type="button"
                disabled
                title="Escalation routing arrives with the Review Queue"
                class="cursor-not-allowed rounded-lg border border-white/[0.12] px-3 py-1.5 text-xs font-semibold text-white/35"
            >
                Escalate
            </button>
        </div>
    </article>
</template>
