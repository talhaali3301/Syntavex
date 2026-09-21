<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const passwordInput = ref<HTMLInputElement | null>(null);
const currentPasswordInput = ref<HTMLInputElement | null>(null);

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const updatePassword = (): void => {
    form.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: () => {
            if (form.errors.password) {
                form.reset('password', 'password_confirmation');
                passwordInput.value?.focus();
            }
            if (form.errors.current_password) {
                form.reset('current_password');
                currentPasswordInput.value?.focus();
            }
        },
    });
};
</script>

<template>
    <section class="relative">
        <header>
            <h2 id="update-password-heading" class="panel-heading">Update Password</h2>
            <p class="panel-note mt-1">
                Use a long, random password to keep this account secure.
            </p>
        </header>

        <form class="mt-6 space-y-5" @submit.prevent="updatePassword">
            <div class="space-y-2">
                <label for="current_password" class="field-label">Current password</label>
                <input
                    id="current_password"
                    ref="currentPasswordInput"
                    v-model="form.current_password"
                    type="password"
                    class="field-input"
                    autocomplete="current-password"
                />
                <p v-if="form.errors.current_password" class="field-error">
                    {{ form.errors.current_password }}
                </p>
            </div>

            <div class="space-y-2">
                <label for="password" class="field-label">New password</label>
                <input
                    id="password"
                    ref="passwordInput"
                    v-model="form.password"
                    type="password"
                    class="field-input"
                    autocomplete="new-password"
                />
                <p v-if="form.errors.password" class="field-error">{{ form.errors.password }}</p>
            </div>

            <div class="space-y-2">
                <label for="password_confirmation" class="field-label">Confirm password</label>
                <input
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    class="field-input"
                    autocomplete="new-password"
                />
                <p v-if="form.errors.password_confirmation" class="field-error">
                    {{ form.errors.password_confirmation }}
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
