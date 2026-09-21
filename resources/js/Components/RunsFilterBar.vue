<script setup lang="ts">
import type { RunsFilters, StatusChip, WorkflowOption } from '@/types';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    filters: RunsFilters;
    chips: StatusChip[];
    workflowOptions: WorkflowOption[];
    showing: { filtered: number; total: number };
}>();

const emit = defineEmits<{
    (e: 'change', patch: Record<string, string | number | null>): void;
    (e: 'clear'): void;
}>();

const datesOpen = ref(false);
const moreOpen = ref(false);
const from = ref(props.filters.from);
const to = ref(props.filters.to);

// The range can change without this popover being touched — "Widen to 90 days"
// on the empty state, or CLEAR — so the inputs follow the server's answer.
watch(
    () => [props.filters.from, props.filters.to] as const,
    ([nextFrom, nextTo]) => {
        from.value = nextFrom;
        to.value = nextTo;
    },
);

const CHIP_ACTIVE: Record<string, string> = {
    accent: 'bg-gradient-to-br from-[#7DEDF0] to-accent-cyan text-[#061020] shadow-[0_0_20px_rgba(45,226,230,0.35)]',
    completed: 'bg-gradient-to-br from-[#7FEBB0] to-status-completed text-[#061020] shadow-[0_0_20px_rgba(85,217,139,0.35)]',
    review: 'bg-gradient-to-br from-[#FBD887] to-status-review text-[#1A1205] shadow-[0_0_20px_rgba(248,198,93,0.35)]',
    critical: 'bg-gradient-to-br from-[#F79AA3] to-status-critical text-[#1C0508] shadow-[0_0_20px_rgba(242,108,120,0.35)]',
};

const CHIP_IDLE: Record<string, string> = {
    accent: 'border border-[rgba(160,205,245,0.18)] bg-white/[0.04] text-ink-400 hover:border-accent-cyan/50 hover:text-glow-cyan',
    completed: 'border border-status-completed/[0.28] bg-status-completed/10 text-glow-green hover:border-status-completed/[0.55] hover:bg-status-completed/20 hover:shadow-[0_0_18px_rgba(85,217,139,0.18)]',
    review: 'border border-status-review/30 bg-status-review/10 text-status-review hover:border-status-review/60 hover:bg-status-review/20 hover:shadow-[0_0_18px_rgba(248,198,93,0.18)]',
    critical: 'border border-status-critical/30 bg-status-critical/10 text-glow-red hover:border-status-critical/60 hover:bg-status-critical/20 hover:shadow-[0_0_18px_rgba(242,108,120,0.18)]',
};

const COUNT_IDLE: Record<string, string> = {
    accent: 'text-ink-700',
    completed: 'text-[#6F9A82]',
    review: 'text-[#A08A55]',
    critical: 'text-[#9E6B72]',
};

const selectedWorkflow = computed(
    () => props.workflowOptions.find((option) => option.id === props.filters.workflow) ?? null,
);

const applyDates = (): void => {
    datesOpen.value = false;
    emit('change', { from: from.value, to: to.value });
};
</script>

<template>
    <div
        class="glass-panel flex flex-wrap items-center gap-2.5 px-3.5 py-2.5"
    >
        <!-- Status chips -->
        <div class="flex flex-wrap gap-1.5" role="group" aria-label="Filter by status">
            <button
                v-for="chip in chips"
                :key="chip.key"
                type="button"
                :aria-pressed="filters.status === chip.key"
                class="flex items-center gap-[7px] rounded-lg px-3 py-1.5 font-mono text-[11px] font-medium tracking-[0.06em] transition duration-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-cyan focus-visible:ring-offset-2 focus-visible:ring-offset-navy-base"
                :class="
                    filters.status === chip.key
                        ? `font-semibold ${CHIP_ACTIVE[chip.tone]}`
                        : CHIP_IDLE[chip.tone]
                "
                @click="emit('change', { status: chip.key, page: null })"
            >
                {{ chip.label }}
                <span :class="filters.status === chip.key ? 'opacity-65' : COUNT_IDLE[chip.tone]">
                    {{ chip.count }}
                </span>
            </button>
        </div>

        <div class="mx-1 h-[26px] w-px bg-[rgba(160,205,245,0.14)]" aria-hidden="true" />

        <!-- Date range -->
        <div class="relative">
            <button
                type="button"
                :aria-expanded="datesOpen"
                class="flex h-[34px] items-center gap-2 rounded-lg border border-[rgba(160,205,245,0.12)] bg-[rgba(10,20,35,0.7)] px-3 transition duration-200 hover:border-accent-cyan/40 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-cyan"
                @click="datesOpen = !datesOpen"
            >
                <svg class="h-[13px] w-[13px] text-[#5E8AAE]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <rect x="3.5" y="5" width="17" height="15" rx="2.2" />
                    <path d="M3.5 10h17M8 3v4M16 3v4" />
                </svg>
                <span class="font-mono text-[11.5px] text-ink-300">{{ filters.range_label }}</span>
            </button>

            <div
                v-if="datesOpen"
                class="absolute left-0 top-[42px] z-30 w-64 rounded-xl border border-[rgba(160,205,245,0.16)] bg-panel-base/95 p-3 shadow-[0_24px_56px_rgba(2,8,18,0.6)] backdrop-blur-xl"
            >
                <label class="block font-mono text-[10px] uppercase tracking-[0.12em] text-ink-700" for="range-from">From</label>
                <input
                    id="range-from"
                    v-model="from"
                    type="date"
                    class="mt-1 w-full rounded-lg border-white/10 bg-white/[0.04] py-1.5 font-mono text-xs text-ink-200 focus:border-accent-cyan/50 focus:ring-2 focus:ring-accent-cyan/30"
                />
                <label class="mt-3 block font-mono text-[10px] uppercase tracking-[0.12em] text-ink-700" for="range-to">To</label>
                <input
                    id="range-to"
                    v-model="to"
                    type="date"
                    class="mt-1 w-full rounded-lg border-white/10 bg-white/[0.04] py-1.5 font-mono text-xs text-ink-200 focus:border-accent-cyan/50 focus:ring-2 focus:ring-accent-cyan/30"
                />
                <button
                    type="button"
                    class="mt-3 w-full rounded-lg border border-accent-cyan/45 py-2 font-mono text-[10.5px] font-semibold tracking-[0.06em] text-glow-cyan transition duration-200 hover:bg-accent-cyan/[0.14] hover:shadow-[0_0_20px_rgba(45,226,230,0.22)]"
                    @click="applyDates"
                >
                    APPLY RANGE
                </button>
            </div>
        </div>

        <!-- Workflow -->
        <div
            class="relative flex h-[34px] items-center gap-2 rounded-lg border border-accent-violet/35 bg-[rgba(10,20,35,0.7)] px-3 shadow-[0_0_18px_rgba(139,124,255,0.12)] transition duration-200 focus-within:border-accent-violet/70 hover:border-accent-violet/70"
        >
            <label for="workflow-filter" class="text-[11.5px] text-ink-600">Workflow</label>
            <select
                id="workflow-filter"
                class="cursor-pointer appearance-none border-0 bg-transparent bg-none py-0 pl-0 pr-5 text-[11.5px] font-medium text-glow-violet focus:ring-0"
                :value="filters.workflow ?? ''"
                @change="emit('change', { workflow: ($event.target as HTMLSelectElement).value || null, page: null })"
            >
                <option value="" class="bg-navy-raised">All workflows</option>
                <option
                    v-for="option in workflowOptions"
                    :key="option.id"
                    :value="option.id"
                    class="bg-navy-raised"
                >
                    {{ option.name }}
                </option>
            </select>
            <svg class="pointer-events-none absolute right-2.5 h-3 w-3 text-[#B5ABFC]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="m6 9 6 6 6-6" />
            </svg>
            <span class="sr-only">{{ selectedWorkflow?.name ?? 'All workflows' }}</span>
        </div>

        <!-- More filters (stub) -->
        <div class="relative">
            <button
                type="button"
                :aria-expanded="moreOpen"
                class="flex h-[34px] items-center gap-[7px] rounded-lg border border-[rgba(160,205,245,0.12)] px-3 transition duration-200 hover:border-[rgba(160,205,245,0.3)] focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-cyan"
                @click="moreOpen = !moreOpen"
            >
                <svg class="h-[13px] w-[13px] text-[#5E8AAE]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <path d="M4 6h16M7 12h10M10 18h4" />
                </svg>
                <span class="text-[11.5px] text-ink-500">More filters</span>
            </button>

            <div
                v-if="moreOpen"
                class="absolute left-0 top-[42px] z-30 w-60 rounded-xl border border-[rgba(160,205,245,0.16)] bg-panel-base/95 p-3 shadow-[0_24px_56px_rgba(2,8,18,0.6)] backdrop-blur-xl"
            >
                <p class="font-mono text-[10px] uppercase tracking-[0.12em] text-ink-700">Planned filters</p>
                <ul class="mt-2 space-y-1.5 text-[11.5px] text-ink-700">
                    <li>Agent handle</li>
                    <li>Cost range</li>
                    <li>Duration threshold</li>
                    <li>Token budget</li>
                </ul>
                <p class="mt-2.5 border-t border-white/10 pt-2 text-[10.5px] text-ink-800">
                    Arriving with the saved-views work.
                </p>
            </div>
        </div>

        <div class="ml-auto flex items-center gap-3">
            <p class="font-mono text-[11.5px] text-ink-600">
                Showing <span class="font-medium text-ink-100">{{ showing.filtered }}</span>
                of {{ showing.total }} run{{ showing.total === 1 ? '' : 's' }}
            </p>
            <button
                type="button"
                :disabled="filters.is_default"
                class="font-mono text-[11px] font-medium tracking-[0.04em] text-accent-cyan transition duration-200 hover:text-accent-violet focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-cyan disabled:cursor-not-allowed disabled:text-ink-950 disabled:hover:text-ink-950"
                @click="emit('clear')"
            >
                CLEAR
            </button>
        </div>
    </div>
</template>
