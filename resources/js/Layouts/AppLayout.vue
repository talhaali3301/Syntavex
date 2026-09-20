<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

type NavItem = {
    label: string;
    href?: string;
};

const navItems: NavItem[] = [
    { label: 'Command Centre', href: '/dashboard' },
    { label: 'Runs', href: '/runs' },
    { label: 'Approvals' },
    { label: 'Telemetry' },
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

const LINKED =
    'flex h-11 w-11 cursor-pointer items-center justify-center rounded-xl border border-transparent text-white/55 transition duration-200 hover:border-white/15 hover:bg-white/10 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-cyan focus-visible:ring-offset-2 focus-visible:ring-offset-navy-base';

const UNLINKED =
    'flex h-11 w-11 cursor-pointer items-center justify-center rounded-xl border border-transparent text-white/40 transition duration-200 hover:border-white/10 hover:bg-white/5 hover:text-white/70 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-cyan focus-visible:ring-offset-2 focus-visible:ring-offset-navy-base';

const ACTIVE = 'border-white/15 bg-white/10 text-accent-cyan';

const classesFor = (item: NavItem): string => {
    const base = item.href ? LINKED : UNLINKED;

    return currentLabel.value === item.label ? `${base} ${ACTIVE}` : base;
};
</script>

<template>
    <div class="relative min-h-screen overflow-hidden bg-navy-base">
        <div
            class="pointer-events-none absolute inset-0"
            aria-hidden="true"
        >
            <div
                class="absolute -left-24 -top-32 h-[34rem] w-[34rem] rounded-full bg-accent-cyan/[0.16] blur-[140px]"
            />
            <div
                class="absolute -bottom-40 -right-24 h-[38rem] w-[38rem] rounded-full bg-accent-violet/[0.18] blur-[160px]"
            />
            <div class="absolute inset-0 bg-dot-grid opacity-50" />
        </div>

        <a
            href="#main-content"
            class="sr-only focus:not-sr-only focus:absolute focus:left-24 focus:top-4 focus:z-50 focus:rounded-md focus:bg-navy-raised focus:px-3 focus:py-2 focus:text-sm focus:text-white focus:outline-none focus:ring-2 focus:ring-accent-cyan"
        >
            Skip to content
        </a>

        <aside
            class="fixed inset-y-0 left-0 z-20 flex w-[4.5rem] flex-col items-center border-r border-white/10 bg-navy-raised/55 py-5 backdrop-blur-xl"
            aria-label="Primary"
        >
            <Link
                href="/dashboard"
                class="mb-8 flex h-11 w-11 cursor-pointer items-center justify-center rounded-xl border border-accent-cyan/30 bg-accent-cyan/10 text-accent-cyan transition duration-200 hover:border-accent-cyan/60 hover:bg-accent-cyan/20 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-cyan focus-visible:ring-offset-2 focus-visible:ring-offset-navy-base"
                aria-label="SyntaVex home"
            >
                <svg
                    class="h-5 w-5"
                    viewBox="0 0 24 24"
                    fill="none"
                    xmlns="http://www.w3.org/2000/svg"
                    aria-hidden="true"
                >
                    <path
                        d="M12 3L20 7.5V16.5L12 21L4 16.5V7.5L12 3Z"
                        stroke="currentColor"
                        stroke-width="1.6"
                    />
                    <path
                        d="M12 8L16 10.25V14.75L12 17L8 14.75V10.25L12 8Z"
                        fill="currentColor"
                    />
                </svg>
            </Link>

            <nav class="flex flex-1 flex-col items-center gap-3" aria-label="Main">
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
                    <svg
                        v-if="item.label === 'Command Centre'"
                        class="h-5 w-5"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.7"
                        aria-hidden="true"
                    >
                        <rect x="3.5" y="3.5" width="7" height="7" rx="1.5" />
                        <rect x="13.5" y="3.5" width="7" height="7" rx="1.5" />
                        <rect x="3.5" y="13.5" width="7" height="7" rx="1.5" />
                        <rect x="13.5" y="13.5" width="7" height="7" rx="1.5" />
                    </svg>
                    <svg
                        v-else-if="item.label === 'Runs'"
                        class="h-5 w-5"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.7"
                        aria-hidden="true"
                    >
                        <path d="M5 12h14" />
                        <path d="M13 6l6 6-6 6" />
                    </svg>
                    <svg
                        v-else-if="item.label === 'Approvals'"
                        class="h-5 w-5"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.7"
                        aria-hidden="true"
                    >
                        <path d="M12 3l7 4v5c0 4.5-3 8.2-7 9.5C8 20.2 5 16.5 5 12V7l7-4Z" />
                        <path d="M9 12l2 2 4-4" />
                    </svg>
                    <svg
                        v-else
                        class="h-5 w-5"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.7"
                        aria-hidden="true"
                    >
                        <path d="M4 19V5" />
                        <path d="M4 16c3-4 6-2 8.5-6S18 6 20 9v10" />
                    </svg>
                </component>
            </nav>
        </aside>

        <main
            id="main-content"
            class="relative z-10 min-h-screen pl-[4.5rem]"
        >
            <slot />
        </main>
    </div>
</template>
