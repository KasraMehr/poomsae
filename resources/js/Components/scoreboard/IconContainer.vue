<script setup>
import { computed } from "vue";

/**
 * کانتینر آیکون فرم یا مدال (رتبه).
 * آیکون‌ها به‌صورت URL/مسیر فایل SVG محلی از prop می‌آیند.
 */
const props = defineProps({
    src: { type: String, default: "" },
    variant: { type: String, default: "form" }, // 'form' | 'medal'
    medalRank: { type: Number, default: 0 }, // 1 | 2 | 3
    size: { type: String, default: "md" }, // 'sm' | 'md' | 'lg'
    label: { type: String, default: "" },
});

const sizeClasses = {
    sm: "h-8 w-8 rounded-md",
    md: "h-12 w-12 rounded-lg",
    lg: "h-16 w-16 rounded-xl",
};

const medalClasses = {
    1: "bg-rtds-gold text-rtds-text-on-yellow",
    2: "bg-rtds-surface-light text-rtds-bg",
    3: "bg-rtds-yellow-dark text-rtds-text-on-yellow",
};

const containerClass = computed(() => [
    sizeClasses[props.size] ?? sizeClasses.md,
    props.variant === "medal"
        ? (medalClasses[props.medalRank] ?? "bg-rtds-bg-elevated")
        : "bg-rtds-bg-elevated border border-rtds-border",
]);
</script>

<template>
    <span
        class="inline-grid shrink-0 place-items-center"
        :class="containerClass"
        :title="label"
    >
        <img
            v-if="src"
            :src="src"
            :alt="label"
            class="h-1/2 w-1/2 object-contain"
        />
        <b v-else-if="variant === 'medal'" class="text-sm leading-none">{{
            medalRank
        }}</b>
        <slot v-else />
    </span>
</template>
