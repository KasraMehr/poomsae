<script setup>
/**
 * Country badge: flag + three-letter English country code.
 *
 * Sizes:
 * sm → md → lg → xl → xxl → xxxl
 */

defineProps({
    code: { type: String, default: "" },
    country: { type: String, default: "" },
    flagUrl: { type: String, default: "" },
    flagPosition: { type: String, default: "left" },
    size: { type: String, default: "md" },
});

const sizeClasses = {
    sm: {
        root: "h-8",
        flag: "w-[46px]",
        code: "min-w-[46px] px-1.5 text-[15px] leading-none",
        placeholder: "text-[10px]",
    },

    md: {
        root: "h-12",
        flag: "w-[70px]",
        code: "min-w-[70px] px-2 text-[24px] leading-none",
        placeholder: "text-xs",
    },

    lg: {
        root: "h-16",
        flag: "w-[92px]",
        code: "min-w-[92px] px-2.5 text-[34px] leading-none",
        placeholder: "text-sm",
    },

    xl: {
        root: "h-20",
        flag: "w-[110px]",
        code: "min-w-[110px] px-3 text-[44px] leading-none",
        placeholder: "text-base",
    },

    xxl: {
        root: "h-24",
        flag: "w-[132px]",
        code: "min-w-[132px] px-3.5 text-[56px] leading-none",
        placeholder: "text-lg",
    },

    xxxl: {
        root: "h-32",
        flag: "w-[176px]",
        code: "min-w-[176px] px-4 text-[72px] leading-none",
        placeholder: "text-2xl",
    },
};
</script>

<template>
    <span
        dir="ltr"
        class="inline-flex overflow-hidden"
        :class="[
            sizeClasses[size]?.root ?? sizeClasses.md.root,
            flagPosition === 'right' ? 'flex-row-reverse' : 'flex-row',
        ]"
    >
        <span
            class="relative shrink-0"
            :class="sizeClasses[size]?.flag ?? sizeClasses.md.flag"
        >
            <img
                v-if="flagUrl"
                :src="flagUrl"
                :alt="country || code"
                class="h-full w-full object-cover"
            />

            <span
                v-else
                class="flex h-full w-full items-center justify-center border border-dashed border-rtds-border-strong bg-rtds-bg-elevated font-rtds font-bold tracking-wider text-rtds-text-secondary"
                :class="
                    sizeClasses[size]?.placeholder ?? sizeClasses.md.placeholder
                "
            >
                {{ code }}
            </span>
        </span>

        <span
            class="flex flex-1 items-center justify-center bg-white/[0.11] px-2 font-rtds font-bold tracking-wide text-white"
            :class="sizeClasses[size]?.code ?? sizeClasses.md.code"
        >
            {{ code }}
        </span>
    </span>
</template>
