<script setup>
import IconContainer from "./IconContainer.vue";

/**
 * چیپ دور + آیکون فرم + نام فرم.
 * سه سایز برای صفحات Draw (lg)، Standby (md) و Scoring (sm).
 */
defineProps({
    size: { type: String, default: "md" }, // 'sm' | 'md' | 'lg'
    roundLabel: { type: String, default: "" },
    formName: { type: String, default: "" },
    icon: { type: String, default: "" }, // URL/مسیر آیکون فرم
});

const containerClasses = {
    sm: "gap-2 rounded-lg px-3 py-2",
    md: "gap-3 rounded-xl px-4 py-3",
    lg: "gap-4 rounded-2xl px-6 py-4",
};

const iconSizes = { sm: "sm", md: "md", lg: "lg" };

const textClasses = {
    sm: "text-xs",
    md: "text-sm",
    lg: "text-base",
};
</script>

<template>
    <div
        class="inline-flex items-center border border-rtds-border bg-rtds-bg-card"
        :class="containerClasses[size] ?? containerClasses.md"
    >
        <IconContainer
            :src="icon"
            variant="form"
            :size="iconSizes[size] ?? 'md'"
        />
        <div class="flex flex-col leading-tight">
            <span
                class="font-medium uppercase tracking-wide text-rtds-text-muted"
                :class="textClasses[size] ?? textClasses.md"
                >{{ roundLabel }}</span
            >
            <span
                class="font-semibold text-rtds-text-light"
                :class="textClasses[size] ?? textClasses.md"
                >{{ formName }}</span
            >
        </div>
        <slot />
    </div>
</template>
