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
    label: { type: String, default: "" }, // متن جایگزین تصویر
    size: { type: String, default: "md" }, // 'sm' | 'md' | 'lg'
});

const sizeClasses = {
    sm: "h-6 w-6", // ۲۴px
    md: "h-10 w-10", // ۴۰px
    lg: "h-14 w-14", // ۵۶px
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