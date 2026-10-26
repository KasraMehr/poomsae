<script setup>
import TopFourRow from "./TopFourRow.vue";

/**
 * جدول چهار نفر برتر — طرح TOP-4-TABLE: ۴ ردیف کاهشی (بدون هدر — هدر متعلق به صفحهٔ مصرف‌کننده است).
 *
 * - ردیف‌ها با شکاف ۱۵px طرح؛ ارتفاع هر ردیف از خود TopFourRow می‌آید.
 */
defineProps({
    /** [{ rank, name, country, countryCode, flagUrl, score }] */
    rows: { type: Array, default: () => [] },
});
</script>

<template>
    <div dir="ltr" class="w-full font-rtds">
        <div class="flex flex-col gap-[15px]">
            <TopFourRow
                v-for="(row, index) in rows"
                :key="row.rank ?? index"
                v-bind="row"
            />
            <slot />
        </div>
    </div>
</template>
