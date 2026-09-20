<script setup lang="ts">
import type { DecisionRecord } from '@/types';

defineProps<{ decision: DecisionRecord }>();

/**
 * The Human-in-the-Loop desk that owns these verdicts is a later phase, so the
 * bar shows the actions a pending record offers without pretending to write.
 */
const PENDING_NOTE = 'Review Queue — not built yet';

const ACTION =
    'cursor-not-allowed rounded-[10px] px-4 py-3 font-mono text-[11px] font-semibold tracking-[0.06em] transition duration-200';
</script>

<template>
    <section
        class="relative overflow-hidden rounded-[18px] border border-[rgba(160,222,245,0.20)] bg-[linear-gradient(150deg,rgba(45,226,230,0.10),rgba(20,34,55,0.62)_48%,rgba(242,108,120,0.12))] px-[22px] py-[18px] shadow-[0_24px_56px_rgba(2,8,18,0.62),inset_0_1px_0_rgba(215,245,255,0.14)] backdrop-blur-[24px]"
        aria-labelledby="decision-record-heading"
    >
        <div
            class="pointer-events-none absolute -top-[120px] left-10 h-[280px] w-[420px] rounded-full bg-[radial-gradient(circle,rgba(45,226,230,0.16),rgba(45,226,230,0)_70%)]"
            aria-hidden="true"
        />

        <div class="relative flex flex-wrap items-center gap-x-[22px] gap-y-4">
            <div class="flex min-w-0 flex-col gap-1.5">
                <h2
                    id="decision-record-heading"
                    class="font-mono text-[10.5px] font-semibold tracking-[0.14em] text-glow-teal"
                >
                    {{ decision.eyebrow }}
                </h2>
                <p class="font-display text-[21px] font-semibold leading-tight tracking-[-0.01em] text-[#F2FBFF]">
                    {{ decision.headline }}
                </p>
                <p class="text-xs text-ink-500">{{ decision.detail }}</p>
                <p v-if="decision.resolution?.notes" class="text-xs text-ink-600">
                    {{ decision.resolution.notes }}
                </p>
            </div>

            <div class="flex-1" />

            <div v-if="decision.pending" class="flex flex-none items-center gap-2.5">
                <button
                    type="button"
                    disabled
                    :title="PENDING_NOTE"
                    :class="`${ACTION} border border-[rgba(160,205,245,0.16)] text-ink-500`"
                >
                    REQUEST CHANGES
                </button>
                <button
                    type="button"
                    disabled
                    :title="PENDING_NOTE"
                    :class="`${ACTION} border border-status-critical/50 bg-status-critical/[0.12] px-[18px] text-[#FFC3C9]`"
                >
                    REJECT
                </button>
                <button
                    type="button"
                    disabled
                    :title="PENDING_NOTE"
                    :class="`${ACTION} bg-[linear-gradient(140deg,#7DEDF0,#2DE2E6)] px-[22px] text-[#061020] shadow-[0_0_30px_rgba(45,226,230,0.4)]`"
                >
                    APPROVE &amp; SIGN
                </button>
            </div>

            <p
                v-else-if="decision.resolution"
                class="flex flex-none items-center gap-2.5 rounded-[10px] border border-status-completed/40 bg-status-completed/10 px-4 py-3 font-mono text-[11px] font-semibold tracking-[0.06em] text-glow-green"
            >
                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="m4 12 5 5L20 6" />
                </svg>
                {{ decision.resolution.status.toUpperCase() }}
                <span class="font-normal text-ink-500">{{ decision.resolution.at }}</span>
            </p>
        </div>
    </section>
</template>
