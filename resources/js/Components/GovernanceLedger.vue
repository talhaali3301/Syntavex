<script setup lang="ts">
import type { GovernanceLedgerData, GovernanceLedgerEntry } from '@/types';
import { computed } from 'vue';

const props = defineProps<{
    ledger: GovernanceLedgerData;
}>();

const rows = computed<GovernanceLedgerEntry[]>(() => [
    props.ledger.decisions_signed,
    props.ledger.policies_evaluated,
    props.ledger.audit_export,
]);

const isReady = computed(() => props.ledger.audit_export.value === 'ready');
</script>

<template>
    <section class="glass-panel p-5" aria-labelledby="governance-ledger-heading">
        <div
            class="pointer-events-none absolute -bottom-14 -right-10 h-32 w-32 rounded-full bg-accent-violet/20 blur-3xl"
            aria-hidden="true"
        />

        <div class="relative flex items-center justify-between gap-2">
            <h2 id="governance-ledger-heading" class="panel-heading">Governance Ledger</h2>
            <span
                class="flex items-center gap-1.5 rounded-full border border-status-completed/35 bg-status-completed/10 px-2 py-0.5 text-[0.6rem] font-semibold uppercase tracking-[0.14em] text-status-completed"
            >
                <span
                    v-if="isReady"
                    class="h-1.5 w-1.5 rounded-full bg-status-completed pulse-breathe"
                    aria-hidden="true"
                />
                Sealed
            </span>
        </div>

        <dl class="relative mt-4 divide-y divide-[rgba(160,205,245,0.10)]">
            <div
                v-for="row in rows"
                :key="row.label"
                class="flex items-start justify-between gap-3 py-2.5 first:pt-0 last:pb-0"
            >
                <div>
                    <dt class="text-xs font-medium text-ink-300">{{ row.label }}</dt>
                    <p class="mt-0.5 text-[0.68rem] text-ink-700">{{ row.caption }}</p>
                </div>
                <dd class="shrink-0 font-display text-lg font-semibold text-ink-100">
                    {{ row.display }}
                </dd>
            </div>
        </dl>
    </section>
</template>
