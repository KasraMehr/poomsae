<script setup>
import { computed } from "vue";
import SymbolContainer from "./SymbolContainer.vue";
import { formSymbol } from "../../Shared/Services/symbols";

/**
 * بج فرم: `[roundLabel] [symbol] [formName]` مطابق طرح.
 *
 * - ردیف ساده و بدون کادر (برخلاف نسخهٔ قبلی که کارت داشت).
 * - سمبل از `formNumber` (کلید عددی) در map دارایی‌ها resolve می‌شود؛
 *   اگر کلید ناشناخته باشد، فقط متن‌ها می‌مانند و سمبل رندر نمی‌شود.
 * - متن‌ها Oswald 700 سفید و uppercase؛ چیدمان LTR مثل طرح.
 */
const props = defineProps({
    roundLabel: { type: String, default: "" }, // مثل «R - 1» (رشتهٔ آمادهٔ صفحه)
    formNumber: { type: [String, Number], default: null }, // کلید فرم ۱..۱۸
    formName: { type: String, default: "" }, // مثل «TAEGUK 5»
    size: { type: String, default: "md" }, // 'sm' | 'md' | 'lg' — سه سایز مستقل
});

const symbol = computed(() => formSymbol(props.formNumber));

const sizeClasses = {
    sm: { root: "gap-2", text: "text-sm", symbol: "sm" },
    md: { root: "gap-3", text: "text-xl", symbol: "md" },
    lg: { root: "gap-4", text: "text-4xl", symbol: "lg" },
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
            class="text-white/85"
            :class="sizeClasses[size]?.text ?? sizeClasses.md.text"
            >{{ roundLabel }}</span
        >
        <SymbolContainer
            :src="symbol"
            :label="formName"
            :size="sizeClasses[size]?.symbol ?? sizeClasses.md.symbol"
        />
        <span :class="sizeClasses[size]?.text ?? sizeClasses.md.text">{{
            formName
        }}</span>
    </span>
</template>