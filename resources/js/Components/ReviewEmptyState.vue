<script setup lang="ts">
import type { ReviewStats } from '@/types';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{ stats: ReviewStats }>();

const decided = computed(() => props.stats.approved + props.stats.rejected);
</script>

<template>
    <div class="relative overflow-hidden px-8 py-14">
        <div
            class="pointer-events-none absolute -top-40 left-[38%] h-[560px] w-[660px] rounded-full bg-status-completed/[0.10] blur-3xl"
            aria-hidden="true"
        />

        <div
            class="glass-panel mx-auto flex max-w-[35rem] flex-col items-center gap-3.5 border-status-completed/25 px-10 py-10 text-center"
        >
            <svg class="h-[62px] w-[62px]" viewBox="0 0 24 24" fill="none" stroke="rgba(85,217,139,0.65)" stroke-width="1.2" aria-hidden="true">
                <circle cx="12" cy="12" r="9" />
                <path d="m8 12.4 2.6 2.6L16 9.6" />
            </svg>

            <h2 class="font-display text-[19px] font-semibold text-ink-100">All clear</h2>

            <p class="text-[12.5px] leading-relaxed text-ink-500">
                No runs are waiting on a human. Every policy gate that tripped in the
                last {{ stats.window_label }} has been signed off. The desk stays
                quiet until an agent hits a ceiling it cannot clear on its own.
            </p>

            <p class="font-mono text-[11px] text-ink-700">
                {{ stats.intercepted }} intercepted · {{ decided }} decided
                <template v-if="decided">
                    ({{ stats.approved }} approved · {{ stats.rejected }} rejected)
                </template>
                · last {{ stats.window_label }}
            </p>

            <div class="mt-1.5 flex flex-wrap justify-center gap-2.5">
                <Link
                    href="/runs?status=needs_review"
                    class="rounded-lg border border-accent-cyan/45 px-[15px] py-2.5 font-mono text-[10.5px] font-semibold tracking-[0.06em] text-glow-cyan transition duration-200 hover:bg-accent-cyan/[0.14] hover:shadow-[0_0_20px_rgba(45,226,230,0.22)] focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-cyan"
                >
                    REVIEW HISTORY
                </Link>
                <Link
                    href="/dashboard"
                    class="rounded-lg border border-[rgba(160,205,245,0.16)] px-[15px] py-2.5 font-mono text-[10.5px] font-semibold tracking-[0.06em] text-ink-400 transition duration-200 hover:border-[rgba(160,205,245,0.35)] hover:text-ink-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-cyan"
                >
                    COMMAND CENTRE
                </Link>
            </div>
        </div>
    </div>
</template>
