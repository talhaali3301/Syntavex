<script setup lang="ts">
import ReviewDecisionBar from '@/Components/ReviewDecisionBar.vue';
import ReviewDetailPanel from '@/Components/ReviewDetailPanel.vue';
import RiskGauge from '@/Components/RiskGauge.vue';
import type { ReviewQueueItem } from '@/types';

defineProps<{ item: ReviewQueueItem; expanded: boolean }>();

const emit = defineEmits<{ toggle: [] }>();
</script>

<template>
    <article
        class="freeze-halo relative rounded-[20px] border border-status-critical/[0.45] bg-[linear-gradient(155deg,rgba(242,108,120,0.14),rgba(14,24,42,0.82)_46%,rgba(139,124,255,0.10))] shadow-[0_28px_70px_rgba(2,8,18,0.7)] backdrop-blur-[24px]"
        :aria-labelledby="`freeze-${item.id}-headline`"
    >
        <div class="relative overflow-hidden rounded-[20px]">
            <div
                class="pointer-events-none absolute -right-[80px] -top-[120px] h-[360px] w-[360px] rounded-full bg-[radial-gradient(circle,rgba(242,108,120,0.22),rgba(242,108,120,0)_70%)]"
                aria-hidden="true"
            />

            <div class="relative px-[22px] pb-5 pt-[18px]">
                <div class="flex flex-wrap items-start gap-x-4 gap-y-3">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                            <span
                                class="flex items-center gap-[7px] rounded-full border border-status-critical/55 bg-status-critical/[0.16] px-[11px] py-[5px] font-mono text-[10px] font-semibold tracking-[0.1em] text-[#FFC3C9]"
                            >
                                <span
                                    class="pulse-breathe h-[7px] w-[7px] rounded-full bg-status-critical shadow-[0_0_10px_#F26C78]"
                                    aria-hidden="true"
                                />
                                {{ item.risk.level_label }} · INTERCEPTED
                            </span>
                            <span class="font-mono text-[11.5px] font-medium text-glow-blue">
                                #{{ item.run_key }}
                            </span>
                            <p class="min-w-0 truncate text-[12px] text-ink-400">
                                {{ item.workflow }} ·
                                <span class="font-mono text-glow-violet">{{ item.agent }}</span>
                            </p>
                            <span class="font-mono text-[11px] text-ink-700">
                                waiting {{ item.waiting_label }}
                            </span>
                        </div>

                        <h3
                            :id="`freeze-${item.id}-headline`"
                            class="mt-3 font-display text-[27px] font-semibold leading-[1.15] tracking-[-0.02em] text-[#F6FBFF]"
                        >
                            {{ item.headline }}
                        </h3>

                        <p class="mt-2 max-w-[46rem] text-[12.5px] leading-[1.65] text-ink-400">
                            {{ item.summary }}
                        </p>

                        <p
                            class="mt-3.5 flex max-w-[34rem] items-center gap-2.5 rounded-[11px] border border-status-critical/[0.3] bg-[rgba(6,14,26,0.66)] px-3.5 py-2.5"
                        >
                            <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="#F26C78" stroke-width="1.9" aria-hidden="true">
                                <path d="M12 4.5 2.8 19.5h18.4z" />
                                <path d="M12 10v4M12 17h.01" />
                            </svg>
                            <span class="text-[12.5px] italic text-[#FFD0D5]">
                                This action was about to happen. You stopped it.
                            </span>
                        </p>
                    </div>

                    <div class="flex w-[186px] flex-none flex-col items-center gap-2.5">
                        <RiskGauge
                            :score="item.risk.score"
                            :score-label="item.risk.score_label"
                            band-label="RISK SCORE"
                        />
                        <span
                            class="rounded-full border border-status-critical/[0.36] px-[11px] py-[5px] font-mono text-[9.5px] font-semibold tracking-[0.1em] text-[#C99AA1]"
                        >
                            BLAST RADIUS ·
                            {{ item.intercept.irreversible ? 'IRREVERSIBLE' : 'CONTAINED' }}
                        </span>
                    </div>
                </div>

                <div class="freeze-still mt-4 grid grid-cols-1 gap-3.5 lg:grid-cols-[minmax(0,1fr)_380px]">
                    <section
                        class="rounded-[13px] border border-[rgba(160,205,245,0.12)] bg-[rgba(5,12,22,0.78)] px-4 py-3.5"
                    >
                        <header class="flex flex-wrap items-baseline justify-between gap-2">
                            <h4 class="font-mono text-[10.5px] font-semibold tracking-[0.12em] text-glow-teal">
                                {{ item.readout.title }}
                            </h4>
                            <span class="font-mono text-[10px] text-ink-800">
                                {{ item.readout.meta }}
                            </span>
                        </header>

                        <ul class="mt-2.5 flex flex-col gap-2 font-mono text-[11px] leading-[1.6]">
                            <li
                                v-for="(line, index) in item.readout.lines"
                                :key="index"
                                class="flex gap-2"
                                :class="line.tone === 'critical' ? 'text-glow-red' : 'text-ink-400'"
                            >
                                <span class="text-ink-900" aria-hidden="true">›</span>
                                <span class="min-w-0 break-words">{{ line.text }}</span>
                            </li>
                        </ul>
                    </section>

                    <div class="flex min-w-0 flex-col gap-3.5">
                        <section class="rounded-[13px] border border-status-critical/[0.28] bg-[rgba(6,14,26,0.72)] px-[13px] py-3">
                            <div class="flex items-center gap-2">
                                <span
                                    class="h-[7px] w-[7px] shrink-0 rounded-full bg-status-critical shadow-[0_0_10px_#F26C78]"
                                    aria-hidden="true"
                                />
                                <span class="min-w-0 truncate font-mono text-[11.5px] font-medium text-[#FFC3C9]">
                                    {{ item.intercept.rule }}
                                </span>
                                <div class="flex-1" />
                                <span
                                    class="shrink-0 rounded bg-status-critical/[0.26] px-1.5 py-0.5 font-mono text-[9px] font-semibold tracking-[0.08em] text-[#FFD9DD]"
                                >
                                    {{ item.intercept.status_label }}
                                </span>
                            </div>
                            <p v-if="item.intercept.measure" class="mt-2 font-mono text-[10.5px] text-[#C3A6AB]">
                                {{ item.intercept.measure }}
                            </p>
                        </section>

                        <section
                            v-if="item.impact_rows.length"
                            class="rounded-[13px] border border-[rgba(160,205,245,0.12)] bg-[rgba(5,12,22,0.78)] px-4 py-3.5"
                        >
                            <h4 class="font-mono text-[10px] font-semibold tracking-[0.14em] text-ink-600">
                                DATA AFFECTED
                            </h4>
                            <dl class="mt-2.5 flex flex-col gap-[7px]">
                                <div
                                    v-for="row in item.impact_rows"
                                    :key="row.label"
                                    class="flex items-baseline gap-3"
                                >
                                    <dt class="min-w-0 flex-1 truncate font-mono text-[10.5px] text-ink-700">
                                        {{ row.label }}
                                    </dt>
                                    <dd class="flex-none font-mono text-[10.5px] text-ink-300">
                                        {{ row.value }}
                                    </dd>
                                </div>
                            </dl>
                        </section>
                    </div>
                </div>

                <div class="mt-4 border-t border-[rgba(160,205,245,0.10)] pt-4">
                    <ReviewDecisionBar :item="item" variant="frozen" />
                </div>

                <button
                    type="button"
                    class="mt-3 flex items-center gap-2 font-mono text-[10.5px] font-semibold tracking-[0.06em] text-ink-600 transition duration-200 hover:text-glow-cyan focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-cyan"
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
                    {{ expanded ? 'HIDE FULL TRACE' : 'SHOW FULL TRACE' }}
                </button>
            </div>

            <ReviewDetailPanel v-if="expanded" :run="item.detail" />
        </div>
    </article>
</template>
