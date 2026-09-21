<script setup lang="ts">
defineProps<{
    message?: string;
}>();
</script>

<template>
    <p v-show="message" class="field-error">{{ message }}</p>
</template>
