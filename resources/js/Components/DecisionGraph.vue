<script setup lang="ts">
import type { DecisionGraphData, DecisionGraphNode, StatusTone } from '@/types';
import { router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps<{
    graph: DecisionGraphData;
}>();

/**
 * The canvas is a fixed 1000×700, but this panel is as tall as whatever sits
 * beside it. Fitting the whole canvas with `meet` therefore letterboxed the
 * drawing — on a 1440×900 laptop it left roughly 180px of dead space inside
 * the panel.
 *
 * Instead, fit the box the drawing actually occupies (`graph.bounds`) and grow
 * it on whichever axis the panel has to spare. The result always fills the
 * panel, is never stretched (both axes keep one scale) and can never crop,
 * because the window only ever grows beyond the content.
 */
const frame = ref<HTMLElement | null>(null);
const frameRatio = ref<number | null>(null);

let observer: ResizeObserver | undefined;

const measure = (): void => {
    const box = frame.value?.getBoundingClientRect();

    frameRatio.value = box && box.width > 0 && box.height > 0 ? box.width / box.height : null;
};

onMounted(() => {
    measure();

    if (typeof ResizeObserver === 'undefined' || frame.value === null) {
        return;
    }

    observer = new ResizeObserver(measure);
    observer.observe(frame.value);
});

onBeforeUnmount(() => observer?.disconnect());

const viewBox = computed(() => {
    const { x, y, width, height } = props.graph.bounds;
    const ratio = frameRatio.value;

    // Before the first measurement, fall back to the content box itself.
    if (ratio === null || !Number.isFinite(ratio) || width <= 0 || height <= 0) {
        return `${x} ${y} ${width} ${height}`;
    }

    if (ratio > width / height) {
        // Panel is wider than the drawing: widen the window, keep the height.
        const grown = height * ratio;

        return `${x - (grown - width) / 2} ${y} ${grown} ${height}`;
    }

    const grown = width / ratio;

    return `${x} ${y - (grown - height) / 2} ${width} ${grown}`;
});

/** Resolves against the `.tone-vars` custom properties declared in app.css. */
const TONE_VAR: Record<StatusTone, string> = {
    completed: 'var(--tone-completed)',
    review: 'var(--tone-review)',
    critical: 'var(--tone-critical)',
    info: 'var(--tone-info)',
};

const TONE_DOT: Record<StatusTone, string> = {
    completed: 'bg-status-completed',
    review: 'bg-status-review',
    critical: 'bg-status-critical',
    info: 'bg-status-info',
};

const callout = computed(() => props.graph.callout);

const plotted = computed(() => props.graph.clusters.length > 0);

const openRun = (node: DecisionGraphNode): void => {
    router.visit(`/runs/${node.id}`);
};
</script>

<template>
    <section class="glass-panel flex h-full flex-col p-5" aria-labelledby="decision-graph-heading">
        <header class="relative flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 id="decision-graph-heading" class="panel-heading">Decision Graph</h2>
                <p v-if="plotted" class="mt-0.5 text-xs text-white/40">
                    Runs clustered by workflow · distance from core encodes risk
                </p>
            </div>

            <ul v-if="plotted" class="flex flex-wrap items-center gap-x-4 gap-y-1">
                <li
                    v-for="entry in graph.legend"
                    :key="entry.status"
                    class="flex items-center gap-1.5 text-[0.7rem] text-white/50"
                >
                    <span
                        class="h-2 w-2 rounded-full"
                        :class="TONE_DOT[entry.tone]"
                        aria-hidden="true"
                    />
                    {{ entry.label }}
                    <span class="font-mono text-white/75">{{ entry.count }}</span>
                </li>
            </ul>
        </header>

        <div ref="frame" class="relative mt-3 min-h-0 flex-1">
            <div
                v-if="!plotted"
                class="flex h-full flex-col items-center justify-center gap-3 text-center"
            >
                <svg
                    class="h-14 w-14"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="rgba(45,226,230,0.45)"
                    stroke-width="1.1"
                    aria-hidden="true"
                >
                    <circle cx="12" cy="12" r="2.4" />
                    <circle cx="5" cy="6" r="1.8" />
                    <circle cx="19" cy="7" r="1.8" />
                    <circle cx="6.5" cy="18" r="1.8" />
                    <circle cx="18" cy="17.5" r="1.8" stroke="rgba(139,124,255,0.7)" />
                    <path d="M6.4 7.1 10 10.4M17.6 8.1 14 10.6M7.4 16.6 10.4 13.7M16.7 16.2 13.8 13.6" stroke-dasharray="2 3" />
                </svg>

                <p class="font-display text-sm font-semibold text-white">
                    No runs in this window
                </p>
                <p class="max-w-[38ch] text-xs leading-relaxed text-white/40">
                    Clusters are plotted from the runs inside the selected observation
                    window. Widen the window to see the fleet take shape.
                </p>
            </div>

            <svg
                v-else
                :viewBox="viewBox"
                class="tone-vars h-full w-full"
                preserveAspectRatio="xMidYMid meet"
                role="img"
                aria-label="Decision graph of workflow run clusters"
            >
                <defs>
                    <radialGradient id="cluster-halo">
                        <stop offset="55%" stop-color="rgba(255,255,255,0.03)" />
                        <stop offset="100%" stop-color="rgba(255,255,255,0)" />
                    </radialGradient>
                    <radialGradient id="cluster-halo-hot">
                        <stop offset="45%" stop-color="rgba(242,108,120,0.14)" />
                        <stop offset="100%" stop-color="rgba(242,108,120,0)" />
                    </radialGradient>
                    <filter id="node-glow" x="-180%" y="-180%" width="460%" height="460%">
                        <feGaussianBlur stdDeviation="3.2" result="blur" />
                        <feMerge>
                            <feMergeNode in="blur" />
                            <feMergeNode in="SourceGraphic" />
                        </feMerge>
                    </filter>
                </defs>

                <!-- Risk-proximity links between cluster cores -->
                <g stroke-linecap="round">
                    <line
                        v-for="(link, index) in graph.links"
                        :key="`link-${index}`"
                        :x1="link.x1"
                        :y1="link.y1"
                        :x2="link.x2"
                        :y2="link.y2"
                        :stroke="link.hot ? 'var(--tone-critical)' : 'rgba(255,255,255,0.14)'"
                        :stroke-opacity="link.hot ? 0.35 : 1"
                        stroke-width="1"
                        stroke-dasharray="4 6"
                        :class="link.hot ? 'link-flow' : ''"
                    />
                </g>

                <g v-for="cluster in graph.clusters" :key="cluster.id">
                    <circle
                        :cx="cluster.x"
                        :cy="cluster.y"
                        :r="cluster.radius"
                        :fill="cluster.flagged ? 'url(#cluster-halo-hot)' : 'url(#cluster-halo)'"
                        :stroke="cluster.flagged ? 'rgba(242,108,120,0.45)' : 'rgba(255,255,255,0.10)'"
                        stroke-width="1"
                        stroke-dasharray="3 7"
                    />

                    <circle
                        v-if="cluster.flagged"
                        :cx="cluster.x"
                        :cy="cluster.y"
                        :r="cluster.radius"
                        fill="none"
                        stroke="var(--tone-critical)"
                        stroke-width="1.5"
                        class="risk-ring"
                    />

                    <!-- Spokes from each run back to its cluster core -->
                    <line
                        v-for="node in cluster.nodes"
                        :key="`spoke-${node.id}`"
                        :x1="cluster.x"
                        :y1="cluster.y"
                        :x2="node.x"
                        :y2="node.y"
                        stroke="rgba(255,255,255,0.10)"
                        stroke-width="0.75"
                    />

                    <!-- Cluster core: the flagged run when there is one -->
                    <template v-if="cluster.core">
                        <circle
                            :cx="cluster.core.x"
                            :cy="cluster.core.y"
                            :r="cluster.core.r + 9"
                            fill="none"
                            stroke="var(--tone-critical)"
                            stroke-width="1.5"
                            class="risk-ring"
                        />
                        <circle
                            :cx="cluster.core.x"
                            :cy="cluster.core.y"
                            :r="cluster.core.r + 4"
                            fill="var(--tone-critical)"
                            fill-opacity="0.18"
                            stroke="var(--tone-critical)"
                            stroke-opacity="0.7"
                            stroke-width="1.5"
                        />
                        <g
                            role="link"
                            tabindex="0"
                            class="graph-node"
                            :aria-label="`Open run ${cluster.core.run_key} in the Run Inspector`"
                            @click="openRun(cluster.core)"
                            @keydown.enter.prevent="openRun(cluster.core)"
                            @keydown.space.prevent="openRun(cluster.core)"
                        >
                            <circle
                                :cx="cluster.core.x"
                                :cy="cluster.core.y"
                                :r="cluster.core.r"
                                :fill="TONE_VAR[cluster.core.tone]"
                                stroke="rgba(255,255,255,0.9)"
                                stroke-width="1.5"
                                filter="url(#node-glow)"
                            >
                                <title>
                                    #{{ cluster.core.run_key }} · {{ cluster.core.status }} ·
                                    {{ cluster.core.cost_label }} · flagged
                                </title>
                            </circle>

                            <circle
                                class="graph-node-hit"
                                :cx="cluster.core.x"
                                :cy="cluster.core.y"
                                :r="cluster.core.r + 13"
                                fill="transparent"
                                stroke-width="2"
                            />
                        </g>
                        <text
                            :x="cluster.core.x"
                            :y="cluster.core.y + cluster.core.r + 18"
                            text-anchor="middle"
                            class="fill-white font-mono text-[11px]"
                        >
                            #{{ cluster.core.run_key }}
                        </text>
                    </template>

                    <circle
                        v-else
                        :cx="cluster.x"
                        :cy="cluster.y"
                        r="3.5"
                        fill="rgba(255,255,255,0.55)"
                    />

                    <g
                        v-for="node in cluster.nodes"
                        :key="node.id"
                        role="link"
                        tabindex="0"
                        class="graph-node"
                        :aria-label="`Open run ${node.run_key} in the Run Inspector`"
                        @click="openRun(node)"
                        @keydown.enter.prevent="openRun(node)"
                        @keydown.space.prevent="openRun(node)"
                    >
                        <circle
                            :cx="node.x"
                            :cy="node.y"
                            :r="node.r"
                            :fill="TONE_VAR[node.tone]"
                            :fill-opacity="node.at_risk ? 1 : 0.85"
                            stroke="transparent"
                            stroke-width="1.5"
                            :filter="node.at_risk ? 'url(#node-glow)' : undefined"
                        >
                            <title>
                                #{{ node.run_key }} · {{ node.status }} · {{ node.cost_label }}
                            </title>
                        </circle>

                        <!-- Widens the pointer/focus target past the 4-7px dot. -->
                        <circle
                            class="graph-node-hit"
                            :cx="node.x"
                            :cy="node.y"
                            :r="node.r + 7"
                            fill="transparent"
                            stroke-width="2"
                        />
                    </g>

                    <text
                        :x="cluster.x"
                        :y="cluster.y - cluster.radius - 14"
                        text-anchor="middle"
                        class="fill-white/75 font-display text-[13px] font-semibold"
                    >
                        {{ cluster.label }}
                    </text>
                    <text
                        :x="cluster.x"
                        :y="cluster.y - cluster.radius + 1"
                        text-anchor="middle"
                        class="font-mono text-[11px]"
                        :class="cluster.at_risk_count > 0 ? 'fill-status-critical/80' : 'fill-white/35'"
                    >
                        {{ cluster.risk_label }}
                    </text>
                </g>
            </svg>
        </div>

        <aside
            v-if="callout"
            class="relative mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 rounded-xl border border-status-critical/35 bg-status-critical/[0.07] px-3.5 py-2.5"
        >
            <span
                class="h-2 w-2 shrink-0 rounded-full bg-status-critical pulse-breathe shadow-[0_0_10px_2px_rgba(242,108,120,0.7)]"
                aria-hidden="true"
            />
            <p class="text-[0.65rem] font-semibold uppercase tracking-[0.16em] text-status-critical">
                {{ callout.title }}
            </p>
            <span class="text-white/20" aria-hidden="true">·</span>
            <p class="font-display text-sm font-semibold text-white">{{ callout.workflow }}</p>
            <p class="text-xs text-white/55">{{ callout.detail }}</p>
        </aside>
    </section>
</template>
