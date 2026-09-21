<script setup lang="ts">
type Tone = 'neutral' | 'accent' | 'violet' | 'review' | 'critical';

withDefaults(
    defineProps<{
        title?: string;
        meta?: string;
        tone?: Tone;
        sticky?: boolean;
    }>(),
    { title: '', meta: '', tone: 'neutral', sticky: false },
);

const EDGE: Record<Tone, string> = {
    neutral: 'border-[rgba(160,205,245,0.09)]',
    accent: 'border-accent-cyan/[0.18]',
    violet: 'border-accent-violet/[0.20]',
    review: 'border-status-review/[0.22]',
    critical: 'border-status-critical/[0.18]',
};

const GLOW: Record<Tone, string | null> = {
    neutral: null,
    accent: 'bg-[radial-gradient(circle,rgba(45,226,230,0.13),rgba(45,226,230,0)_70%)]',
    violet: 'bg-[radial-gradient(circle,rgba(139,124,255,0.14),rgba(139,124,255,0)_70%)]',
    review: 'bg-[radial-gradient(circle,rgba(248,198,93,0.15),rgba(248,198,93,0)_70%)]',
    critical: 'bg-[radial-gradient(circle,rgba(242,108,120,0.14),rgba(242,108,120,0)_70%)]',
};
</script>

<template>
    <header
        class="relative flex min-h-[74px] flex-col justify-center gap-2.5 border-b bg-gradient-to-b from-[rgba(14,26,44,0.82)] to-[rgba(9,18,32,0.42)] px-8 py-[14px] backdrop-blur-lg"
        :class="[EDGE[tone], sticky ? 'sticky top-0 z-20' : '']"
    >
        <div
            v-if="GLOW[tone]"
            class="pointer-events-none absolute -top-[150px] left-[180px] h-[300px] w-[520px] rounded-full"
            :class="GLOW[tone]"
            aria-hidden="true"
        />

        <slot name="above" />

        <div class="relative flex flex-wrap items-center gap-x-4 gap-y-3">
            <slot name="lead" />

            <div class="flex min-w-0 flex-col gap-0.5">
                <div class="flex flex-wrap items-center gap-2.5">
                    <h1 class="page-title">
                        <slot name="title">{{ title }}</slot>
                    </h1>
                    <slot name="badges" />
                </div>

                <p v-if="$slots.meta || meta" class="page-meta">
                    <slot name="meta">{{ meta }}</slot>
                </p>
            </div>

            <div v-if="$slots.actions" class="ml-auto flex flex-wrap items-center gap-3">
                <slot name="actions" />
            </div>
        </div>

        <slot name="below" />
    </header>
</template>
