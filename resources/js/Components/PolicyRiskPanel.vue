<script setup lang="ts">
import RiskGauge from '@/Components/RiskGauge.vue';
import type { PolicyEvaluation } from '@/types';
import { computed } from 'vue';

const props = defineProps<{ policy: PolicyEvaluation }>();

const flagged = computed(() => props.policy.risk !== null);

const counts = computed(() => {
    const { passed, breached, skipped } = props.policy.counts;

    return [
        `${passed} passed`,
        `${breached} breached`,
        `${skipped} skipped`,
    ];
});
</script>

<template>
    <section
        class="relative overflow-hidden rounded-[18px] border px-[18px] pb-[18px] pt-4 backdrop-blur-[24px]"
        :class="
            flagged
                ? 'border-status-critical/30 bg-[linear-gradient(150deg,rgba(242,108,120,0.14),rgba(24,32,50,0.55)_62%,rgba(139,124,255,0.12))] shadow-[0_22px_52px_rgba(2,8,18,0.6),0_0_34px_rgba(242,108,120,0.12),inset_0_1px_0_rgba(255,215,220,0.12)]'
                : 'border-status-completed/25 bg-[linear-gradient(150deg,rgba(85,217,139,0.10),rgba(24,32,50,0.55)_62%,rgba(45,226,230,0.10))] shadow-[0_22px_52px_rgba(2,8,18,0.6),inset_0_1px_0_rgba(215,245,255,0.12)]'
        "
        aria-labelledby="policy-risk-heading"
    >
        <div
            class="pointer-events-none absolute -right-[60px] -top-[90px] h-60 w-60 rounded-full"
            :class="
                flagged
                    ? 'bg-[radial-gradient(circle,rgba(242,108,120,0.26),rgba(242,108,120,0)_70%)]'
                    : 'bg-[radial-gradient(circle,rgba(85,217,139,0.18),rgba(85,217,139,0)_70%)]'
            "
            aria-hidden="true"
        />

        <header class="relative flex items-center justify-between gap-2">
            <h2
                id="policy-risk-heading"
                class="font-mono text-[10.5px] font-semibold tracking-[0.14em]"
                :class="flagged ? 'text-[#FFC3C9]' : 'text-glow-green'"
            >
                POLICY &amp; RISK
            </h2>
            <span
                v-if="policy.tier"
                class="rounded border px-1.5 py-0.5 font-mono text-[10px] font-medium uppercase"
                :class="
                    flagged
                        ? 'border-status-critical/[0.34] text-[#C99AA1]'
                        : 'border-status-completed/30 text-ink-500'
                "
            >
                {{ policy.tier }}
            </span>
        </header>

        <div v-if="policy.risk" class="relative mt-1 grid place-items-center">
            <RiskGauge
                :score="policy.risk.score"
                :score-label="policy.risk.score_label"
                :band-label="policy.risk.level_label"
            />
        </div>

        <p
            v-else
            class="relative mt-4 rounded-xl border border-status-completed/20 bg-[rgba(6,20,16,0.5)] px-3.5 py-4 text-center font-display text-[13px] font-semibold text-glow-green"
        >
            No risk flag
            <span class="mt-1 block font-sans text-[11.5px] font-normal leading-relaxed text-ink-500">
                {{ policy.clear_label }}
            </span>
        </p>

        <div v-if="policy.confidence" class="relative" :class="policy.risk ? '-mt-1.5' : 'mt-4'">
            <div class="flex items-baseline justify-between gap-2">
                <span class="font-mono text-[10.5px] text-[#C3A6AB]">DECISION CONFIDENCE</span>
                <span class="font-mono text-xs font-semibold text-[#FFE1A6]">
                    {{ policy.confidence.label }}
                </span>
            </div>
            <div
                class="relative mt-[7px] h-[7px] overflow-hidden rounded border-0 bg-[rgba(150,195,235,0.12)]"
                role="meter"
                :aria-valuenow="policy.confidence.value"
                aria-valuemin="0"
                aria-valuemax="1"
                :aria-label="`Decision confidence ${policy.confidence.label}`"
            >
                <div
                    class="h-full rounded"
                    :class="
                        policy.confidence.verdict === 'clear'
                            ? 'bg-[linear-gradient(90deg,#55D98B,#8FEFBB)] shadow-[0_0_14px_rgba(85,217,139,0.55)]'
                            : 'bg-[linear-gradient(90deg,#F8C65D,#FFE1A6)] shadow-[0_0_14px_rgba(248,198,93,0.55)]'
                    "
                    :style="{ width: `${policy.confidence.percent}%` }"
                />
            </div>
            <div class="mt-1.5 flex justify-between font-mono text-[9.5px] text-[#8A7B80]">
                <span>{{ policy.confidence.threshold_label }}</span>
                <span>{{ policy.confidence.verdict }}</span>
            </div>
        </div>

        <div
            v-if="policy.breach"
            class="relative mt-3.5 rounded-xl border border-status-critical/[0.26] bg-[rgba(6,14,26,0.62)] px-[13px] py-3"
        >
            <div class="flex items-center gap-2">
                <span
                    class="h-[7px] w-[7px] shrink-0 rounded-full bg-status-critical shadow-[0_0_10px_#F26C78]"
                    aria-hidden="true"
                />
                <span class="min-w-0 truncate font-mono text-[11.5px] font-medium text-[#FFC3C9]">
                    {{ policy.breach.rule }}
                </span>
                <div class="flex-1" />
                <span
                    class="shrink-0 rounded bg-status-critical/[0.24] px-1.5 py-0.5 font-mono text-[9px] font-semibold tracking-[0.08em] text-[#FFD9DD]"
                >
                    BREACH
                </span>
            </div>
            <p class="mt-2 text-[11.5px] leading-[1.55] text-[#C3A6AB]">
                {{ policy.breach.summary }}
            </p>
        </div>

        <ul class="relative mt-[11px] flex gap-4 font-mono text-[10px] text-[#9C8388]">
            <li v-for="entry in counts" :key="entry">{{ entry }}</li>
        </ul>
    </section>
</template>
