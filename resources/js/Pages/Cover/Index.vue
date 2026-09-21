<script setup lang="ts">
import FleetPulsePill from '@/Components/FleetPulsePill.vue';
import KpiStatCard from '@/Components/KpiStatCard.vue';
import OrbitalConstellation from '@/Components/OrbitalConstellation.vue';
import PageHeader from '@/Components/PageHeader.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { CoverProps } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<CoverProps>();

const workspaceLabel = computed(
    () => props.workspace?.name ?? 'No workspace provisioned',
);

const crumb = computed(() =>
    props.workspace
        ? `workspace / ${props.workspace.slug} · ${props.workspace.tier.toLowerCase()}`
        : 'workspace / unprovisioned',
);

const signature = computed(() => [
    {
        label: 'Latest trace',
        value: props.pulse.latest_run_key ? `#${props.pulse.latest_run_key}` : '—',
    },
    { label: 'Autonomy', value: props.pulse.autonomy_display },
    { label: 'Tokens reasoned', value: props.pulse.tokens_display },
    { label: 'Fleet spend', value: props.pulse.spend_display },
]);
</script>

<template>
    <Head title="SyntaVex" />

    <AppLayout>
        <div class="flex min-h-screen flex-col">
            <PageHeader tone="violet" :title="workspaceLabel" :meta="crumb">
                <template #badges>
                    <span
                        class="rounded border border-accent-violet/40 px-1.5 py-[3px] font-mono text-[10px] font-medium tracking-[0.08em] text-accent-violet"
                    >
                        COVER
                    </span>
                </template>

                <template #actions>
                    <FleetPulsePill :pulse="pulse" />

                    <Link
                        href="/dashboard"
                        class="flex h-9 shrink-0 items-center gap-2 rounded-[9px] border border-accent-cyan/40 bg-accent-cyan/[0.12] px-4 text-xs font-semibold text-glow-cyan transition duration-200 hover:border-accent-cyan/70 hover:shadow-[0_0_22px_rgba(45,226,230,0.25)] focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-cyan"
                    >
                        Enter Command Centre
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                            <path d="M4 12h15M13.5 6.5 19.5 12l-6 5.5" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </Link>
                </template>
            </PageHeader>

            <div class="flex flex-1 flex-col gap-[18px] px-8 pb-8 pt-[22px]">
                <div class="grid grid-cols-12 gap-[18px]">
                    <section
                        class="glass-panel col-span-12 flex flex-col justify-between gap-8 p-7 lg:col-span-7 xl:p-9"
                        aria-labelledby="cover-heading"
                    >
                        <div
                            class="pointer-events-none absolute -left-16 -top-24 h-72 w-72 rounded-full bg-accent-violet/20 blur-[90px]"
                            aria-hidden="true"
                        />
                        <div
                            class="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r from-accent-cyan/70 via-accent-violet/40 to-transparent"
                            aria-hidden="true"
                        />

                        <div class="relative">
                            <p class="panel-eyebrow">Agent governance platform</p>

                            <h2
                                id="cover-heading"
                                class="mt-4 max-w-[19ch] font-display text-[2.6rem] font-semibold leading-[1.06] tracking-[-0.03em] text-gradient-cyan sm:text-[3.1rem]"
                            >
                                Every agent decision, on the record.
                            </h2>

                            <p class="mt-5 max-w-[52ch] text-[15px] leading-relaxed text-ink-400">
                                SyntaVex traces autonomous workflows end to end, capturing reasoning,
                                tool calls and policy gates. Irreversible actions are held at the
                                boundary until a human signs them off.
                            </p>

                            <div class="mt-7 flex flex-wrap items-center gap-3">
                                <Link
                                    href="/dashboard"
                                    class="flex h-11 items-center gap-2.5 rounded-xl border border-accent-cyan/45 bg-[linear-gradient(145deg,rgba(45,226,230,0.22),rgba(139,124,255,0.20))] px-5 text-sm font-semibold text-[#DFFAFB] shadow-[0_0_26px_rgba(45,226,230,0.22)] transition duration-200 hover:brightness-110 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-cyan focus-visible:ring-offset-2 focus-visible:ring-offset-navy-base"
                                >
                                    Open Command Centre
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <path d="M4 12h15M13.5 6.5 19.5 12l-6 5.5" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </Link>

                                <Link
                                    href="/reviews"
                                    class="flex h-11 items-center rounded-xl border border-[rgba(160,205,245,0.16)] bg-white/[0.03] px-5 text-sm font-medium text-ink-300 transition duration-200 hover:border-accent-cyan/40 hover:text-glow-cyan focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-cyan focus-visible:ring-offset-2 focus-visible:ring-offset-navy-base"
                                >
                                    Review Queue
                                </Link>
                            </div>
                        </div>

                        <dl
                            class="relative grid grid-cols-2 gap-x-6 gap-y-4 border-t border-[rgba(160,205,245,0.10)] pt-6 xl:grid-cols-4"
                        >
                            <div v-for="item in signature" :key="item.label" class="min-w-0">
                                <dt class="panel-eyebrow">{{ item.label }}</dt>
                                <dd class="mt-1.5 truncate font-mono text-base text-ink-200">
                                    {{ item.value }}
                                </dd>
                            </div>
                        </dl>
                    </section>

                    <section
                        class="glass-panel col-span-12 flex flex-col p-6 lg:col-span-5"
                        aria-labelledby="cover-graph-heading"
                    >
                        <div
                            class="pointer-events-none absolute -right-20 -top-20 h-64 w-64 rounded-full bg-accent-cyan/15 blur-[90px]"
                            aria-hidden="true"
                        />

                        <div class="relative flex items-start justify-between gap-3">
                            <div>
                                <h2 id="cover-graph-heading" class="panel-heading">Decision Graph</h2>
                                <p class="panel-note mt-0.5">
                                    {{ workspace?.active_workflow_count ?? 0 }} of
                                    {{ workspace?.workflow_count ?? 0 }} workflows live
                                </p>
                            </div>
                            <span
                                class="shrink-0 rounded-full border border-[rgba(160,205,245,0.14)] bg-white/5 px-2.5 py-1 font-mono text-[10px] uppercase tracking-[0.12em] text-ink-600"
                            >
                                Orbit
                            </span>
                        </div>

                        <div class="relative mt-2 flex flex-1 items-center justify-center">
                            <OrbitalConstellation :constellation="constellation" class="mx-auto max-w-[34rem]" />
                        </div>

                        <p class="relative mt-1 text-center font-mono text-[11px] text-ink-800">
                            {{ constellation.caption }}
                        </p>
                    </section>
                </div>

                <div class="grid grid-cols-1 gap-[18px] sm:grid-cols-2 lg:grid-cols-4">
                    <KpiStatCard
                        v-for="signal in signals"
                        :key="signal.key"
                        :label="signal.label"
                        :value="signal.display"
                        :caption="signal.caption"
                        :accent="signal.accent"
                    />
                </div>

                <div class="grid grid-cols-1 gap-[18px] md:grid-cols-3">
                    <Link
                        v-for="entry in entries"
                        :key="entry.key"
                        :href="entry.href"
                        class="glass-panel group flex flex-col gap-2 p-5 transition duration-300 hover:border-accent-cyan/35 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-cyan focus-visible:ring-offset-2 focus-visible:ring-offset-navy-base"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="panel-heading">{{ entry.label }}</h3>
                            <svg
                                class="h-4 w-4 shrink-0 text-ink-800 transition duration-300 group-hover:translate-x-0.5 group-hover:text-accent-cyan"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                                aria-hidden="true"
                            >
                                <path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </div>
                        <p class="text-sm leading-relaxed text-ink-600">{{ entry.description }}</p>
                        <p class="mt-auto pt-2 font-mono text-[11px] text-ink-800">{{ entry.meta }}</p>
                    </Link>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
