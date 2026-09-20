<script setup lang="ts">
type Accent = 'cyan' | 'violet' | 'emerald' | 'amber';

withDefaults(
    defineProps<{
        label: string;
        value: string;
        caption?: string;
        accent?: Accent;
    }>(),
    { accent: 'cyan', caption: '' },
);

const DOT: Record<Accent, string> = {
    cyan: 'bg-accent-cyan shadow-[0_0_10px_2px_rgba(45,226,230,0.65)]',
    violet: 'bg-accent-violet shadow-[0_0_10px_2px_rgba(139,124,255,0.65)]',
    emerald: 'bg-status-completed shadow-[0_0_10px_2px_rgba(85,217,139,0.6)]',
    amber: 'bg-status-review shadow-[0_0_10px_2px_rgba(248,198,93,0.6)]',
};

const GLOW: Record<Accent, string> = {
    cyan: 'bg-accent-cyan/25',
    violet: 'bg-accent-violet/25',
    emerald: 'bg-status-completed/20',
    amber: 'bg-status-review/20',
};

const RULE: Record<Accent, string> = {
    cyan: 'from-accent-cyan/70',
    violet: 'from-accent-violet/70',
    emerald: 'from-status-completed/70',
    amber: 'from-status-review/70',
};
</script>

<template>
    <article class="glass-panel group p-5 transition duration-300 hover:border-white/20">
        <div
            class="pointer-events-none absolute -right-10 -top-12 h-28 w-28 rounded-full blur-3xl transition duration-500 group-hover:opacity-80"
            :class="GLOW[accent ?? 'cyan']"
            aria-hidden="true"
        />
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r to-transparent"
            :class="RULE[accent ?? 'cyan']"
            aria-hidden="true"
        />

        <div class="relative flex items-center gap-2">
            <span class="h-1.5 w-1.5 rounded-full" :class="DOT[accent ?? 'cyan']" aria-hidden="true" />
            <h3 class="panel-eyebrow">{{ label }}</h3>
        </div>

        <p class="relative mt-4 font-display text-3xl font-semibold tracking-tight text-white">
            {{ value }}
        </p>

        <p v-if="caption" class="relative mt-1.5 text-xs text-white/45">
            {{ caption }}
        </p>
    </article>
</template>
