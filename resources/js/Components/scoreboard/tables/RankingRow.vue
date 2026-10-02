<script setup>
import NationalityBadge from "../NationalityBadge.vue";
import SymbolContainer from "../SymbolContainer.vue";
import { medalSymbol } from "../../../Shared/Services/symbols";

/**
 * ردیف جدول رنکینگ — ارتفاع و رنگ هر ردیف بسته به رتبه متفاوت است (طبق طراحی).
 */
const props = defineProps({
    rank: { type: [Number, String], required: true },
    name: { type: String, default: "" },
    country: { type: String, default: "" }, // فقط برای alt پرچم
    countryCode: { type: String, default: "" }, // کد انگلیسی مثل IRI — متن بج
    flagUrl: { type: String, default: "" },
    score: { type: [Number, String], default: "" },
    highlight: { type: Boolean, default: false },
});

// ارتفاع‌های متغیر طراحی: رتبه‌های بالاتر ردیف بلندتری دارند
const rowHeight = (rank) => {
    const heights = { 1: "min-h-[130px]", 2: "min-h-[110px]", 3: "min-h-[90px]" };
    return heights[rank] ?? "min-h-[75px]";
};
</script>

<template>
    <div
        class="flex items-center gap-5 rounded-xl border px-6"
        :class="[
            rowHeight(props.rank),
            highlight
                ? 'border-rtds-gold bg-rtds-gold/10'
                : 'border-rtds-border bg-rtds-bg-card',
        ]"
    >
        <!-- مدال رتبه: تصویر ثابت پروژه، از روی rank انتخاب می‌شود -->
        <SymbolContainer :src="medalSymbol(rank)" :label="`رتبهٔ ${rank}`" size="md" />

        <div class="min-w-0 flex-1">
            <p
                class="truncate font-semibold text-rtds-text-light"
                :class="props.rank === 1 ? 'text-2xl' : 'text-lg'"
            >
                {{ name }}
            </p>
            <NationalityBadge
                :code="countryCode"
                :country="country"
                :flag-url="flagUrl"
                size="sm"
                class="mt-1"
            />
        </div>

        <b
            class="shrink-0 tabular-nums"
            :class="[
                props.rank === 1 ? 'text-3xl text-rtds-gold-bright' : 'text-xl text-rtds-text-light',
            ]"
            >{{ score }}</b
        >
    </div>
</template>
