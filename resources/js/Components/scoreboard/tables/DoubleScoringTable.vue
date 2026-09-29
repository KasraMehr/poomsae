<script setup>
import { computed } from "vue";
import ScoringTableBase from "./ScoringTableBase.vue";

/**
 * جدول امتیاز Double — به‌ازای هر ورزشکار (سمت) ستون‌های قاضی + مجموع.
 */
const props = defineProps({
    judgeCount: { type: Number, default: 5 },
    athletes: { type: Array, default: () => [] }, // [{ key, name }]
    rows: { type: Array, default: () => [] }, // [{ key, label, scores: { [athleteKey]: [] }, totals: { [athleteKey]: number } }]
    totalLabel: { type: String, default: "مجموع" },
});

const columns = computed(() =>
    props.athletes.flatMap((athlete) => [
        ...Array.from({ length: props.judgeCount }, (_, i) => ({
            key: `${athlete.key}-judge${i + 1}`,
            label: `قاضی ${i + 1}`,
            group: athlete.name,
        })),
        {
            key: `${athlete.key}-total`,
            label: props.totalLabel,
            group: athlete.name,
        },
    ]),
);

const tableRows = computed(() =>
    props.rows.map((row) => ({
        key: row.key,
        label: row.label,
        cells: Object.fromEntries(
            columns.value.map((column) => {
                if (column.key.endsWith("-total")) {
                    const athleteKey = column.key.replace("-total", "");
                    return [column.key, row.totals?.[athleteKey] ?? "—"];
                }
                const [athleteKey, judge] = column.key.split("-judge");
                const index = Number(judge) - 1;
                return [
                    column.key,
                    row.scores?.[athleteKey]?.[index] ?? "—",
                ];
            }),
        ),
    })),
);

// گروه‌بندی هدر: نام ورزشکار بالای ستون‌هایش
const headerGroups = computed(() => {
    const groups = [];
    for (const column of columns.value) {
        const last = groups[groups.length - 1];
        if (last && last.label === column.group) {
            last.span += 1;
        } else {
            groups.push({ label: column.group, span: 1 });
        }
    }
    return groups;
});
</script>

<template>
    <div class="overflow-x-auto rounded-xl border border-rtds-border bg-rtds-bg-card">
        <table class="w-full border-collapse">
            <thead>
                <tr class="border-b border-rtds-border">
                    <th rowspan="2" class="px-3 py-2.5 text-right text-xs font-medium uppercase tracking-wide text-rtds-text-muted">
                        <slot name="first-header">حرکت</slot>
                    </th>
                    <th
                        v-for="group in headerGroups"
                        :key="group.label"
                        :colspan="group.span"
                        class="px-2 py-2 text-center text-xs font-semibold uppercase tracking-wide text-rtds-text-light"
                        :class="judgeCount >= 7 ? 'text-[11px]' : 'text-xs'"
                    >
                        {{ group.label }}
                    </th>
                </tr>
                <tr class="border-b border-rtds-border bg-rtds-bg-elevated">
                    <th
                        v-for="column in columns"
                        :key="column.key"
                        class="px-2 text-center text-xs font-medium uppercase tracking-wide text-rtds-text-muted"
                        :class="judgeCount >= 7 ? 'py-1.5' : 'py-2'"
                    >
                        {{ column.label }}
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="row in tableRows"
                    :key="row.key"
                    class="border-b border-rtds-border-subtle last:border-0"
                >
                    <td class="px-3 py-2.5 text-right text-sm text-rtds-text-secondary">
                        {{ row.label }}
                    </td>
                    <td
                        v-for="column in columns"
                        :key="column.key"
                        class="px-2 text-center text-sm tabular-nums"
                        :class="[
                            judgeCount >= 7 ? 'py-1.5 text-xs' : 'py-2.5 text-sm',
                            column.key.endsWith('-total')
                                ? 'font-semibold text-rtds-gold-bright'
                                : 'text-rtds-text-light',
                        ]"
                    >
                        {{ row.cells[column.key] ?? "—" }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
