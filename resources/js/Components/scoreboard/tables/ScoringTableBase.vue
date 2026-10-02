<script setup>
/**
 * اسکلت مشترک جدول‌های Scoring.
 * تعداد قاضی ۵ یا ۷ است؛ با ۷ قاضی سلول‌ها فشرده‌تر می‌شوند (ریسپانسیو).
 * ستون‌ها/ردیف‌ها از بیرون می‌آیند؛ هدر و layout داخل این کامپوننت است.
 */
const props = defineProps({
    judgeCount: { type: Number, default: 5 }, // 5 | 7
    columns: { type: Array, default: () => [] }, // [{ key, label }]
    rows: { type: Array, default: () => [] }, // [{ key, label, cells: {...} }]
    highlightColumn: { type: String, default: "" },
});

const cellClasses = (columnKey) => [
    "px-2 text-center tabular-nums",
    props.judgeCount >= 7 ? "py-1.5 text-xs" : "py-2.5 text-sm",
    columnKey === props.highlightColumn
        ? "text-rtds-gold-bright font-semibold"
        : "text-rtds-text-light",
];
</script>

<template>
    <div class="overflow-x-auto rounded-xl border border-rtds-border bg-rtds-bg-card">
        <table class="w-full border-collapse">
            <thead>
                <tr class="border-b border-rtds-border bg-rtds-bg-elevated">
                    <th
                        class="px-3 py-2.5 text-right text-xs font-medium uppercase tracking-wide text-rtds-text-muted"
                    >
                        <slot name="first-header">حرکت</slot>
                    </th>
                    <th
                        v-for="column in columns"
                        :key="column.key"
                        class="px-2 text-center text-xs font-medium uppercase tracking-wide text-rtds-text-muted"
                        :class="judgeCount >= 7 ? 'py-2' : 'py-2.5'"
                    >
                        {{ column.label }}
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="row in rows"
                    :key="row.key"
                    class="border-b border-rtds-border-subtle last:border-0"
                >
                    <td class="px-3 py-2.5 text-right text-sm text-rtds-text-secondary">
                        {{ row.label }}
                    </td>
                    <td
                        v-for="column in columns"
                        :key="column.key"
                        :class="cellClasses(column.key)"
                    >
                        {{ row.cells?.[column.key] ?? "—" }}
                    </td>
                </tr>
                <slot name="footer" />
            </tbody>
        </table>
    </div>
</template>
