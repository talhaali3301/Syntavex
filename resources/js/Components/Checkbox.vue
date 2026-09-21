<script setup lang="ts">
import { computed } from 'vue';

const emit = defineEmits(['update:checked']);

const props = defineProps<{
    checked: boolean;
    value?: any;
}>();

const proxyChecked = computed({
    get() {
        return props.checked;
    },

    set(val) {
        emit('update:checked', val);
    },
});
</script>

<template>
    <input
        v-model="proxyChecked"
        type="checkbox"
        :value="value"
        class="h-4 w-4 rounded border-white/15 bg-white/[0.04] text-accent-cyan focus:ring-2 focus:ring-accent-cyan/40 focus:ring-offset-0"
    />
</template>
