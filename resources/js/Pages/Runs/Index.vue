<script setup lang="ts">
import PageHeader from '@/Components/PageHeader.vue';
import RunsEmptyState from '@/Components/RunsEmptyState.vue';
import RunsFilterBar from '@/Components/RunsFilterBar.vue';
import RunsPagination from '@/Components/RunsPagination.vue';
import RunsTable from '@/Components/RunsTable.vue';
import RunVolumeChart from '@/Components/RunVolumeChart.vue';
import StatusDistributionStrip from '@/Components/StatusDistributionStrip.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { RunsExplorerProps } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps<RunsExplorerProps>();

const expanded = ref<number | null>(props.expandedRunId);
const search = ref(props.filters.search);
const searchInput = ref<HTMLInputElement | null>(null);

const isMac = typeof navigator !== 'undefined' && /Mac|iPhone|iPad|iPod/.test(navigator.userAgent);
const shortcutChip = isMac ? '⌘K' : 'Ctrl K';
const shortcutSpoken = isMac ? 'Command K' : 'Control K';

const focusSearch = (event: KeyboardEvent): void => {
    if (event.key.toLowerCase() !== 'k' || !(event.metaKey || event.ctrlKey)) {
        return;
    }

    event.preventDefault();
    searchInput.value?.focus();
    searchInput.value?.select();
};

onMounted(() => window.addEventListener('keydown', focusSearch));
onUnmounted(() => window.removeEventListener('keydown', focusSearch));

watch(
    () => props.expandedRunId,
    (value) => {
        expanded.value = value;
    },
);

watch(
    () => props.filters.search,
    (value) => {
        search.value = value;
    },
);

type FilterPatch = Record<string, string | number | null>;

const buildQuery = (patch: FilterPatch = {}): Record<string, string> => {
    const merged: FilterPatch = {
        status: props.filters.status,
        workflow: props.filters.workflow,
        search: props.filters.search,
        from: props.filters.from,
        to: props.filters.to,
        ...patch,
    };

    const query: Record<string, string> = {};

    if (merged.status && merged.status !== 'all') query.status = String(merged.status);
    if (merged.workflow) query.workflow = String(merged.workflow);
    if (merged.search) query.search = String(merged.search);
    if (merged.from) query.from = String(merged.from);
    if (merged.to) query.to = String(merged.to);

    return query;
};

const applyFilters = (patch: FilterPatch = {}): void => {
    router.get('/runs', buildQuery(patch), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const clearFilters = (): void => {
    router.get('/runs', {}, { preserveScroll: true, replace: true });
};

const widenRange = (): void => {
    const to = new Date(props.filters.to);
    const from = new Date(to);
    from.setDate(from.getDate() - 89);

    applyFilters({ from: from.toISOString().slice(0, 10), to: to.toISOString().slice(0, 10) });
};

let searchTimer: ReturnType<typeof setTimeout> | undefined;

watch(search, (value) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        if (value !== props.filters.search) {
            applyFilters({ search: value });
        }
    }, 300);
});

const exportHref = computed(() => {
    const query = new URLSearchParams(buildQuery()).toString();

    return query ? `/runs/export?${query}` : '/runs/export';
});

const toggleRow = (id: number): void => {
    expanded.value = expanded.value === id ? null : id;
};
</script>

<template>
    <Head title="Runs Explorer" />

    <AppLayout>
        <PageHeader
            sticky
            tone="accent"
            title="Runs Explorer"
            :meta="`workspace / ${workspace.slug} · ${workspace.tier.toLowerCase()} / runs`"
        >
            <template #badges>
                <span
                    class="rounded border border-accent-cyan/35 px-1.5 py-[3px] font-mono text-[10px] font-medium tracking-[0.08em] text-accent-cyan"
                >
                    DIRECTORY
                </span>
            </template>

            <template #actions>
                <div
                    class="flex h-9 w-full min-w-[15rem] max-w-[26rem] flex-1 items-center gap-2.5 rounded-[9px] border border-accent-cyan/35 bg-[rgba(10,20,35,0.75)] px-3.5 shadow-[0_0_22px_rgba(45,226,230,0.10)] focus-within:border-accent-cyan/70"
                >
                    <svg class="h-3.5 w-3.5 shrink-0 text-accent-cyan" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <circle cx="11" cy="11" r="7" />
                        <path d="m16.5 16.5 4 4" />
                    </svg>
                    <span class="hidden shrink-0 font-mono text-xs text-glow-blue sm:inline" aria-hidden="true">trace:</span>
                    <label for="runs-search" class="sr-only">
                        Search by trace ID or workflow name. Shortcut: {{ shortcutSpoken }}
                    </label>
                    <input
                        id="runs-search"
                        ref="searchInput"
                        v-model="search"
                        type="search"
                        placeholder="search by trace ID or workflow name…"
                        class="min-w-0 flex-1 border-0 bg-transparent p-0 font-mono text-xs text-ink-100 placeholder:text-[#55697F] focus:ring-0"
                    />
                    <kbd
                        class="hidden shrink-0 rounded border border-[rgba(160,205,245,0.14)] px-[5px] py-0.5 font-mono text-[10px] font-medium text-ink-950 sm:inline-block"
                        aria-hidden="true"
                    >
                        {{ shortcutChip }}
                    </kbd>
                </div>

                <a
                    :href="exportHref"
                    class="flex h-9 shrink-0 items-center gap-2 rounded-[9px] border border-[rgba(160,205,245,0.12)] bg-[rgba(10,20,35,0.7)] px-3.5 text-xs font-medium text-ink-400 transition duration-200 hover:border-accent-cyan/45 hover:text-glow-cyan focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-cyan"
                >
                    <svg class="h-[13px] w-[13px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path d="M12 3v12M7.5 10.5 12 15l4.5-4.5M4 20h16" />
                    </svg>
                    Export
                </a>
            </template>
        </PageHeader>

        <div class="flex flex-col gap-[18px] px-8 pb-8 pt-[22px]">
            <div class="grid gap-[18px] lg:grid-cols-[minmax(0,1fr)_300px]">
                <StatusDistributionStrip :distribution="distribution" />
                <RunVolumeChart :volume="volume" />
            </div>

            <RunsFilterBar
                :filters="filters"
                :chips="statusChips"
                :workflow-options="workflowOptions"
                :showing="showing"
                @change="applyFilters"
                @clear="clearFilters"
            />

            <RunsTable
                v-if="runs.length > 0"
                :runs="runs"
                :expanded-id="expanded"
                @toggle="toggleRow"
            >
                <template #footer>
                    <RunsPagination :pagination="pagination" />
                </template>
            </RunsTable>

            <div
                v-else
                class="glass-panel glass-panel-solid"
            >
                <RunsEmptyState
                    :filters="filters"
                    :workflow-options="workflowOptions"
                    @clear="clearFilters"
                    @widen="widenRange"
                />
            </div>
        </div>
    </AppLayout>
</template>
