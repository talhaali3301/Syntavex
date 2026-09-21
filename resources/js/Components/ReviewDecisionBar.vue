<script setup lang="ts">
import type { ReviewDecision, ReviewQueueItem } from '@/types';
import { useForm } from '@inertiajs/vue3';
import { onBeforeUnmount, ref } from 'vue';

const props = withDefaults(
    defineProps<{ item: ReviewQueueItem; variant?: 'frozen' | 'row'; showNote?: boolean }>(),
    { variant: 'row', showNote: true },
);

const form = useForm<{ decision: ReviewDecision | ''; note: string }>({
    decision: '',
    note: '',
});

const armed = ref<ReviewDecision | null>(null);
let disarmTimer: ReturnType<typeof setTimeout> | undefined;

const needsHold = (decision: ReviewDecision): boolean =>
    props.item.frozen && decision !== 'request_changes';

const disarm = (): void => {
    clearTimeout(disarmTimer);
    armed.value = null;
};

const submit = (decision: ReviewDecision): void => {
    if (needsHold(decision) && armed.value !== decision) {
        clearTimeout(disarmTimer);
        armed.value = decision;
        disarmTimer = setTimeout(disarm, 4000);

        return;
    }

    disarm();
    form.decision = decision;
    form.post(`/reviews/${props.item.id}/decision`, {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};

onBeforeUnmount(disarm);

const BASE =
    'rounded-[10px] font-mono text-[11px] font-semibold tracking-[0.06em] transition duration-200 disabled:cursor-not-allowed disabled:opacity-45 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-cyan focus-visible:ring-offset-2 focus-visible:ring-offset-navy-base';

const label = (decision: ReviewDecision, resting: string): string =>
    armed.value === decision ? 'CONFIRM · CLICK AGAIN' : resting;
</script>

<template>
    <div
        class="flex flex-wrap items-center gap-x-3.5 gap-y-2.5"
        :class="variant === 'frozen' ? 'justify-between' : 'justify-end'"
    >
        <p
            v-if="variant === 'frozen'"
            class="max-w-[17rem] flex-1 font-mono text-[10px] leading-[1.6] text-[#9C8388]"
        >
            Two-step confirmation required · both actions are logged to the signed
            ledger
        </p>

        <input
            v-if="showNote"
            v-model="form.note"
            type="text"
            maxlength="500"
            placeholder="note for the ledger (optional)"
            class="h-[38px] w-full min-w-0 shrink rounded-[10px] border-[rgba(160,205,245,0.16)] bg-[rgba(5,12,22,0.7)] px-3 font-mono text-[11px] text-ink-200 placeholder:text-ink-900 focus:border-accent-cyan/50 focus:ring-0 sm:w-[15rem]"
        />

        <div class="flex flex-none flex-wrap items-center gap-2.5">
            <button
                type="button"
                :disabled="form.processing"
                :class="`${BASE} border border-[rgba(160,205,245,0.18)] px-4 py-3 text-ink-400 hover:border-[rgba(160,205,245,0.4)] hover:text-ink-100`"
                @click="submit('request_changes')"
            >
                REQUEST CHANGES
            </button>

            <button
                type="button"
                :disabled="form.processing"
                :class="[
                    BASE,
                    'px-[22px] py-3',
                    armed === 'approve'
                        ? 'border border-accent-cyan bg-accent-cyan/30 text-white shadow-[0_0_26px_rgba(45,226,230,0.5)]'
                        : variant === 'frozen'
                          ? 'border border-accent-cyan/50 bg-[rgba(10,26,40,0.8)] text-glow-cyan hover:bg-accent-cyan/[0.14]'
                          : 'bg-[linear-gradient(140deg,#7DEDF0,#2DE2E6)] text-[#061020] shadow-[0_0_30px_rgba(45,226,230,0.35)] hover:shadow-[0_0_38px_rgba(45,226,230,0.5)]',
                ]"
                @click="submit('approve')"
            >
                {{ label('approve', variant === 'frozen' ? 'HOLD TO APPROVE' : 'APPROVE') }}
            </button>

            <button
                type="button"
                :disabled="form.processing"
                :class="[
                    BASE,
                    'border px-[18px] py-3',
                    armed === 'reject'
                        ? 'border-status-critical bg-status-critical/30 text-white shadow-[0_0_26px_rgba(242,108,120,0.45)]'
                        : variant === 'frozen'
                          ? 'border-status-critical/60 bg-[linear-gradient(140deg,rgba(242,108,120,0.9),rgba(242,108,120,0.55))] text-[#2A0A0F]'
                          : 'border-status-critical/50 bg-status-critical/[0.12] text-[#FFC3C9] hover:bg-status-critical/20',
                ]"
                @click="submit('reject')"
            >
                {{ label('reject', variant === 'frozen' ? 'REJECT & REVOKE SCOPE' : 'REJECT') }}
            </button>
        </div>

        <p v-if="form.errors.decision" class="basis-full font-mono text-[10.5px] text-glow-red">
            {{ form.errors.decision }}
        </p>
    </div>
</template>
