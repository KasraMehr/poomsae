<script setup>
import { computed } from "vue";

const props = defineProps({
    disabled: Boolean,
    full: Boolean,
    size: { type: String, default: "default" },
    type: { type: String, default: "button" },
    variant: { type: String, default: "primary" },
});

const variantClasses = {
    primary: "bg-[#286957] text-white hover:bg-[#1b5142]",
    secondary: "bg-[#e8eee7] text-[#42664f]",
    danger: "bg-[#a33939] text-white hover:bg-[#842d2d]",
};
const sizeClasses = {
    default: "min-h-11 px-[22px] py-[11px] text-[13px]",
    small: "min-h-[34px] px-3 py-1.5 text-[11px]",
};
const classes = computed(() => [
    "inline-flex items-center justify-center rounded-lg border-0 disabled:cursor-wait disabled:opacity-55",
    variantClasses[props.variant] || variantClasses.primary,
    sizeClasses[props.size] || sizeClasses.default,
    props.full ? "w-full" : "",
]);
</script>

<template>
    <button :type="type" :disabled="disabled" :class="classes"><slot /></button>
</template>
