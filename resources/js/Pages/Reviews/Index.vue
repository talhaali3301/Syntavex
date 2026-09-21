<script setup lang="ts">
import FreezeFrameCard from '@/Components/FreezeFrameCard.vue';
import PageHeader from '@/Components/PageHeader.vue';
import QueueConstellation from '@/Components/QueueConstellation.vue';
import ReviewEmptyState from '@/Components/ReviewEmptyState.vue';
import ReviewQueueRow from '@/Components/ReviewQueueRow.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { ReviewQueueProps, StatusTone } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<ReviewQueueProps>();

const DOT: Record<StatusTone, string> = {
    completed: 'bg-status-completed',
    critical: 'bg-status-critical',
    review: 'bg-status-review',
    info: 'bg-status-info',
};

const expandedId = ref<number | null>(null);
const focusMode = ref(false);

const toggle = (id: number): void => {
    expandedId.value = expandedId.value === id ? null : id;
};

/** Focus mode strips the desk back to the decisions that cannot be undone. */
const visible = computed(() =>
    focusMode.value ? props.queue.filter((item) => item.frozen) : props.queue,
);

const decided = computed(() => props.stats.approved + props.stats.rejected);

const pulse = computed(() => {
    const days = props.stats.decisions;
    const peak = Math.max(1, ...days.map((day) => day.approved + day.rejected));
    const step = days.length > 1 ? 100 / (days.length - 1) : 0;

    return {
        path: days
            .map(
                (day, index) =>
                    `${index === 0 ? 'M' : 'L'} ${(index * step).toFixed(2)} ${(
                        26 - ((day.approved + day.rejected) / peak) * 22
                    ).toFixed(2)}`,
            )
            .join(' '),
        rejections: days
            .map((day, index) => ({ ...day, x: index * step }))
            .filter((day) => day.rejected > 0),
    };
});
</script>

<template>
    <Head title="Review Queue" />

    <AppLayout>
        <div class="flex min-h-screen flex-col">
            <PageHeader tone="critical" title="Review Queue">
                <template #badges>
                    <span
                        v-if="stats.pending"
                        class="flex items-center gap-[7px] rounded-full border border-status-critical/45 bg-status-critical/[0.13] px-[11px] py-[5px] font-mono text-[10px] font-semibold tracking-[0.1em] text-[#FFC3C9]"
                    >
                        <span
                            class="pulse-breathe h-[7px] w-[7px] rounded-full bg-status-critical shadow-[0_0_10px_#F26C78]"
                            aria-hidden="true"
                        />
                        {{ stats.pending }} AWAITING REVIEW · {{ stats.critical }} CRITICAL
                    </span>
                    <span
                        v-else
                        class="flex items-center gap-[7px] rounded-full border border-status-completed/45 bg-status-completed/[0.13] px-[11px] py-[5px] font-mono text-[10px] font-semibold tracking-[0.1em] text-glow-green"
                    >
                        <span class="h-[7px] w-[7px] rounded-full bg-status-completed" aria-hidden="true" />
                        QUEUE CLEAR
                    </span>
                </template>

                <template #meta>
                    human-in-the-loop desk
                    <template v-if="stats.oldest_wait_label">
                        · oldest item waiting
                        <span class="text-ink-400">{{ stats.oldest_wait_label }}</span>
                    </template>
                    · {{ stats.sla_label }}
                </template>

                <template #actions>
                    <button
                        type="button"
                        class="rounded-lg border px-[13px] py-2 font-mono text-[10.5px] font-semibold tracking-[0.06em] transition duration-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-cyan"
                        :class="
                            focusMode
                                ? 'border-accent-cyan/60 bg-accent-cyan/[0.14] text-glow-cyan shadow-[0_0_20px_rgba(45,226,230,0.2)]'
                                : 'border-[rgba(160,205,245,0.16)] text-ink-500 hover:border-[rgba(160,205,245,0.35)] hover:text-ink-100'
                        "
                        :aria-pressed="focusMode"
                        @click="focusMode = !focusMode"
                    >
                        FOCUS MODE
                    </button>
                </template>
            </PageHeader>

            <div class="grid flex-1 grid-cols-1 gap-[18px] px-8 pb-8 pt-5 xl:grid-cols-[minmax(0,1fr)_310px]">
                <div class="flex min-w-0 flex-col gap-[18px]">
                    <section
                        class="glass-panel flex flex-wrap items-center gap-x-7 gap-y-4 px-[22px] py-4"
                        aria-label="Desk throughput"
                    >
                        <div v-for="stat in [
                            { label: 'PENDING', value: String(stats.pending) },
                            { label: 'AVG WAIT', value: stats.average_wait_label },
                            { label: `INTERCEPTED ${stats.window_label}`, value: String(stats.intercepted) },
                        ]" :key="stat.label" class="flex flex-col gap-[3px]">
                            <span class="panel-eyebrow">
                                {{ stat.label }}
                            </span>
                            <span class="font-display text-[21px] font-semibold leading-none text-ink-100">
                                {{ stat.value }}
                            </span>
                        </div>

                        <div class="min-w-[220px] flex-1">
                            <div class="flex flex-wrap items-center gap-x-3.5 gap-y-1">
                                <span class="panel-eyebrow">
                                    DECISIONS · {{ stats.window_label }}
                                </span>
                                <span class="flex items-center gap-1.5 font-mono text-[10px] text-glow-green">
                                    <span class="h-1.5 w-1.5 rounded-full bg-status-completed" aria-hidden="true" />
                                    {{ stats.approved }} approved
                                </span>
                                <span class="flex items-center gap-1.5 font-mono text-[10px] text-glow-red">
                                    <span class="h-1.5 w-1.5 rounded-full bg-status-critical" aria-hidden="true" />
                                    {{ stats.rejected }} rejected
                                </span>
                            </div>

                            <p v-if="!decided" class="mt-2 font-mono text-[10px] text-ink-800">
                                no decisions recorded in the last {{ stats.window_label }}
                            </p>

                            <svg
                                v-else
                                viewBox="0 0 100 30"
                                preserveAspectRatio="none"
                                class="mt-2 h-[30px] w-full"
                                role="img"
                                :aria-label="`${decided} decisions recorded in the last ${stats.window_label}`"
                            >
                                <path :d="pulse.path" fill="none" stroke="#2DE2E6" stroke-width="1.1" vector-effect="non-scaling-stroke" />
                                <line
                                    v-for="day in pulse.rejections"
                                    :key="day.date"
                                    :x1="day.x"
                                    y1="2"
                                    :x2="day.x"
                                    y2="28"
                                    stroke="#F26C78"
                                    stroke-width="1"
                                    vector-effect="non-scaling-stroke"
                                />
                            </svg>
                        </div>
                    </section>

                    <template v-if="queue.length">
                        <template v-for="item in visible" :key="item.id">
                            <FreezeFrameCard
                                v-if="item.frozen"
                                :item="item"
                                :expanded="expandedId === item.id"
                                @toggle="toggle(item.id)"
                            />
                            <ReviewQueueRow
                                v-else
                                :item="item"
                                :expanded="expandedId === item.id"
                                @toggle="toggle(item.id)"
                            />
                        </template>

                        <p
                            v-if="focusMode && visible.length < queue.length"
                            class="rounded-xl border border-dashed border-[rgba(160,205,245,0.16)] bg-[rgba(10,20,35,0.6)] px-4 py-3 font-mono text-[10.5px] text-ink-700"
                        >
                            {{ queue.length - visible.length }} reversible item{{
                                queue.length - visible.length === 1 ? '' : 's'
                            }}
                            hidden by focus mode
                        </p>

                        <p
                            v-else
                            class="flex items-center gap-[9px] rounded-xl border border-[rgba(160,205,245,0.10)] bg-[rgba(10,20,35,0.55)] px-4 py-3 font-mono text-[10.5px] text-ink-800"
                        >
                            <svg class="h-3 w-3 shrink-0" viewBox="0 0 24 24" fill="none" stroke="#55D98B" stroke-width="2" aria-hidden="true">
                                <path d="m4 12 5 5L20 6" />
                            </svg>
                            Queue clear beyond this point · {{ decided }} item{{
                                decided === 1 ? '' : 's'
                            }}
                            resolved in the last {{ stats.window_label }}
                        </p>
                    </template>

                    <ReviewEmptyState v-else :stats="stats" />
                </div>

                <aside class="flex min-w-0 flex-col gap-3.5">
                    <QueueConstellation v-if="queue.length" :constellation="constellation" />

                    <section
                        class="glass-panel px-4 py-3.5"
                        aria-labelledby="resolved-heading"
                    >
                        <h2 id="resolved-heading" class="panel-eyebrow">
                            RECENTLY RESOLVED
                        </h2>

                        <ul v-if="resolved.length" class="mt-2.5 flex flex-col gap-0.5">
                            <li v-for="entry in resolved" :key="entry.id">
                                <Link
                                    :href="`/runs/${entry.run_id}`"
                                    class="flex items-center gap-[9px] rounded-[9px] px-[9px] py-2 transition duration-150 hover:bg-accent-cyan/[0.08] focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-cyan"
                                >
                                    <span
                                        class="h-1.5 w-1.5 flex-none rounded-full"
                                        :class="DOT[entry.tone]"
                                        aria-hidden="true"
                                    />
                                    <span class="flex-none font-mono text-[11px] font-medium text-glow-blue">
                                        #{{ entry.run_key }}
                                    </span>
                                    <span class="min-w-0 flex-1 truncate text-[11px] text-ink-500">
                                        {{ entry.workflow }} · {{ entry.status_label }}
                                    </span>
                                    <span class="flex-none font-mono text-[10px] text-ink-900">
                                        {{ entry.by }}
                                    </span>
                                </Link>
                            </li>
                        </ul>

                        <p v-else class="mt-2.5 font-mono text-[10.5px] text-ink-800">
                            Nothing has been signed off yet.
                        </p>
                    </section>

                    <section
                        class="glass-panel px-4 py-3.5"
                        aria-labelledby="reviewers-heading"
                    >
                        <h2 id="reviewers-heading" class="panel-eyebrow">
                            REVIEWER LOAD
                        </h2>

                        <ul class="mt-2.5 flex flex-col gap-2.5">
                            <li v-for="row in reviewers.rows" :key="row.name" class="flex flex-col gap-1.5">
                                <div class="flex items-baseline gap-2">
                                    <span class="min-w-0 truncate text-[11.5px] text-ink-400">
                                        {{ row.name }}
                                        <span v-if="row.is_you" class="font-mono text-[10px] text-glow-cyan">· you</span>
                                    </span>
                                    <div class="flex-1" />
                                    <span class="flex-none font-mono text-[11px] font-medium text-ink-200">
                                        {{ row.decisions }}
                                    </span>
                                </div>
                                <div class="h-[5px] overflow-hidden rounded bg-[rgba(150,195,235,0.12)]">
                                    <div
                                        class="h-full rounded bg-[linear-gradient(90deg,#2DE2E6,#8B7CFF)]"
                                        :style="{ width: `${(row.decisions / reviewers.max) * 100}%` }"
                                    />
                                </div>
                            </li>
                        </ul>

                        <p class="mt-3 font-mono text-[10px] text-ink-800">
                            {{ reviewers.unassigned_label }}
                        </p>
                    </section>
                </aside>
            </div>
        </div>
    </AppLayout>
</template>
