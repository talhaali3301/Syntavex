<script setup lang="ts">
import type { LineTone, ReasoningTimeline } from '@/types';

defineProps<{
    timeline: ReasoningTimeline;
    activeStepId: number | null;
}>();

const LINE: Record<LineTone, string> = {
    ink: 'text-ink-400',
    ok: 'text-glow-green',
    warn: 'text-[#FFE1A6]',
    critical: 'text-glow-red',
};
</script>

<template>
    <section
        class="glass-panel flex min-h-0 flex-col border-accent-cyan/20 bg-[#070F1C] backdrop-blur-none"
        aria-labelledby="reasoning-heading"
    >
        <header
            class="flex items-center gap-2.5 border-b border-accent-cyan/[0.16] bg-[rgba(14,26,44,0.9)] px-[18px] py-[11px]"
        >
            <span class="flex gap-[5px]" aria-hidden="true">
                <span class="h-2 w-2 rounded-full bg-status-critical" />
                <span class="h-2 w-2 rounded-full bg-status-review" />
                <span class="h-2 w-2 rounded-full bg-status-completed" />
            </span>
            <h2
                id="reasoning-heading"
                class="font-mono text-[11px] font-medium tracking-[0.08em] text-glow-teal"
            >
                {{ timeline.title }}
            </h2>
            <div class="flex-1" />
            <span class="font-mono text-[10px] text-ink-900">{{ timeline.meta }}</span>
        </header>

        <div class="relative min-h-0 flex-1 overflow-y-auto px-5 py-4">
            <div
                class="pointer-events-none absolute inset-0 bg-[repeating-linear-gradient(180deg,rgba(160,215,245,0.035)_0px,rgba(160,215,245,0.035)_1px,transparent_1px,transparent_3px)]"
                aria-hidden="true"
            />

            <p
                v-if="!timeline.entries.length"
                class="relative font-mono text-[11.5px] text-ink-700"
            >
                No reasoning steps were recorded for this run.
            </p>

            <ol v-else class="relative flex flex-col gap-3.5 font-mono text-xs leading-[1.65]">
                <li
                    v-for="entry in timeline.entries"
                    :id="`reasoning-step-${entry.id}`"
                    :key="entry.id"
                    class="scroll-mt-4 rounded-md px-2 py-1 transition duration-200"
                    :class="
                        entry.id === activeStepId
                            ? 'bg-status-review/[0.09] shadow-[inset_0_0_0_1px_rgba(248,198,93,0.28)]'
                            : ''
                    "
                >
                    <p class="text-ink-900">
                        [{{ entry.time }}]
                        <span class="text-accent-violet">{{ entry.type }}</span>
                        <span class="text-ink-800"> ▸ </span>
                        <span class="text-ink-600">{{ entry.order_label }} {{ entry.name }}</span>
                    </p>

                    <p
                        v-for="(line, index) in entry.lines"
                        :key="index"
                        class="mt-[3px] break-words"
                        :class="LINE[line.tone]"
                    >
                        <span class="text-ink-800">{{ line.label }}</span>
                        {{ line.text }}
                    </p>
                </li>
            </ol>

            <p
                v-if="timeline.open"
                class="relative mt-3.5 flex items-center gap-2 font-mono text-xs text-ink-900"
            >
                awaiting countersignature
                <span
                    class="pulse-breathe inline-block h-[15px] w-2 bg-accent-cyan shadow-[0_0_12px_rgba(45,226,230,0.8)]"
                    aria-hidden="true"
                />
            </p>
        </div>
    </section>
</template>
