<script setup>
import { DEFAULT_FLAG_URL } from "../../Shared/Services/flags";

/**
 * Country badge: flag + three-letter English country code.
 *
 * Sizes:
 * sm → md → lg → xl → xxl → xxxl
 */

defineProps({
    code: { type: String, default: "IRI" },
    country: { type: String, default: "" },
    flagUrl: { type: String, default: "" },
    flagPosition: { type: String, default: "left" },
    size: { type: String, default: "md" },
});

const sizeClasses = {
    sm: {
        root: "w-[104px]",
        flag: "w-2/3",
        code: "w-1/3 min-w-0 px-1 text-[13px] leading-none",
        placeholder: "text-[10px]",
    },

    md: {
        root: "w-[156px]",
        flag: "w-2/3",
        code: "w-1/3 min-w-0 px-1.5 text-[19px] leading-none",
        placeholder: "text-xs",
    },

    lg: {
        root: "w-[204px]",
        flag: "w-2/3",
        code: "w-1/3 min-w-0 px-2 text-[27px] leading-none",
        placeholder: "text-sm",
    },

    xl: {
        root: "w-[240px]",
        flag: "w-2/3",
        code: "w-1/3 min-w-0 px-2 text-[38px] leading-none",
        placeholder: "text-base",
    },

    xxl: {
        root: "w-[360px]",
        flag: "w-2/3",
        code: "w-1/3 min-w-0 px-2 text-[52px] leading-none",
        placeholder: "text-2xl",
    },

    xxxl: {
        root: "w-[390px]",
        flag: "w-2/3",
        code: "w-1/3 min-w-0 px-2 text-[54px] leading-none",
        placeholder: "text-2xl",
    },
};
</script>

<template>
    <span
        dir="ltr"
        class="nationality-badge inline-flex overflow-hidden pt-1"
        :class="[
            sizeClasses[size]?.root ?? sizeClasses.md.root,
            flagPosition === 'right' ? 'flex-row-reverse' : 'flex-row',
        ]"
    >
        <span
            class="relative flex h-full shrink-0"
            :class="sizeClasses[size]?.flag ?? sizeClasses.md.flag"
        >
            <img
                :src="flagUrl || DEFAULT_FLAG_URL"
                :alt="country || code || 'Iran'"
                class="block h-full w-full object-contain"
            />

        </span>

        <span
            class="flex h-full shrink-0 items-center justify-center bg-white/[0.11] font-rtds font-bold tracking-wide text-white"
            :class="sizeClasses[size]?.code ?? sizeClasses.md.code"
        >
            {{ code }}
        </span>
    </span>
</template>

<style scoped>
.nationality-badge {
    box-sizing: content-box;
    height: auto;
    aspect-ratio: 2.625 / 1;
}
</style>
