<script setup>
import { computed } from "vue";
import ScoringTableBase from "./ScoringTableBase.vue";

/**
 * جدول امتیاز Freestyle — ساختار ستون‌های متفاوت (اجرا/فرم) نسبت به Single.
 */
const props = defineProps({
    judgeCount: { type: Number, default: 5 },
    rows: { type: Array, default: () => [] }, // [{ key, label, scores: [], execution: [], total }]
    totalLabel: { type: String, default: "مجموع" },
});

const columns = computed(() => {
    const judges = Array.from({ length: props.judgeCount }, (_, i) => ({
        key: `judge${i + 1}`,
        label: `قاضی ${i + 1}`,
    }));
    return [
        ...judges,
        { key: "execution", label: "اجرا" },
        { key: "total", label: props.totalLabel },
    ];
});

const tableRows = computed(() =>
    props.rows.map((row) => ({
        key: row.key,
        label: row.label,
        cells: {
            ...Object.fromEntries(
                Array.from({ length: props.judgeCount }, (_, i) => [
                    `judge${i + 1}`,
                    row.scores?.[i] ?? "—",
                ]),
            ),
            execution: row.execution ?? "—",
            total: row.total ?? "—",
        },
    })),
);
</script>

<template>
    <ScoringTableBase
        :judge-count="judgeCount"
        :columns="columns"
        :rows="tableRows"
        highlight-column="total"
    />
</template>
