<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';

defineProps<{
    mustVerifyEmail?: boolean;
    status?: string;
}>();

const user = computed(() => usePage().props.auth.user);

const initials = computed(() => {
    const parts = user.value.name.trim().split(/\s+/).filter(Boolean);

    if (parts.length === 0) {
        return '';
    }

    return (
        parts.length > 1
            ? `${parts[0][0]}${parts[parts.length - 1][0]}`
            : parts[0].slice(0, 2)
    ).toUpperCase();
});

const verified = computed(() => Boolean(user.value.email_verified_at));
</script>

<template>
    <Head title="Account" />

    <AppLayout>
        <div class="flex min-h-screen flex-col">
            <header
                class="relative flex min-h-[74px] flex-wrap items-center gap-x-4 gap-y-3 border-b border-[rgba(160,205,245,0.09)] bg-gradient-to-b from-[rgba(14,26,44,0.82)] to-[rgba(9,18,32,0.42)] px-8 py-[14px] backdrop-blur-lg"
            >
                <div
                    class="pointer-events-none absolute -top-[150px] left-[180px] h-[300px] w-[520px] rounded-full bg-[radial-gradient(circle,rgba(139,124,255,0.14),rgba(139,124,255,0)_70%)]"
                    aria-hidden="true"
                />

                <span
                    class="relative grid h-11 w-11 shrink-0 place-items-center rounded-full bg-[linear-gradient(145deg,#8B7CFF,#2DE2E6)] font-display text-sm font-semibold text-[#061020] shadow-[0_0_18px_rgba(139,124,255,0.4)]"
                    aria-hidden="true"
                >
                    {{ initials }}
                </span>

                <div class="relative flex min-w-0 flex-col gap-0.5">
                    <div class="flex items-center gap-2.5">
                        <h1 class="truncate font-display text-[17px] font-semibold -tracking-[0.01em] text-ink-100">
                            {{ user.name }}
                        </h1>
                        <span
                            v-if="verified"
                            class="shrink-0 rounded border border-status-completed/40 px-1.5 py-[3px] font-mono text-[10px] font-medium tracking-[0.08em] text-status-completed"
                        >
                            VERIFIED
                        </span>
                        <span
                            v-else
                            class="shrink-0 rounded border border-status-review/40 px-1.5 py-[3px] font-mono text-[10px] font-medium tracking-[0.08em] text-status-review"
                        >
                            UNVERIFIED
                        </span>
                    </div>
                    <p class="truncate font-mono text-[11.5px] text-ink-700">
                        account / {{ user.email }}
                    </p>
                </div>
            </header>

            <div class="flex flex-1 flex-col gap-[18px] px-8 pb-8 pt-[22px]">
                <div class="grid grid-cols-1 gap-[18px] xl:grid-cols-2">
                    <section class="glass-panel p-6" aria-labelledby="profile-information-heading">
                        <div
                            class="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r from-accent-cyan/70 to-transparent"
                            aria-hidden="true"
                        />
                        <UpdateProfileInformationForm
                            :must-verify-email="mustVerifyEmail"
                            :status="status"
                        />
                    </section>

                    <section class="glass-panel p-6" aria-labelledby="update-password-heading">
                        <div
                            class="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r from-accent-violet/70 to-transparent"
                            aria-hidden="true"
                        />
                        <UpdatePasswordForm />
                    </section>
                </div>

                <section
                    class="glass-panel border-status-critical/20 p-6"
                    aria-labelledby="delete-user-heading"
                >
                    <div
                        class="pointer-events-none absolute -right-16 -top-16 h-48 w-48 rounded-full bg-status-critical/15 blur-[80px]"
                        aria-hidden="true"
                    />
                    <DeleteUserForm />
                </section>
            </div>
        </div>
    </AppLayout>
</template>
