<script setup lang="ts">
import ReviewDecisionBar from '@/Components/ReviewDecisionBar.vue';
import ReviewDetailPanel from '@/Components/ReviewDetailPanel.vue';
import type { ReviewQueueItem, StatusTone } from '@/types';
import { computed } from 'vue';

const props = defineProps<{ item: ReviewQueueItem; expanded: boolean }>();

const emit = defineEmits<{ toggle: [] }>();

const CHIP: Record<StatusTone, string> = {
    critical: 'border-status-critical/45 bg-status-critical/[0.14] text-[#FFC3C9]',
    review: 'border-status-review/45 bg-status-review/[0.14] text-[#FFE1A6]',
    info: 'border-status-info/45 bg-status-info/[0.14] text-glow-blue',
    completed: 'border-status-completed/45 bg-status-completed/[0.14] text-glow-green',
};

const DIAL: Record<StatusTone, string> = {
    critical: '#F26C78',
    review: '#F8C65D',
    info: '#5AA8FF',
    completed: '#55D98B',
};

const RADIUS = 26;
const CIRCUMFERENCE = 2 * Math.PI * RADIUS;
const SWEEP = 0.75;

const dash = computed(
    () =>
        `${CIRCUMFERENCE * SWEEP * Math.max(0, Math.min(1, props.item.risk.score))} ${CIRCUMFERENCE}`,
);

const band = computed(() =>
    [props.item.risk.level_label, props.item.risk.confidence_label]
        .filter(Boolean)
        .join(' · '),
);
</script>

<template>
    <article
        class="glass-panel transition duration-200 hover:border-[rgba(160,205,245,0.28)]"
    >
        <div class="flex flex-wrap items-start gap-x-5 gap-y-4 px-[18px] py-4">
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                    <span
                        class="rounded border px-[7px] py-[3px] font-mono text-[9.5px] font-semibold tracking-[0.1em]"
                        :class="CHIP[item.risk.tone]"
                    >
                        {{ item.category }}
                    </span>
                    <span class="font-mono text-[11.5px] font-medium text-glow-blue">
                        #{{ item.run_key }}
                    </span>
                    <p class="min-w-0 truncate text-[12px] text-ink-400">
                        {{ item.workflow }} ·
                        <span class="font-mono text-glow-violet">{{ item.agent }}</span>
                    </p>
                    <span class="font-mono text-[10.5px] text-ink-700">
                        waiting {{ item.waiting_label }} · {{ item.sla_label }}
                    </span>
                </div>

                <button
                    type="button"
                    class="mt-2 block w-full text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-cyan"
                    :aria-expanded="expanded"
                    @click="emit('toggle')"
                >
                    <h3 class="font-display text-[16px] font-semibold leading-snug tracking-[-0.01em] text-ink-100">
                        {{ item.headline }}
                    </h3>
                </button>

                <p class="mt-1.5 text-[11.5px] leading-[1.6] text-ink-600">
                    {{ item.objective }}
                    <span v-if="item.impact_label" class="font-mono text-ink-400">
                        · {{ item.impact_label }}
                    </span>
                </p>
            </div>

            <div class="flex flex-none flex-col items-center gap-1">
                <svg viewBox="0 0 72 72" class="h-[72px] w-[72px]" fill="none" role="img" :aria-label="`Risk score ${item.risk.score_label}`">
                    <g transform="rotate(135 36 36)">
                        <circle
                            cx="36"
                            cy="36"
                            :r="RADIUS"
                            stroke="rgba(150,195,235,0.14)"
                            stroke-width="5"
                            stroke-linecap="round"
                            :stroke-dasharray="`${CIRCUMFERENCE * SWEEP} ${CIRCUMFERENCE}`"
                        />
                        <circle
                            cx="36"
                            cy="36"
                            :r="RADIUS"
                            :stroke="DIAL[item.risk.tone]"
                            stroke-width="5"
                            stroke-linecap="round"
                            :stroke-dasharray="dash"
                        />
                    </g>
                    <text
                        x="36"
                        y="41"
                        text-anchor="middle"
                        class="fill-ink-100 font-display text-[18px] font-semibold"
                    >
                        {{ item.risk.score_label }}
                    </text>
                </svg>
                <span class="font-mono text-[9.5px] tracking-[0.08em] text-ink-800">
                    {{ band }}
                </span>
            </div>

            <div class="flex-none basis-full lg:basis-auto">
                <ReviewDecisionBar :item="item" :show-note="expanded" />
            </div>
        </div>

        <button
            type="button"
            class="flex w-full items-center gap-2 border-t border-[rgba(160,205,245,0.08)] px-[18px] py-2.5 font-mono text-[10px] font-semibold tracking-[0.06em] text-ink-700 transition duration-200 hover:bg-accent-cyan/[0.06] hover:text-glow-cyan focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-cyan"
            :aria-expanded="expanded"
            @click="emit('toggle')"
        >
            <svg
                class="h-3 w-3 transition-transform duration-200"
                :class="expanded ? 'rotate-90' : ''"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                aria-hidden="true"
            >
                <path d="m10 6 6 6-6 6" />
            </svg>
            {{ expanded ? 'HIDE EVIDENCE' : 'SHOW EVIDENCE' }}
        </button>

        <ReviewDetailPanel v-if="expanded" :run="item.detail" />
    </article>
</template>
