<script setup>
defineProps({ errors: Object });
</script>
<template><div v-if="Object.keys(errors || {}).length" class="error-box" role="alert"><p v-for="(error,key) in errors" :key="key">{{ error }}</p></div></template>

