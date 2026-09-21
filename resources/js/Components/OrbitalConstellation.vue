<script setup lang="ts">
import type { CoverConstellation, CoverConstellationNode, StatusTone } from '@/types';

defineProps<{ constellation: CoverConstellation }>();

const TONE: Record<StatusTone, string> = {
    completed: '#55D98B',
    review: '#F8C65D',
    critical: '#F26C78',
    info: '#5AA8FF',
};

const LABEL_OFFSET = 2.2;

const labelX = (node: CoverConstellationNode): number =>
    node.anchor === 'start'
        ? node.x + node.radius + LABEL_OFFSET
        : node.anchor === 'end'
          ? node.x - node.radius - LABEL_OFFSET
          : node.x;

const labelY = (node: CoverConstellationNode): number =>
    node.anchor === 'middle' ? node.y - node.radius - 8 : node.y - 0.6;
</script>

<template>
    <div class="relative aspect-[43/29] w-full tone-vars">
        <div
            class="pointer-events-none absolute inset-[22%] rounded-full bg-[radial-gradient(circle,rgba(45,226,230,0.20),rgba(139,124,255,0.08)_55%,transparent_72%)] blur-[2px]"
            aria-hidden="true"
        />

        <svg
            viewBox="-36 -8 172 116"
            class="relative h-full w-full"
            role="img"
            :aria-label="constellation.caption"
        >
            <defs>
                <radialGradient id="orbit-core" cx="50%" cy="50%" r="50%">
                    <stop offset="0%" stop-color="#2DE2E6" stop-opacity="0.45" />
                    <stop offset="60%" stop-color="#8B7CFF" stop-opacity="0.18" />
                    <stop offset="100%" stop-color="#8B7CFF" stop-opacity="0" />
                </radialGradient>
            </defs>

            <g class="orbit-drift" style="transform-origin: 50px 50px">
                <circle
                    v-for="orbit in constellation.orbits"
                    :key="`orbit-${orbit}`"
                    cx="50"
                    cy="50"
                    :r="orbit"
                    fill="none"
                    stroke="rgba(160,205,245,0.16)"
                    stroke-width="0.22"
                    stroke-dasharray="1.6 2.4"
                />
            </g>

            <line
                v-for="node in constellation.nodes"
                :key="`link-${node.id}`"
                x1="50"
                y1="50"
                :x2="node.x"
                :y2="node.y"
                :stroke="TONE[node.tone]"
                stroke-width="0.35"
                stroke-opacity="0.32"
                stroke-dasharray="1.2 2"
                class="link-flow"
            />

            <circle cx="50" cy="50" r="16" fill="url(#orbit-core)" />
            <circle
                cx="50"
                cy="50"
                r="8.4"
                fill="rgba(6,16,32,0.85)"
                stroke="rgba(45,226,230,0.55)"
                stroke-width="0.5"
            />
            <text
                x="50"
                y="49.6"
                text-anchor="middle"
                class="fill-[#BFF6F7] font-display text-[5.4px] font-semibold"
            >
                {{ constellation.core_display }}
            </text>
            <text
                x="50"
                y="54.2"
                text-anchor="middle"
                class="fill-ink-600 font-mono text-[2.6px] tracking-[0.18em]"
            >
                RUNS
            </text>

            <g v-for="node in constellation.nodes" :key="node.id">
                <circle
                    :cx="node.x"
                    :cy="node.y"
                    :r="node.radius + 3.2"
                    :fill="TONE[node.tone]"
                    opacity="0.14"
                    :class="node.tone === 'critical' ? 'risk-ring' : ''"
                />
                <circle
                    :cx="node.x"
                    :cy="node.y"
                    :r="node.radius"
                    :fill="TONE[node.tone]"
                    fill-opacity="0.85"
                    :stroke="TONE[node.tone]"
                    stroke-width="0.4"
                />
                <title>{{ node.label }} · {{ node.runs_label }}</title>
                <text
                    :x="labelX(node)"
                    :y="labelY(node)"
                    :text-anchor="node.anchor"
                    class="fill-ink-300 font-display text-[3.2px] font-medium"
                >
                    {{ node.short_label }}
                </text>
                <text
                    :x="labelX(node)"
                    :y="labelY(node) + 3.8"
                    :text-anchor="node.anchor"
                    class="fill-ink-700 font-mono text-[2.7px]"
                >
                    {{ node.runs_label }}
                </text>
            </g>
        </svg>
    </div>
</template>
