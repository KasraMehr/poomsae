<script setup>
defineProps({ errors: Object });
</script>
<template>
    <div v-if="Object.keys(errors || {}).length" class="my-3.5 rounded-lg border border-[#f0c7bf] bg-[#fff0ed] px-4 py-3 text-xs text-[#a04132]" role="alert">
        <p v-for="(error, key) in errors" :key="key">{{ error }}</p>
    </div>
</template>
