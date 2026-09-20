<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

type NavItem = {
    label: string;
    href?: string;
};

/** Order follows the mockup rail: grid, pulse, graph, shield, queue. */
const navItems: NavItem[] = [
    { label: 'Command Centre', href: '/dashboard' },
    { label: 'Runs', href: '/runs' },
    { label: 'Telemetry' },
    { label: 'Approvals' },
    { label: 'Review Queue', href: '/reviews' },
];

const page = usePage();

/** Active when the current URL is the item's route, a child of it, or it with a query string. */
const currentLabel = computed(() => {
    const url = page.url;

    return (
        navItems.find(
            (item) =>
                item.href !== undefined &&
                (url === item.href ||
                    url.startsWith(`${item.href}/`) ||
                    url.startsWith(`${item.href}?`)),
        )?.label ?? null
    );
});

const accountName = computed(() => page.props.auth?.user?.name ?? '');

/** "Mara Kessler" -> "MK"; a single name falls back to its first two letters. */
const initials = computed(() => {
    const parts = accountName.value.trim().split(/\s+/).filter(Boolean);

    if (parts.length === 0) {
        return '';
    }

    const letters =
        parts.length > 1
            ? `${parts[0][0]}${parts[parts.length - 1][0]}`
            : parts[0].slice(0, 2);

    return letters.toUpperCase();
});

const TILE =
    'relative flex h-11 w-11 cursor-pointer items-center justify-center rounded-xl border border-transparent text-ink-600 transition duration-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-cyan focus-visible:ring-offset-2 focus-visible:ring-offset-navy-base';

const LINKED =
    'hover:border-[#A0CDF5]/[0.16] hover:bg-[#A0CDF5]/[0.08] hover:text-ink-300';

/** Telemetry and Approvals have no route yet, so their hover stays deliberately weaker. */
const UNLINKED =
    'hover:border-[#A0CDF5]/[0.10] hover:bg-[#A0CDF5]/[0.04] hover:text-ink-500';

/** Mockup active tile: cyan wash, cyan hairline border and an inner cyan bloom. */
const ACTIVE =
    'border-accent-cyan/[0.32] bg-[linear-gradient(145deg,rgba(45,226,230,0.16),rgba(45,226,230,0.04))] text-accent-cyan shadow-[inset_0_0_18px_rgba(45,226,230,0.18)]';

const classesFor = (item: NavItem): string => {
    if (currentLabel.value === item.label) {
        return `${TILE} ${ACTIVE}`;
    }

    return `${TILE} ${item.href ? LINKED : UNLINKED}`;
};
</script>

<template>
    <div class="relative min-h-screen overflow-hidden bg-navy-base">
        <div
            class="pointer-events-none absolute inset-0"
            aria-hidden="true"
        >
            <div
                class="absolute -left-[180px] -top-[260px] h-[900px] w-[900px] rounded-full bg-[radial-gradient(circle,rgba(45,226,230,0.16)_0%,rgba(45,226,230,0.05)_38%,rgba(45,226,230,0)_68%)]"
            />
            <div
                class="absolute -bottom-[340px] -right-[200px] h-[1000px] w-[1000px] rounded-full bg-[radial-gradient(circle,rgba(139,124,255,0.18)_0%,rgba(139,124,255,0.05)_40%,rgba(139,124,255,0)_70%)]"
            />
            <div class="absolute inset-0 bg-dot-grid opacity-50" />
            <div
                class="absolute inset-0 bg-[linear-gradient(180deg,rgba(5,13,24,0)_60%,rgba(3,8,15,0.7)_100%)]"
            />
        </div>

        <a
            href="#main-content"
            class="sr-only focus:not-sr-only focus:absolute focus:left-24 focus:top-4 focus:z-50 focus:rounded-md focus:bg-navy-raised focus:px-3 focus:py-2 focus:text-sm focus:text-white focus:outline-none focus:ring-2 focus:ring-accent-cyan"
        >
            Skip to content
        </a>

        <aside
            class="fixed inset-y-0 left-0 z-20 flex w-[76px] flex-col items-center gap-2 border-r border-[#A0CDF5]/10 bg-[linear-gradient(180deg,rgba(23,38,60,0.72)_0%,rgba(12,22,38,0.55)_100%)] pb-5 pt-[22px] shadow-[24px_0_60px_rgba(0,0,0,0.45)] backdrop-blur-[22px]"
            aria-label="Primary"
        >
            <Link
                href="/dashboard"
                class="mb-4 grid h-[38px] w-[38px] cursor-pointer place-items-center rounded-[11px] border border-accent-cyan/45 bg-[linear-gradient(145deg,rgba(45,226,230,0.28),rgba(139,124,255,0.28))] shadow-[0_0_22px_rgba(45,226,230,0.35)] transition duration-200 hover:shadow-[0_0_28px_rgba(45,226,230,0.5)] focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-cyan focus-visible:ring-offset-2 focus-visible:ring-offset-navy-base"
                aria-label="SyntaVex home"
            >
                <svg
                    class="h-5 w-5"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="#BFF6F7"
                    stroke-width="1.6"
                    xmlns="http://www.w3.org/2000/svg"
                    aria-hidden="true"
                >
                    <path d="M12 2.5 21 7v10l-9 4.5L3 17V7z" />
                    <path d="M12 7.5 16.5 10v4L12 16.5 7.5 14v-4z" stroke="#8B7CFF" />
                </svg>
            </Link>

            <nav class="flex flex-col items-center gap-2" aria-label="Main">
                <component
                    :is="item.href ? Link : 'button'"
                    v-for="item in navItems"
                    :key="item.label"
                    :href="item.href"
                    :type="item.href ? undefined : 'button'"
                    :aria-label="item.label"
                    :aria-current="currentLabel === item.label ? 'page' : undefined"
                    :class="classesFor(item)"
                >
                    <span
                        v-if="currentLabel === item.label"
                        class="absolute -left-[14px] top-3 h-5 w-0.5 rounded-sm bg-accent-cyan shadow-[0_0_10px_#2DE2E6]"
                        aria-hidden="true"
                    />
                    <svg
                        v-if="item.label === 'Command Centre'"
                        class="h-[19px] w-[19px]"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.6"
                        aria-hidden="true"
                    >
                        <rect x="3" y="3" width="7.5" height="7.5" rx="1.6" />
                        <rect x="13.5" y="3" width="7.5" height="7.5" rx="1.6" />
                        <rect x="3" y="13.5" width="7.5" height="7.5" rx="1.6" />
                        <rect x="13.5" y="13.5" width="7.5" height="7.5" rx="1.6" />
                    </svg>
                    <svg
                        v-else-if="item.label === 'Runs'"
                        class="h-[19px] w-[19px]"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.6"
                        aria-hidden="true"
                    >
                        <path d="M3 12h4l2.5-6.5L14 18.5 16.5 12H21" />
                    </svg>
                    <svg
                        v-else-if="item.label === 'Telemetry'"
                        class="h-[19px] w-[19px]"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.6"
                        aria-hidden="true"
                    >
                        <circle cx="6" cy="6.5" r="2.6" />
                        <circle cx="18" cy="6.5" r="2.6" />
                        <circle cx="12" cy="17.5" r="2.6" />
                        <path d="M7.9 8.3 11 15.4M16.1 8.3 13 15.4M8.6 6.5h6.8" />
                    </svg>
                    <template v-else-if="item.label === 'Approvals'">
                        <svg
                            class="h-[19px] w-[19px]"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.6"
                            aria-hidden="true"
                        >
                            <path d="M12 3 20 6v6c0 4.4-3.3 7.8-8 9-4.7-1.2-8-4.6-8-9V6z" />
                            <path d="m9 12 2.2 2.2L15.5 10" />
                        </svg>
                        <span
                            class="absolute right-[7px] top-[7px] h-[7px] w-[7px] rounded-full bg-status-review shadow-[0_0_10px_#F8C65D]"
                            aria-hidden="true"
                        />
                    </template>
                    <svg
                        v-else
                        class="h-[19px] w-[19px]"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.6"
                        aria-hidden="true"
                    >
                        <rect x="3" y="4" width="18" height="16" rx="2.4" />
                        <path d="M3 9h18M8 4v16" />
                    </svg>
                </component>
            </nav>

            <div class="flex-1" />

            <button type="button" aria-label="Settings" :class="`${TILE} ${UNLINKED}`">
                <svg
                    class="h-[19px] w-[19px]"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.6"
                    aria-hidden="true"
                >
                    <circle cx="12" cy="12" r="3.2" />
                    <path
                        d="M12 2.8v2.6M12 18.6v2.6M21.2 12h-2.6M5.4 12H2.8M18.5 5.5l-1.8 1.8M7.3 16.7l-1.8 1.8M18.5 18.5l-1.8-1.8M7.3 7.3 5.5 5.5"
                    />
                </svg>
            </button>

            <Link
                href="/profile"
                class="grid h-[34px] w-[34px] cursor-pointer place-items-center rounded-full bg-[linear-gradient(145deg,#8B7CFF,#2DE2E6)] font-display text-xs font-semibold text-[#061020] shadow-[0_0_18px_rgba(139,124,255,0.4)] transition duration-200 hover:brightness-110 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-cyan focus-visible:ring-offset-2 focus-visible:ring-offset-navy-base"
                :aria-label="accountName ? `Profile — ${accountName}` : 'Profile'"
            >
                {{ initials }}
            </Link>
        </aside>

        <main
            id="main-content"
            class="relative z-10 min-h-screen pl-[76px]"
        >
            <slot />
        </main>
    </div>
</template>
