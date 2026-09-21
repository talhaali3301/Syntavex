<script setup lang="ts">
import { Link, useForm, usePage } from '@inertiajs/vue3';

defineProps<{
    mustVerifyEmail?: boolean;
    status?: string;
}>();

const user = usePage().props.auth.user;

const form = useForm({
    name: user.name,
    email: user.email,
});
</script>

<template>
    <section class="relative">
        <header>
            <h2 id="profile-information-heading" class="panel-heading">Profile Information</h2>
            <p class="panel-note mt-1">
                Update your account's name and email address.
            </p>
        </header>

        <form class="mt-6 space-y-5" @submit.prevent="form.patch(route('profile.update'))">
            <div class="space-y-2">
                <label for="name" class="field-label">Name</label>
                <input
                    id="name"
                    v-model="form.name"
                    type="text"
                    class="field-input"
                    required
                    autocomplete="name"
                />
                <p v-if="form.errors.name" class="field-error">{{ form.errors.name }}</p>
            </div>

            <div class="space-y-2">
                <label for="email" class="field-label">Email</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    class="field-input"
                    required
                    autocomplete="username"
                />
                <p v-if="form.errors.email" class="field-error">{{ form.errors.email }}</p>
            </div>

            <div
                v-if="mustVerifyEmail && user.email_verified_at === null"
                class="rounded-lg border border-status-review/30 bg-status-review/[0.08] px-3.5 py-3"
            >
                <p class="text-xs text-ink-300">
                    Your email address is unverified.
                    <Link
                        :href="route('verification.send')"
                        method="post"
                        as="button"
                        class="rounded text-status-review underline underline-offset-2 transition hover:text-glow-cyan focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-cyan"
                    >
                        Re-send the verification email.
                    </Link>
                </p>

                <p
                    v-show="status === 'verification-link-sent'"
                    class="mt-2 font-mono text-[11px] text-status-completed"
                >
                    A new verification link has been sent to your email address.
                </p>
            </div>

            <div class="flex items-center gap-4 pt-1">
                <button type="submit" class="btn-glass btn-glass-primary" :disabled="form.processing">
                    Save
                </button>

                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p v-if="form.recentlySuccessful" class="font-mono text-[11px] text-status-completed">
                        Saved.
                    </p>
                </Transition>
            </div>
        </form>
    </section>
</template>
