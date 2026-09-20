<script setup lang="ts">
import type { PaginationData } from '@/types';
import { Link } from '@inertiajs/vue3';

defineProps<{
    pagination: PaginationData;
}>();

const ARROW =
    'grid h-8 w-8 place-items-center rounded-lg border transition duration-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-cyan';
</script>

<template>
    <nav
        class="flex flex-wrap items-center gap-2.5 border-t border-[rgba(160,205,245,0.10)] bg-gradient-to-t from-[rgba(11,22,39,0.95)] to-[rgba(10,20,36,0.6)] px-5 py-3.5"
        aria-label="Runs pagination"
    >
        <p class="font-mono text-[11px] text-ink-700">{{ pagination.range_label }}</p>

        <div class="ml-auto flex items-center gap-2.5">
            <component
                :is="pagination.prev_url ? Link : 'span'"
                :href="pagination.prev_url ?? undefined"
                :class="[
                    ARROW,
                    pagination.prev_url
                        ? 'cursor-pointer border-[rgba(160,205,245,0.10)] text-ink-400 hover:border-accent-cyan/45 hover:text-glow-cyan'
                        : 'cursor-not-allowed border-[rgba(160,205,245,0.10)] opacity-45',
                ]"
                :aria-disabled="pagination.prev_url ? undefined : 'true'"
                aria-label="Previous page"
            >
                <svg class="h-[13px] w-[13px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="m14 6-6 6 6 6" />
                </svg>
            </component>

            <template v-for="(link, index) in pagination.links" :key="index">
                <span
                    v-if="link.type === 'gap'"
                    class="px-0.5 font-mono text-[11.5px] font-medium text-ink-950"
                    aria-hidden="true"
                >
                    …
                </span>
                <Link
                    v-else
                    :href="link.url!"
                    :aria-current="link.active ? 'page' : undefined"
                    class="grid h-8 min-w-[2rem] place-items-center rounded-lg px-1.5 font-mono text-[11.5px] transition duration-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-cyan"
                    :class="
                        link.active
                            ? 'bg-gradient-to-br from-[#7DEDF0] to-accent-cyan font-semibold text-[#061020] shadow-[0_0_20px_rgba(45,226,230,0.35)]'
                            : 'border border-[rgba(160,205,245,0.12)] font-medium text-ink-400 hover:border-accent-cyan/45 hover:text-glow-cyan hover:shadow-[0_0_16px_rgba(45,226,230,0.16)]'
                    "
                >
                    {{ link.page }}
                </Link>
            </template>

            <component
                :is="pagination.next_url ? Link : 'span'"
                :href="pagination.next_url ?? undefined"
                :class="[
                    ARROW,
                    pagination.next_url
                        ? 'cursor-pointer border-accent-violet/35 text-[#B5ABFC] hover:border-accent-violet/75 hover:shadow-[0_0_18px_rgba(139,124,255,0.22)]'
                        : 'cursor-not-allowed border-[rgba(160,205,245,0.10)] opacity-45',
                ]"
                :aria-disabled="pagination.next_url ? undefined : 'true'"
                aria-label="Next page"
            >
                <svg class="h-[13px] w-[13px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="m10 6 6 6-6 6" />
                </svg>
            </component>

            <div class="mx-1 h-[22px] w-px bg-[rgba(160,205,245,0.14)]" aria-hidden="true" />

            <p class="font-mono text-[11px] text-ink-700">{{ pagination.per_page }} / page</p>
        </div>
    </nav>
</template>
