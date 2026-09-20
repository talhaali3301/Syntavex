<script setup lang="ts">
import type { QueueConstellation, RiskLevel } from '@/types';

defineProps<{ constellation: QueueConstellation }>();

const NODE: Record<RiskLevel, { fill: string; radius: number }> = {
    critical: { fill: '#F26C78', radius: 4.6 },
    high: { fill: '#F26C78', radius: 4 },
    medium: { fill: '#F8C65D', radius: 3.4 },
    low: { fill: '#5AA8FF', radius: 3 },
};
</script>

<template>
    <section
        class="rounded-2xl border border-[rgba(160,205,245,0.11)] bg-[rgba(10,20,35,0.7)] px-4 py-3.5 shadow-[0_16px_38px_rgba(2,8,18,0.45)] backdrop-blur-[18px]"
        aria-labelledby="constellation-heading"
    >
        <h2
            id="constellation-heading"
            class="font-mono text-[10px] font-semibold tracking-[0.14em] text-ink-600"
        >
            QUEUE CONSTELLATION
        </h2>

        <svg
            viewBox="0 0 100 100"
            class="mt-2 h-[150px] w-full"
            role="img"
            :aria-label="constellation.caption"
        >
            <line
                v-for="node in constellation.nodes"
                :key="`link-${node.run_key}`"
                x1="50"
                y1="50"
                :x2="node.x"
                :y2="node.y"
                stroke="rgba(150,195,235,0.22)"
                stroke-width="0.5"
                stroke-dasharray="2 2"
            />

            <circle cx="50" cy="50" r="7.5" fill="rgba(45,226,230,0.10)" stroke="rgba(45,226,230,0.5)" stroke-width="0.6" />
            <text x="50" y="51.6" text-anchor="middle" class="fill-[#BFF6F7] font-mono text-[4px]">
                you
            </text>

            <g v-for="node in constellation.nodes" :key="node.run_key">
                <circle
                    :cx="node.x"
                    :cy="node.y"
                    :r="NODE[node.level].radius + 3"
                    :fill="NODE[node.level].fill"
                    opacity="0.16"
                    :class="node.level === 'critical' ? 'risk-ring' : ''"
                />
                <circle
                    :cx="node.x"
                    :cy="node.y"
                    :r="NODE[node.level].radius"
                    :fill="NODE[node.level].fill"
                />
                <text
                    :x="node.x"
                    :y="node.y - NODE[node.level].radius - 2.6"
                    text-anchor="middle"
                    class="fill-ink-600 font-mono text-[4.2px]"
                >
                    {{ node.run_key }}
                </text>
            </g>
        </svg>

        <p class="mt-1 font-mono text-[10px] text-ink-800">{{ constellation.caption }}</p>
    </section>
</template>
