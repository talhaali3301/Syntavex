<script setup lang="ts">
import Modal from '@/Components/Modal.vue';
import { useForm } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';

const confirmingUserDeletion = ref(false);
const passwordInput = ref<HTMLInputElement | null>(null);

const form = useForm({
    password: '',
});

const confirmUserDeletion = (): void => {
    confirmingUserDeletion.value = true;

    nextTick(() => passwordInput.value?.focus());
};

const deleteUser = (): void => {
    form.delete(route('profile.destroy'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: () => passwordInput.value?.focus(),
        onFinish: () => form.reset(),
    });
};

const closeModal = (): void => {
    confirmingUserDeletion.value = false;

    form.clearErrors();
    form.reset();
};
</script>

<template>
    <section class="relative">
        <header class="flex flex-wrap items-center gap-2.5">
            <h2 id="delete-user-heading" class="panel-heading">Delete Account</h2>
            <span
                class="rounded border border-status-critical/40 px-1.5 py-[3px] font-mono text-[10px] font-medium tracking-[0.08em] text-status-critical"
            >
                IRREVERSIBLE
            </span>
        </header>

        <p class="mt-2 max-w-[62ch] text-xs leading-relaxed text-ink-700">
            Deleting this account permanently removes it and everything attached to it.
            Export anything you need to keep before you confirm.
        </p>

        <button type="button" class="btn-glass btn-glass-danger mt-5" @click="confirmUserDeletion">
            Delete Account
        </button>

        <Modal :show="confirmingUserDeletion" max-width="lg" @close="closeModal">
            <div class="freeze-halo relative p-6">
                <h2 class="font-display text-[19px] font-semibold text-ink-100">
                    Delete this account?
                </h2>

                <p class="mt-2 text-xs leading-relaxed text-ink-600">
                    This cannot be undone. Enter your password to confirm.
                </p>

                <div class="mt-5 space-y-2">
                    <label for="delete-password" class="sr-only">Password</label>
                    <input
                        id="delete-password"
                        ref="passwordInput"
                        v-model="form.password"
                        type="password"
                        class="field-input"
                        placeholder="Password"
                        @keyup.enter="deleteUser"
                    />
                    <p v-if="form.errors.password" class="field-error">{{ form.errors.password }}</p>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" class="btn-glass btn-glass-ghost" @click="closeModal">
                        Cancel
                    </button>

                    <button
                        type="button"
                        class="btn-glass btn-glass-danger"
                        :disabled="form.processing"
                        @click="deleteUser"
                    >
                        Delete Account
                    </button>
                </div>
            </div>
        </Modal>
    </section>
</template>
