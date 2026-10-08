<script setup>
/**
 * نگه‌دارندهٔ یک سمبل (فرم یا مدال).
 *
 * - فقط تصویر را در اندازهٔ مشخص نگه می‌دارد؛ پس‌زمینه/کادر ندارد چون حلقهٔ رنگی داخل خود تصویر است.
 * - اگر `src` نبود (کلید ناشناخته یا asset موجود نیست) هیچ چیزی رندر نمی‌شود.
 * - مسیر تصویر از `Shared/Services/symbols.js` می‌آید، نه از دادهٔ snapshot.
 */
defineProps({
    src: { type: String, default: "" },
    label: { type: String, default: "" },
    size: { type: String, default: "md" },
});

const sizeClasses = {
    sm: "h-6 w-6",
    md: "h-10 w-10",
    lg: "h-14 w-14",
    xl: "h-18 w-18",
    xxl: "h-24 w-24",
    xxxl: "h-32 w-32",
};
</script>

<template>
    <img
        v-if="src"
        :src="src"
        :alt="label"
        class="shrink-0 object-contain"
        :class="sizeClasses[size] ?? sizeClasses.md"
    />
</template>
