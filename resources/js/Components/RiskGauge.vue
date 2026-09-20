<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    /** 0–1. */
    score: number;
    scoreLabel: string;
    bandLabel: string;
}>();

/** Same 270° dial as the Fleet Trust Index gauge, drawn at inspector scale. */
const RADIUS = 72;
const SWEEP = 0.75;
const CIRCUMFERENCE = 2 * Math.PI * RADIUS;
const ARC_LENGTH = CIRCUMFERENCE * SWEEP;

const clamped = computed(() => Math.max(0, Math.min(1, props.score)));

const progress = computed(
    () => `${ARC_LENGTH * clamped.value} ${CIRCUMFERENCE}`,
);
</script>

<template>
    <svg
        viewBox="0 0 226 168"
        class="h-[168px] w-full"
        fill="none"
        role="img"
        :aria-label="`Risk score ${scoreLabel} out of 1.00 — ${bandLabel}`"
    >
        <defs>
            <linearGradient id="risk-arc" x1="0" y1="1" x2="1" y2="0">
                <stop offset="0%" stop-color="#F8C65D" />
                <stop offset="60%" stop-color="#F26C78" />
                <stop offset="100%" stop-color="#FF97A2" />
            </linearGradient>
            <filter id="risk-glow" x="-60%" y="-60%" width="220%" height="220%">
                <feGaussianBlur stdDeviation="6" result="blur" />
                <feMerge>
                    <feMergeNode in="blur" />
                    <feMergeNode in="SourceGraphic" />
                </feMerge>
            </filter>
        </defs>

        <g transform="rotate(135 113 92)">
            <circle
                cx="113"
                cy="92"
                :r="RADIUS"
                stroke="rgba(150,195,235,0.12)"
                stroke-width="9"
                stroke-linecap="round"
                :stroke-dasharray="`${ARC_LENGTH} ${CIRCUMFERENCE}`"
            />
            <circle
                cx="113"
                cy="92"
                :r="RADIUS"
                stroke="url(#risk-arc)"
                stroke-width="9"
                stroke-linecap="round"
                :stroke-dasharray="progress"
                filter="url(#risk-glow)"
                class="transition-[stroke-dasharray] duration-700 ease-out"
            />
        </g>

        <circle cx="113" cy="92" r="58" stroke="rgba(242,108,120,0.14)" stroke-width="1" />

        <text
            x="113"
            y="96"
            text-anchor="middle"
            class="fill-[#FFE9EB] font-display text-[46px] font-semibold [letter-spacing:-1px]"
        >
            {{ scoreLabel }}
        </text>
        <text
            x="113"
            y="116"
            text-anchor="middle"
            class="fill-[#D79AA3] font-mono text-[10px] [letter-spacing:3px]"
        >
            {{ bandLabel }}
        </text>
    </svg>
</template>
