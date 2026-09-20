<script setup lang="ts">
/**
 * Footer heartbeat strip. Phase 3 renders a looping static waveform — the
 * labels beside it are real workspace figures, not sample data. Live
 * streaming lands with the telemetry channel in a later phase.
 */
withDefaults(
    defineProps<{
        stats?: { label: string; value: string }[];
        label?: string;
    }>(),
    { stats: () => [], label: 'Live Execution Pulse' },
);

/** One heartbeat period, 240 units wide; tiled to fill the strip. */
const BEAT =
    'M0 30 H26 L34 30 L40 12 L46 48 L52 22 L58 30 H86 L92 30 L98 20 L104 40 L110 30 H150 L156 30 L162 16 L168 44 L174 30 H240';
</script>

<template>
    <section
        class="glass-panel flex flex-wrap items-center gap-x-6 gap-y-3 px-5 py-4"
        aria-labelledby="live-pulse-heading"
    >
        <div class="flex shrink-0 items-center gap-2">
            <span
                class="h-2 w-2 rounded-full bg-accent-cyan pulse-breathe shadow-[0_0_10px_2px_rgba(45,226,230,0.7)]"
                aria-hidden="true"
            />
            <h2 id="live-pulse-heading" class="panel-eyebrow">{{ label }}</h2>
        </div>

        <div class="relative h-12 min-w-[12rem] flex-1 overflow-hidden">
            <svg
                class="h-full w-full"
                viewBox="0 0 480 60"
                preserveAspectRatio="none"
                aria-hidden="true"
                focusable="false"
            >
                <defs>
                    <linearGradient id="pulse-stroke" x1="0" y1="0" x2="1" y2="0">
                        <stop offset="0%" stop-color="#2DE2E6" stop-opacity="0.15" />
                        <stop offset="35%" stop-color="#2DE2E6" stop-opacity="0.95" />
                        <stop offset="75%" stop-color="#8B7CFF" stop-opacity="0.95" />
                        <stop offset="100%" stop-color="#8B7CFF" stop-opacity="0.15" />
                    </linearGradient>
                    <filter id="pulse-glow" x="-10%" y="-80%" width="120%" height="260%">
                        <feGaussianBlur stdDeviation="2.6" result="blur" />
                        <feMerge>
                            <feMergeNode in="blur" />
                            <feMergeNode in="SourceGraphic" />
                        </feMerge>
                    </filter>
                </defs>

                <line
                    x1="0"
                    y1="30"
                    x2="480"
                    y2="30"
                    stroke="rgba(255,255,255,0.08)"
                    stroke-width="1"
                />

                <!-- Two tiled copies scrolled by 50% for a seamless loop. -->
                <g class="pulse-scroll" stroke="url(#pulse-stroke)" stroke-width="1.8" fill="none"
                   stroke-linecap="round" stroke-linejoin="round" filter="url(#pulse-glow)">
                    <path :d="BEAT" />
                    <path :d="BEAT" transform="translate(240 0)" />
                    <path :d="BEAT" transform="translate(480 0)" />
                    <path :d="BEAT" transform="translate(720 0)" />
                </g>
            </svg>
        </div>

        <dl v-if="stats.length" class="flex shrink-0 flex-wrap items-center gap-x-6 gap-y-2">
            <div v-for="stat in stats" :key="stat.label" class="text-right">
                <dt class="panel-eyebrow">{{ stat.label }}</dt>
                <dd class="mt-0.5 font-mono text-sm text-white/85">{{ stat.value }}</dd>
            </div>
        </dl>
    </section>
</template>
