<script setup>
import RankingRow from "./RankingRow.vue";

/**
 * جدول رنکینگ — ردیف‌ها با ارتفاع/رنگ متغیر از RankingRow ساخته می‌شوند.
 */
defineProps({
    rows: {
        type: Array,
        default: () => [],
        // [{ rank, name, country, countryCode, flagUrl, photoUrl, score, highlight }]
    },
});
</script>

<template>
    <div class="flex flex-col gap-3">
        <RankingRow
            v-for="(row, index) in rows"
            :key="row.rank ?? index"
            v-bind="row"
        />
        <slot />
    </div>
</template>
