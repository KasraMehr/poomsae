<script setup>
/**
 * پرچم + نام کشور.
 * flagUrl: مسیر/URL تصویر پرچم (SVG/PNG محلی یا URL خارجی).
 */
defineProps({
    country: { type: String, default: "" },
    flagUrl: { type: String, default: "" },
    size: { type: String, default: "md" }, // 'sm' | 'md' | 'lg'
});

const flagClasses = {
    sm: "h-4 w-6",
    md: "h-5 w-8",
    lg: "h-7 w-11",
};

const textClasses = {
    sm: "text-[11px]",
    md: "text-sm",
    lg: "text-base",
};
</script>

<template>
    <span class="inline-flex items-center gap-2">
        <img
            v-if="flagUrl"
            :src="flagUrl"
            :alt="country"
            class="rounded-sm object-cover"
            :class="flagClasses[size] ?? flagClasses.md"
        />
        <span
            class="font-medium text-rtds-text-secondary"
            :class="textClasses[size] ?? textClasses.md"
            >{{ country }}</span
        >
    </span>
</template>
