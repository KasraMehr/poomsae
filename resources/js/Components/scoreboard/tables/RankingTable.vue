<script setup>
import RankingRow from "./RankingRow.vue";

/**
 * جدول رنکینگ — طرح RANKING-TABLE: ردیف‌های کاهشی (بدون هدر — هدر متعلق به صفحهٔ مصرف‌کننده است).
 *
 * - ستون‌های نمره عرض ثابت (۱۴۲px) دارند تا divider بین ردیف‌ها در یک خط بایستد؛
 *   تعداد ستون‌های راند ثابت ۲ است (برچسب ستون‌ها R-1/R-2 را صفحه بالای جدول رندر می‌کند).
 */
defineProps({
    /** [{ rank, name, country, countryCode, flagUrl, roundScores }] */
    rows: { type: Array, default: () => [] },
});
</script>

<template>
    <div dir="ltr" class="w-full font-rtds">
        <!-- ردیف‌ها با شکاف ۱۵px طرح -->
        <div class="flex flex-col gap-[15px]">
            <RankingRow
                v-for="(row, index) in rows"
                :key="row.rank ?? index"
                v-bind="row"
            />
            <slot />
        </div>
    </div>
</template>
