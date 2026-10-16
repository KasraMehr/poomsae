<script setup>
import { computed } from "vue";
import SymbolContainer from "./SymbolContainer.vue";
import { formSymbol } from "../../Shared/Services/symbols";

/**
 * Form badge: [roundLabel] [symbol] [formName]
 *
 * Sizes:
 * sm → md → lg → xl → xxl → xxxl
 */

const props = defineProps({
    roundLabel: { type: String, default: "" },
    // ترتیب فرم در مسابقه: 1..2
    formNumber: { type: [String, Number], default: null },
    // کلید سمبل فرم: 1..18
    symbolKey: { type: [String, Number], default: null },
    formName: { type: String, default: "" },
    size: { type: String, default: "md" },
});

const symbol = computed(() => formSymbol(props.symbolKey ?? props.formNumber));

const sizeClasses = {
    sm: {
        root: "gap-2",
        round: "text-sm",
        form: "text-sm",
        symbol: "sm",
    },

    md: {
        root: "gap-3",
        round: "text-lg",
        form: "text-lg",
        symbol: "md",
    },

    lg: {
        root: "gap-4",
        round: "text-2xl",
        form: "text-3xl",
        symbol: "lg",
    },

    xl: {
        root: "gap-5",
        round: "text-3xl",
        form: "text-4xl",
        symbol: "xl",
    },

    xxl: {
        root: "gap-6",
        round: "text-4xl",
        form: "text-5xl",
        symbol: "xxl",
    },

    xxxl: {
        root: "gap-8",
        round: "text-5xl",
        form: "text-6xl",
        symbol: "xxxl",
    },
};
</script>

<template>
    <span
        dir="ltr"
        class="inline-flex items-center font-rtds font-bold uppercase text-white"
        :class="sizeClasses[size]?.root ?? sizeClasses.md.root"
    >
        <span
            v-if="roundLabel"
            class="shrink-0 text-white/85"
            :class="sizeClasses[size]?.round ?? sizeClasses.md.round"
        >
            {{ roundLabel }}
        </span>

        <SymbolContainer
            :src="symbol"
            :label="formName"
            :size="sizeClasses[size]?.symbol ?? sizeClasses.md.symbol"
        />

        <span
            class="shrink-0"
            :class="sizeClasses[size]?.form ?? sizeClasses.md.form"
        >
            {{ formName }}
        </span>
    </span>
</template>
