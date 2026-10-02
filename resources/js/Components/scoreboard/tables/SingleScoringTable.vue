<script setup>
import { computed } from "vue";
import ScoringTableBase from "./ScoringTableBase.vue";

/**
 * جدول امتیاز تک‌نفره (Single) — ستون‌ها را از دادهٔ ردیف‌ها می‌سازد.
 */
const props = defineProps({
    judgeCount: { type: Number, default: 5 },
    rows: { type: Array, default: () => [] }, // [{ key, label, scores: [] , total }]
    totalLabel: { type: String, default: "مجموع" },
});

const columns = computed(() => {
    const judges = Array.from({ length: props.judgeCount }, (_, i) => ({
        key: `judge${i + 1}`,
        label: `قاضی ${i + 1}`,
    }));
    return [...judges, { key: "total", label: props.totalLabel }];
});

const tableRows = computed(() =>
    props.rows.map((row) => ({
        key: row.key,
        label: row.label,
        cells: Object.fromEntries(
            columns.value.map((column, index) => [
                column.key,
                column.key === "total"
                    ? (row.total ?? "—")
                    : (row.scores?.[index] ?? "—"),
            ]),
        ),
    })),
);

const totalsRow = computed(() => {
    if (!props.rows.length) return null;
    const sums = Array.from({ length: props.judgeCount }, (_, i) =>
        props.rows.reduce(
            (sum, row) => sum + (Number(row.scores?.[i]) || 0),
            0,
        ),
    );
    return {
        key: "totals",
        label: "مجموع قاضی‌ها",
        cells: {
            ...Object.fromEntries(
                sums.map((sum, i) => [`judge${i + 1}`, sum]),
            ),
            total: props.rows.reduce(
                (sum, row) => sum + (Number(row.total) || 0),
                0,
            ),
        },
    };
});
</script>

<template>
    <ScoringTableBase
        :judge-count="judgeCount"
        :columns="columns"
        :rows="tableRows"
        highlight-column="total"
    >
        <template #footer>
            <tr
                v-if="totalsRow"
                class="border-t border-rtds-border bg-rtds-bg-elevated"
            >
                <td class="px-3 py-2.5 text-right text-sm font-semibold text-rtds-text-light">
                    {{ totalsRow.label }}
                </td>
                <td
                    v-for="column in columns"
                    :key="column.key"
                    class="px-2 text-center font-semibold tabular-nums text-rtds-text-light"
                    :class="judgeCount >= 7 ? 'py-1.5 text-xs' : 'py-2.5 text-sm'"
                >
                    {{ totalsRow.cells[column.key] ?? "—" }}
                </td>
            </tr>
        </template>
    </ScoringTableBase>
</template>
