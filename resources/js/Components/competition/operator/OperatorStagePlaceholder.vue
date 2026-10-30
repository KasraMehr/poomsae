<script setup>
defineProps({
    label: { type: String, required: true },
    title: { type: String, required: true },
});
</script>

<template>
    <section class="operator-stage-placeholder" :aria-label="`${label} stage placeholder`">
        <span>{{ label }}</span>
        <h2>{{ title }}</h2>
        <p>Stage content will be added after its layout is defined.</p>
    </section>
</template>

<style scoped>
.operator-stage-placeholder {
    display: grid;
    min-height: 280px;
    align-content: center;
    justify-items: center;
    padding: 24px;
    border: 1px dashed #c4c9d2;
    background: #f7f8fc;
    color: #0d192b;
    text-align: center;
}

.operator-stage-placeholder > span {
    color: #667184;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 1px;
}

.operator-stage-placeholder h2 {
    margin: 8px 0;
    font-size: 20px;
}

.operator-stage-placeholder p {
    margin: 0;
    color: #667184;
}
</style>
