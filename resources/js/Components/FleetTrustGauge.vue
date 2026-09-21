<script setup lang="ts">
import type { FleetTrustComponent } from '@/types';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        score: number;
        band?: string;
        components?: FleetTrustComponent[];
    }>(),
    { band: '', components: () => [] },
);

/** Gauge geometry: a 270° dial, opening at the bottom. */
const RADIUS = 62;
const SWEEP = 0.75;
const CIRCUMFERENCE = 2 * Math.PI * RADIUS;
const ARC_LENGTH = CIRCUMFERENCE * SWEEP;

const clamped = computed(() => Math.max(0, Math.min(100, props.score)));

const progress = computed(
    () => `${(ARC_LENGTH * clamped.value) / 100} ${CIRCUMFERENCE}`,
);

const BAND_LABELS: Record<string, string> = {
    strong: 'Strong',
    steady: 'Steady',
    watch: 'Watch',
    critical: 'Critical',
    unknown: 'No data',
};

const bandLabel = computed(() => BAND_LABELS[props.band] ?? props.band);

const BAND_CLASSES: Record<string, string> = {
    strong: 'border-status-completed/40 bg-status-completed/10 text-status-completed',
    steady: 'border-accent-cyan/40 bg-accent-cyan/10 text-accent-cyan',
    watch: 'border-status-review/40 bg-status-review/10 text-status-review',
    critical: 'border-status-critical/40 bg-status-critical/10 text-status-critical',
    unknown: 'border-white/15 bg-white/5 text-ink-500',
};

const bandClass = computed(
    () => BAND_CLASSES[props.band] ?? BAND_CLASSES.unknown,
);
</script>

<template>
    <section class="glass-panel flex h-full flex-col p-5" aria-labelledby="fleet-trust-heading">
        <div
            class="pointer-events-none absolute -left-16 -top-16 h-44 w-44 rounded-full bg-accent-violet/20 blur-3xl"
            aria-hidden="true"
        />

        <div class="relative flex items-start justify-between gap-3">
            <h2 id="fleet-trust-heading" class="panel-eyebrow">Fleet Trust Index</h2>
            <span
                v-if="bandLabel"
                class="rounded-full border px-2 py-0.5 text-[0.6rem] font-semibold uppercase tracking-[0.14em]"
                :class="bandClass"
            >
                {{ bandLabel }}
            </span>
        </div>

        <div class="relative mt-3 flex justify-center">
            <svg
                viewBox="0 0 160 160"
                class="h-36 w-36"
                role="img"
                :aria-label="`Fleet Trust Index ${clamped} out of 100`"
            >
                <defs>
                    <linearGradient id="trust-arc" x1="0" y1="1" x2="1" y2="0">
                        <stop offset="0%" stop-color="#2DE2E6" />
                        <stop offset="55%" stop-color="#5AA8FF" />
                        <stop offset="100%" stop-color="#8B7CFF" />
                    </linearGradient>
                    <filter id="trust-glow" x="-60%" y="-60%" width="220%" height="220%">
                        <feGaussianBlur stdDeviation="5" result="blur" />
                        <feMerge>
                            <feMergeNode in="blur" />
                            <feMergeNode in="SourceGraphic" />
                        </feMerge>
                    </filter>
                </defs>

                <g transform="rotate(135 80 80)">
                    <circle
                        cx="80"
                        cy="80"
                        :r="RADIUS"
                        fill="none"
                        stroke="rgba(255,255,255,0.08)"
                        stroke-width="10"
                        stroke-linecap="round"
                        :stroke-dasharray="`${ARC_LENGTH} ${CIRCUMFERENCE}`"
                    />
                    <circle
                        cx="80"
                        cy="80"
                        :r="RADIUS"
                        fill="none"
                        stroke="url(#trust-arc)"
                        stroke-width="10"
                        stroke-linecap="round"
                        :stroke-dasharray="progress"
                        filter="url(#trust-glow)"
                        class="transition-[stroke-dasharray] duration-700 ease-out"
                    />
                </g>

                <text
                    x="80"
                    y="78"
                    text-anchor="middle"
                    class="fill-ink-100 font-display text-[2.1rem] font-semibold"
                >
                    {{ clamped }}
                </text>
                <text
                    x="80"
                    y="98"
                    text-anchor="middle"
                    class="fill-ink-600 font-sans text-[0.6rem] uppercase tracking-[0.2em]"
                >
                    / 100
                </text>
            </svg>
        </div>

        <dl v-if="components.length" class="relative mt-4 space-y-2 border-t border-[rgba(160,205,245,0.12)] pt-3">
            <div
                v-for="item in components"
                :key="item.label"
                class="flex items-baseline justify-between gap-2"
            >
                <dt class="text-xs text-ink-500">
                    {{ item.label }}
                    <span class="font-mono text-[0.6rem] text-ink-900">{{ item.weight }}</span>
                </dt>
                <dd class="font-mono text-xs text-ink-200">{{ item.display }}</dd>
            </div>
        </dl>
    </section>
</template>
