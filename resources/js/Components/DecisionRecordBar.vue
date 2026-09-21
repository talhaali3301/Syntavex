<script setup lang="ts">
import type { DecisionRecord } from '@/types';
import { Link } from '@inertiajs/vue3';

defineProps<{ decision: DecisionRecord }>();
</script>

<template>
    <section
        class="glass-panel glass-panel-feature px-[22px] py-[18px]"
        aria-labelledby="decision-record-heading"
    >
        <div
            class="pointer-events-none absolute -top-[120px] left-10 h-[280px] w-[420px] rounded-full bg-[radial-gradient(circle,rgba(45,226,230,0.16),rgba(45,226,230,0)_70%)]"
            aria-hidden="true"
        />

        <div class="relative flex flex-wrap items-center gap-x-[22px] gap-y-4">
            <div class="flex min-w-0 flex-col gap-1.5">
                <h2 id="decision-record-heading" class="panel-eyebrow text-glow-teal">
                    {{ decision.eyebrow }}
                </h2>
                <p class="font-display text-[21px] font-semibold leading-tight tracking-[-0.01em] text-ink-100">
                    {{ decision.headline }}
                </p>
                <p class="text-xs text-ink-500">{{ decision.detail }}</p>
                <p v-if="decision.resolution?.notes" class="text-xs text-ink-600">
                    {{ decision.resolution.notes }}
                </p>
            </div>

            <div class="flex-1" />

            <Link
                v-if="decision.pending"
                href="/reviews"
                class="flex flex-none items-center gap-2.5 rounded-[10px] bg-[linear-gradient(140deg,#7DEDF0,#2DE2E6)] px-[22px] py-3 font-mono text-[11px] font-semibold tracking-[0.06em] text-[#061020] shadow-[0_0_30px_rgba(45,226,230,0.4)] transition duration-200 hover:brightness-110 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-cyan focus-visible:ring-offset-2 focus-visible:ring-offset-navy-base"
            >
                SIGN OFF AT THE DESK
                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                    <path d="M5 12h13M12.5 6.5 19 12l-6.5 5.5" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </Link>

            <p
                v-else-if="decision.resolution"
                class="flex flex-none items-center gap-2.5 rounded-[10px] border border-status-completed/40 bg-status-completed/10 px-4 py-3 font-mono text-[11px] font-semibold tracking-[0.06em] text-glow-green"
            >
                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="m4 12 5 5L20 6" />
                </svg>
                {{ decision.resolution.status.toUpperCase() }}
                <span class="font-normal text-ink-500">{{ decision.resolution.at }}</span>
            </p>
        </div>
    </section>
</template>
