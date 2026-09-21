<script setup lang="ts">
import type { FleetPulse } from '@/types';

defineProps<{ pulse: FleetPulse }>();
</script>

<template>
    <span
        v-if="pulse.live"
        class="flex shrink-0 items-center gap-2 rounded-full border border-accent-cyan/35 bg-accent-cyan/10 px-3 py-1.5 font-mono text-[10.5px] font-semibold tracking-[0.08em] text-accent-cyan"
    >
        <span
            class="pulse-breathe h-1.5 w-1.5 rounded-full bg-accent-cyan shadow-[0_0_10px_2px_rgba(45,226,230,0.7)]"
            aria-hidden="true"
        />
        FLEET LIVE · {{ pulse.latest_run_label }}
    </span>
    <span
        v-else
        class="flex shrink-0 items-center gap-2 rounded-full border border-[rgba(160,205,245,0.16)] bg-white/[0.03] px-3 py-1.5 font-mono text-[10.5px] font-semibold tracking-[0.08em] text-ink-600"
    >
        <span class="h-1.5 w-1.5 rounded-full bg-ink-800" aria-hidden="true" />
        FLEET IDLE
    </span>
</template>
