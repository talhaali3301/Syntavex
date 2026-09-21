<script setup lang="ts">
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps<{
    canResetPassword?: boolean;
    status?: string;
}>();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => {
            form.reset('password');
        },
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Log in" />

        <header>
            <p class="panel-eyebrow">Agent governance platform</p>
            <h1 class="mt-2 font-display text-[21px] font-semibold tracking-tight text-ink-100">
                Sign in
            </h1>
            <p class="mt-1.5 text-xs text-ink-700">
                Every decision on this fleet is signed. Identify yourself first.
            </p>
        </header>

        <p
            v-if="status"
            class="mt-5 rounded-lg border border-status-completed/35 bg-status-completed/[0.10] px-3.5 py-2.5 font-mono text-[11px] text-glow-green"
        >
            {{ status }}
        </p>

        <form class="mt-6 space-y-5" @submit.prevent="submit">
            <div class="space-y-2">
                <label for="email" class="field-label">Email</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    class="field-input"
                    required
                    autofocus
                    autocomplete="username"
                />
                <p v-if="form.errors.email" class="field-error">{{ form.errors.email }}</p>
            </div>

            <div class="space-y-2">
                <label for="password" class="field-label">Password</label>
                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    class="field-input"
                    required
                    autocomplete="current-password"
                />
                <p v-if="form.errors.password" class="field-error">{{ form.errors.password }}</p>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3">
                <label class="flex cursor-pointer items-center gap-2.5">
                    <input
                        v-model="form.remember"
                        type="checkbox"
                        name="remember"
                        class="h-4 w-4 rounded border-white/15 bg-white/[0.04] text-accent-cyan focus:ring-2 focus:ring-accent-cyan/40 focus:ring-offset-0"
                    />
                    <span class="text-xs text-ink-600">Remember me</span>
                </label>

                <Link
                    v-if="canResetPassword"
                    :href="route('password.request')"
                    class="rounded text-xs text-ink-700 transition duration-150 hover:text-glow-cyan focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-cyan"
                >
                    Forgot your password?
                </Link>
            </div>

            <button
                type="submit"
                class="btn-glass btn-glass-primary w-full justify-center"
                :disabled="form.processing"
            >
                Log in
            </button>
        </form>

        <p class="mt-6 border-t border-white/[0.07] pt-5 text-center text-xs text-ink-700">
            No account yet?
            <Link
                :href="route('register')"
                class="rounded font-medium text-glow-cyan transition duration-150 hover:text-accent-violet focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-cyan"
            >
                Register
            </Link>
        </p>
    </GuestLayout>
</template>
