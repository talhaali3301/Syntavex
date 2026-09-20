<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    run: {
        id: number;
        run_key: string;
        workflow: string | null;
        status: string;
    };
}>();
</script>

<template>
    <Head :title="`Run #${run.run_key}`" />

    <AppLayout>
        <div class="flex min-h-screen items-center justify-center px-8 py-16">
            <div
                class="max-w-lg rounded-2xl border border-[rgba(160,205,245,0.14)] bg-gradient-to-br from-[rgba(40,58,86,0.38)] to-[rgba(12,22,38,0.55)] px-10 py-9 text-center shadow-[0_24px_56px_rgba(2,8,18,0.6)] backdrop-blur-xl"
            >
                <p class="font-mono text-[10px] font-semibold uppercase tracking-[0.14em] text-ink-700">
                    Run Inspector
                </p>
                <h1 class="mt-2 font-display text-2xl font-semibold text-ink-100">
                    #{{ run.run_key }}
                </h1>
                <p class="mt-1 font-mono text-xs text-ink-500">
                    {{ run.workflow }} · {{ run.status.replace('_', ' ') }}
                </p>
                <p class="mt-4 text-[12.5px] leading-relaxed text-ink-500">
                    The full inspector — step timeline, payload diffs and the decision trail —
                    lands in Phase 5. This route exists so the link from the Runs Explorer
                    resolves today.
                </p>
                <Link
                    href="/runs"
                    class="mt-6 inline-block rounded-lg border border-accent-cyan/45 px-4 py-2.5 font-mono text-[10.5px] font-semibold tracking-[0.06em] text-glow-cyan transition duration-200 hover:bg-accent-cyan/[0.14] hover:shadow-[0_0_20px_rgba(45,226,230,0.22)]"
                >
                    ← BACK TO RUNS
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
