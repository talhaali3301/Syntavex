<script setup lang="ts">
import type { ExecutionTrace, TraceNode, TraceTone } from '@/types';
import { computed } from 'vue';

const props = defineProps<{ trace: ExecutionTrace }>();

const emit = defineEmits<{ select: [node: TraceNode] }>();

const VIEW = { width: 1120, height: 184 };
const MARGIN = 110;
const BASELINE = 100;
const AMPLITUDE = 42;

/**
 * Steps are laid out as a heartbeat rather than a straight line: an irregular
 * wave reads as activity, and the per-step drop lines keep it a timeline.
 */
const WAVE = 1.9;

const FILL: Record<TraceTone, string> = {
    reasoning: '#5AA8FF',
    tool: '#8B7CFF',
    policy: '#F8C65D',
    critical: '#F26C78',
    review: '#F8C65D',
    idle: 'rgba(150,195,235,0.35)',
};

const LEGEND: { tone: TraceTone; label: string }[] = [
    { tone: 'reasoning', label: 'reasoning' },
    { tone: 'tool', label: 'tool call' },
    { tone: 'policy', label: 'policy check' },
];

type Point = TraceNode & { x: number; y: number; label: string };

const spacing = computed(() => {
    const span = VIEW.width - MARGIN * 2;

    return props.trace.nodes.length > 1
        ? span / (props.trace.nodes.length - 1)
        : 0;
});

const GLYPH = 5.7;

/**
 * Labels are centred under their node, so each one has to fit both its own
 * slot and — for the outermost nodes — the margin it overhangs into.
 */
const labelLimit = computed(() => {
    const room = Math.min(spacing.value || VIEW.width, MARGIN * 2);

    return Math.max(8, Math.floor(room / GLYPH) - 4);
});

const points = computed<Point[]>(() =>
    props.trace.nodes.map((node, index) => {
        const name =
            node.name.length > labelLimit.value
                ? `${node.name.slice(0, labelLimit.value - 1)}…`
                : node.name;

        return {
            ...node,
            x:
                props.trace.nodes.length > 1
                    ? MARGIN + index * spacing.value
                    : VIEW.width / 2,
            y: BASELINE - AMPLITUDE * Math.cos(index * WAVE),
            label: `${node.order_label} ${name}`,
        };
    }),
);

/** Catmull-Rom through every node, emitted as cubic segments. */
const path = computed(() => {
    const list = points.value;

    if (list.length === 0) {
        return '';
    }

    if (list.length === 1) {
        return `M${list[0].x} ${list[0].y}`;
    }

    let d = `M${list[0].x} ${list[0].y}`;

    for (let i = 0; i < list.length - 1; i++) {
        const p0 = list[Math.max(0, i - 1)];
        const p1 = list[i];
        const p2 = list[i + 1];
        const p3 = list[Math.min(list.length - 1, i + 2)];

        const c1x = p1.x + (p2.x - p0.x) / 6;
        const c1y = p1.y + (p2.y - p0.y) / 6;
        const c2x = p2.x - (p3.x - p1.x) / 6;
        const c2y = p2.y - (p3.y - p1.y) / 6;

        d += ` C${c1x} ${c1y}, ${c2x} ${c2y}, ${p2.x} ${p2.y}`;
    }

    return d;
});

const selected = computed(() => points.value.find((point) => point.selected));
</script>

<template>
    <section
        class="relative overflow-hidden rounded-[18px] border border-[rgba(160,222,245,0.16)] bg-[linear-gradient(155deg,rgba(26,44,72,0.55),rgba(9,18,32,0.72))] px-[22px] pb-2.5 pt-4 shadow-[0_22px_52px_rgba(2,8,18,0.6),inset_0_1px_0_rgba(215,245,255,0.12)] backdrop-blur-[22px]"
        aria-labelledby="execution-trace-heading"
    >
        <div
            class="absolute inset-0 bg-[linear-gradient(rgba(150,195,235,0.04)_1px,transparent_1px),linear-gradient(90deg,rgba(150,195,235,0.04)_1px,transparent_1px)] bg-[length:44px_44px]"
            aria-hidden="true"
        />

        <header class="relative flex flex-wrap items-center gap-x-3 gap-y-1">
            <h2 id="execution-trace-heading" class="font-display text-[12.5px] font-semibold text-[#E3EFFA]">
                Execution Trace
            </h2>
            <p class="font-mono text-[10.5px] text-ink-700">{{ trace.summary }}</p>
            <div class="flex-1" />
            <ul class="flex gap-3.5 font-mono text-[10px] text-ink-600">
                <li v-for="item in LEGEND" :key="item.tone" class="flex items-center gap-[5px]">
                    <span
                        class="h-[7px] w-[7px] rounded-sm"
                        :style="{ background: FILL[item.tone] }"
                        aria-hidden="true"
                    />
                    {{ item.label }}
                </li>
            </ul>
        </header>

        <p v-if="!points.length" class="relative py-14 text-center font-mono text-[11px] text-ink-700">
            No steps were recorded for this run.
        </p>

        <svg
            v-else
            :viewBox="`0 0 ${VIEW.width} ${VIEW.height}`"
            width="100%"
            height="188"
            fill="none"
            class="relative"
            role="img"
            :aria-label="trace.summary"
        >
            <defs>
                <linearGradient id="trace-path" x1="0" x2="1">
                    <stop offset="0%" stop-color="rgba(90,168,255,0.25)" />
                    <stop offset="55%" stop-color="rgba(139,124,255,0.55)" />
                    <stop offset="78%" stop-color="#F8C65D" />
                    <stop offset="100%" stop-color="rgba(139,124,255,0.28)" />
                </linearGradient>
                <filter id="trace-node-glow" x="-200%" y="-200%" width="500%" height="500%">
                    <feGaussianBlur stdDeviation="5" result="blur" />
                    <feMerge>
                        <feMergeNode in="blur" />
                        <feMergeNode in="SourceGraphic" />
                    </feMerge>
                </filter>
                <radialGradient id="trace-halo">
                    <stop offset="0%" stop-color="rgba(248,198,93,0.38)" />
                    <stop offset="100%" stop-color="rgba(248,198,93,0)" />
                </radialGradient>
            </defs>

            <path :d="path" stroke="url(#trace-path)" stroke-width="2" stroke-linecap="round" />

            <g stroke="rgba(160,205,245,0.14)" stroke-width="1" stroke-dasharray="3 6">
                <line
                    v-for="point in points"
                    :key="`drop-${point.id}`"
                    :x1="point.x"
                    :y1="point.y"
                    :x2="point.x"
                    y2="150"
                />
            </g>

            <circle
                v-if="selected"
                :cx="selected.x"
                :cy="selected.y"
                r="58"
                fill="url(#trace-halo)"
            />

            <g filter="url(#trace-node-glow)">
                <circle
                    v-for="point in points"
                    :key="`node-${point.id}`"
                    :cx="point.x"
                    :cy="point.y"
                    :r="point.selected ? 16 : 9"
                    :fill="point.selected ? '#F8C65D' : FILL[point.tone]"
                />
            </g>

            <template v-if="selected">
                <circle
                    :cx="selected.x"
                    :cy="selected.y"
                    r="26"
                    stroke="rgba(248,198,93,0.55)"
                    stroke-width="1.4"
                />
                <circle
                    :cx="selected.x"
                    :cy="selected.y"
                    r="36"
                    stroke="rgba(248,198,93,0.22)"
                    stroke-width="1"
                    stroke-dasharray="4 7"
                />
                <text
                    :x="selected.x"
                    :y="selected.y + 4"
                    text-anchor="middle"
                    class="fill-[#2E1F03] font-mono text-[10px] font-semibold"
                >
                    {{ selected.order_label }}
                </text>
            </template>

            <g class="fill-ink-700 font-mono text-[9.5px]" text-anchor="middle">
                <text v-for="point in points" :key="`label-${point.id}`" :x="point.x" y="170">
                    {{ point.label }}
                </text>
            </g>

            <g>
                <circle
                    v-for="point in points"
                    :key="`hit-${point.id}`"
                    :cx="point.x"
                    :cy="point.y"
                    r="22"
                    fill="transparent"
                    class="cursor-pointer"
                    @click="emit('select', point)"
                >
                    <title>
                        {{ point.order_label }} · {{ point.name }} — {{ point.status }} ·
                        {{ point.duration_label }}
                    </title>
                </circle>
            </g>
        </svg>
    </section>
</template>
