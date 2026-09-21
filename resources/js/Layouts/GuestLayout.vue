<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="relative flex min-h-screen flex-col items-center justify-center overflow-hidden bg-navy-base px-5 py-12">
        <div class="pointer-events-none absolute inset-0" aria-hidden="true">
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

        <div class="relative flex w-full max-w-[26rem] flex-col items-center gap-7">
            <Link
                href="/"
                class="flex items-center gap-3 rounded-xl px-1 py-1 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-cyan focus-visible:ring-offset-2 focus-visible:ring-offset-navy-base"
                aria-label="SyntaVex home"
            >
                <span
                    class="grid h-[38px] w-[38px] place-items-center rounded-[11px] border border-accent-cyan/45 bg-[linear-gradient(145deg,rgba(45,226,230,0.28),rgba(139,124,255,0.28))] shadow-[0_0_22px_rgba(45,226,230,0.35)]"
                >
                    <svg
                        class="h-5 w-5"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="#BFF6F7"
                        stroke-width="1.6"
                        aria-hidden="true"
                    >
                        <path d="M12 2.5 21 7v10l-9 4.5L3 17V7z" />
                        <path d="M12 7.5 16.5 10v4L12 16.5 7.5 14v-4z" stroke="#8B7CFF" />
                    </svg>
                </span>
                <span class="font-display text-lg font-semibold tracking-tight text-gradient-cyan">
                    SyntaVex
                </span>
            </Link>

            <div class="glass-panel w-full p-7">
                <div
                    class="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r from-accent-cyan/70 via-accent-violet/40 to-transparent"
                    aria-hidden="true"
                />
                <div
                    class="pointer-events-none absolute -right-20 -top-20 h-56 w-56 rounded-full bg-accent-violet/15 blur-[80px]"
                    aria-hidden="true"
                />

                <div class="relative">
                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>
