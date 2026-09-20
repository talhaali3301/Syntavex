<script setup lang="ts">
import type { ToolCall, TraceTone } from '@/types';
import { computed } from 'vue';

const props = defineProps<{ call: ToolCall }>();

type Skin = { shell: string; chip: string; icon: string; label: string; stroke: string };

const SKINS: Record<string, Skin> = {
    completed: {
        shell: 'border-status-completed/30 bg-[linear-gradient(150deg,rgba(85,217,139,0.10),rgba(16,26,44,0.6))] shadow-[0_14px_34px_rgba(2,8,18,0.5),0_0_24px_rgba(85,217,139,0.10)] hover:border-status-completed/60',
        chip: 'border-status-completed/[0.34] bg-status-completed/[0.14]',
        icon: '#55D98B',
        label: 'text-glow-green',
        stroke: '#55D98B',
    },
    review: {
        shell: 'border-status-review/[0.34] bg-[linear-gradient(150deg,rgba(248,198,93,0.12),rgba(16,26,44,0.6))] shadow-[0_14px_34px_rgba(2,8,18,0.5),0_0_26px_rgba(248,198,93,0.12)] hover:border-status-review/[0.65]',
        chip: 'border-status-review/[0.36] bg-status-review/[0.14]',
        icon: '#F8C65D',
        label: 'text-[#FFE1A6]',
        stroke: '#F8C65D',
    },
    critical: {
        shell: 'border-status-critical/40 bg-[linear-gradient(150deg,rgba(242,108,120,0.12),rgba(16,26,44,0.6))] shadow-[0_14px_34px_rgba(2,8,18,0.5),0_0_26px_rgba(242,108,120,0.12)] hover:border-status-critical/70',
        chip: 'border-status-critical/40 bg-status-critical/[0.14]',
        icon: '#F26C78',
        label: 'text-glow-red',
        stroke: '#F26C78',
    },
    idle: {
        shell: 'border-[rgba(160,205,245,0.16)] bg-[linear-gradient(150deg,rgba(90,168,255,0.08),rgba(16,26,44,0.6))] shadow-[0_14px_34px_rgba(2,8,18,0.5)] hover:border-[rgba(160,205,245,0.36)]',
        chip: 'border-[rgba(160,205,245,0.2)] bg-[rgba(160,205,245,0.08)]',
        icon: '#8AA3BC',
        label: 'text-ink-500',
        stroke: '#8AA3BC',
    },
};

/** Tool calls only ever carry an outcome tone; anything else reads as idle. */
const skin = computed<Skin>(() => {
    const byTone: Partial<Record<TraceTone, Skin>> = {
        critical: SKINS.critical,
        review: SKINS.review,
        idle: SKINS.idle,
    };

    return (
        byTone[props.call.tone] ??
        (props.call.status === 'completed' ? SKINS.completed : SKINS.idle)
    );
});
</script>

<template>
    <article
        class="relative rounded-[14px] border px-[15px] py-[13px] backdrop-blur-[16px] transition duration-200"
        :class="skin.shell"
    >
        <header class="flex items-center gap-[9px]">
            <span
                class="grid h-6 w-6 shrink-0 place-items-center rounded-[7px] border"
                :class="skin.chip"
                aria-hidden="true"
            >
                <svg
                    class="h-3 w-3"
                    viewBox="0 0 24 24"
                    fill="none"
                    :stroke="skin.icon"
                    stroke-width="2"
                >
                    <path v-if="call.kind === 'retrieval'" d="m4 12 5 5L20 6" />
                    <path
                        v-else
                        d="M12 4v16M15.5 8c-.8-1.3-2-1.8-3.5-1.8-2 0-3.4 1-3.4 2.6 0 3.8 7.2 2.2 7.2 6 0 1.8-1.6 2.9-3.8 2.9-1.8 0-3.2-.7-4-2"
                    />
                </svg>
            </span>
            <h3 class="min-w-0 truncate font-mono text-xs font-medium text-ink-200">
                {{ call.name }}
            </h3>
            <div class="flex-1" />
            <span class="shrink-0 font-mono text-[10px]" :class="skin.label">
                {{ call.status_label }}
            </span>
        </header>

        <dl
            class="mt-2.5 rounded-[9px] border border-[rgba(160,205,245,0.08)] bg-[rgba(5,12,22,0.7)] px-[11px] py-[9px] font-mono text-[10.5px] leading-[1.6]"
        >
            <div class="flex gap-2">
                <dt class="shrink-0 text-ink-900">in</dt>
                <dd class="min-w-0 break-words text-ink-400">{{ call.input }}</dd>
            </div>
            <div class="mt-0.5 flex gap-2">
                <dt class="shrink-0 text-ink-900">out</dt>
                <dd class="min-w-0 break-words" :class="call.output ? 'text-ink-400' : 'text-ink-900'">
                    {{ call.output ?? 'not executed' }}
                </dd>
            </div>
        </dl>

        <footer class="mt-[9px] flex gap-3.5 font-mono text-[10px] text-ink-700">
            <span>{{ call.order_label }}</span>
            <span>{{ call.duration_label }}</span>
            <span>{{ call.cost_label }}</span>
        </footer>
    </article>
</template>
