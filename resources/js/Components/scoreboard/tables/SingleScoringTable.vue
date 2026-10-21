<script setup>
import { computed } from "vue";

/**
 * جدول امتیاز Single — طرح: سطر = داور، ستون‌ها = JUDGE / ACCURACY / EOE / S&P / R&P / PRESENTATION.
 *
 * - هدرها ثابت و انگلیسی‌اند (از بیرون نمی‌آیند).
 * - `showSubScores=false` → سه ستون JUDGE / ACCURACY / PRESENTATION؛
 *   عرض ستون‌های باقی‌مانده تغییر نمی‌کند، فقط ستون‌های وسط حذف می‌شوند.
 * - تعداد داوران از `judges` می‌آید (۵ یا ۷)؛ `judgeCount` فقط برای حالت خالی است.
 */
const props = defineProps({
    showSubScores: { type: Boolean, default: true },
    judgeCount: { type: Number, default: 5 }, // حالت خالی: تعداد سطرها
    judges: { type: Array, default: () => [] }, // [{ label?, accuracy, eoe, sp, rp, presentation }]
});

const columns = computed(() => [
    { key: "judge", label: "JUDGE", tone: "judge" },
    { key: "accuracy", label: "ACCURACY", tone: "score" },
    ...(props.showSubScores
        ? [
              { key: "eoe", label: "EOE", tone: "sub" },
              { key: "sp", label: "S&P", tone: "sub" },
              { key: "rp", label: "R&P", tone: "sub" },
          ]
        : []),
    { key: "presentation", label: "PRESENTATION", tone: "score" },
]);

const rows = computed(() =>
    props.judges.length
        ? props.judges
        : Array.from({ length: props.judgeCount }, () => ({})),
);

const judgeLabel = (row, index) => row.label ?? `J${index + 1}`;

const format = (value) => {
    if (value === null || value === undefined || value === "") return "—";
    return typeof value === "number" && Number.isInteger(value)
        ? value.toFixed(1)
        : String(value);
};

const gridColumns = computed(() =>
    props.showSubScores
        ? "minmax(110px, 0.9fr) minmax(140px, 1.25fr) repeat(3, minmax(100px, 1fr)) minmax(170px, 1.5fr)"
        : "minmax(110px, 0.9fr) minmax(140px, 1.25fr) minmax(170px, 1.5fr)",
);

const panelClass = (tone) => {
    if (tone === "judge") return "rounded-lg bg-rtds-gold-bright/20 shadow-lg";
    if (tone === "score") return "rounded-t-[2px] bg-[rgba(30,41,59,0.4)]";
    return "";
};

const valueClass = (tone) => {
    if (tone === "judge")
        return "font-rtds text-[36px] font-bold leading-10 text-rtds-gold-bright";
    if (tone === "sub")
        return "font-rtds text-[36px] font-medium leading-10 text-rtds-text-light";
    return "font-rtds text-[50px] font-semibold leading-10 text-rtds-surface-light";
};

const isRejected = (row, key, partIndex = null) => {
    const componentIndex = { eoe: 0, sp: 1, rp: 2 }[key];
    const componentKey = componentIndex == null ? key : "presentation_components";
    const state =
        row.acceptance?.[key] ??
        row.accepted?.[key] ??
        row.score_status?.[key] ??
        (componentIndex == null
            ? null
            : row.acceptance?.[componentKey]?.[componentIndex] ??
              row.accepted?.[componentKey]?.[componentIndex] ??
              row.score_status?.[componentKey]?.[componentIndex]);

    return (
        state === false ||
        state === "rejected" ||
        (partIndex != null &&
            (row.acceptance?.[key]?.[partIndex] === false ||
                row.acceptance?.[key]?.[partIndex] === "rejected")) ||
        row[key + "_accepted"] === false ||
        row.status === "rejected"
    );
};

const scoreStateClass = (row, key, partIndex = null) =>
    isRejected(row, key, partIndex)
        ? "!text-rtds-red line-through decoration-2"
        : "!text-white";

const headerClass = (tone) =>
    tone === "judge"
        ? "text-rtds-gold-bright"
        : "text-rtds-text-light";
</script>

<template>
    <div class="min-w-0 overflow-x-auto">
        <div
            class="grid h-[67px] min-w-[760px] items-end border-b border-rtds-divider/30"
            :style="{ gridTemplateColumns: gridColumns }"
        >
            <span
                v-for="column in columns"
                :key="column.key"
                class="whitespace-nowrap text-center font-rtds text-xl font-normal uppercase leading-9"
                :class="headerClass(column.tone)"
            >
                {{ column.label }}
            </span>
        </div>

        <div
            v-for="(row, index) in rows"
            :key="index"
            class="grid min-w-[760px] items-center border-b border-rtds-divider/20"
            :style="{ gridTemplateColumns: gridColumns }"
        >
            <div
                v-for="column in columns"
                :key="column.key"
                class="flex h-[68px] items-center justify-center px-2"
                :class="panelClass(column.tone)"
            >
                <span
                    class="text-center"
                    :class="[
                        valueClass(column.tone),
                        column.key === 'judge'
                            ? ''
                            : scoreStateClass(row, column.key),
                    ]"
                >
                    {{ column.key === "judge" ? judgeLabel(row, index) : format(row[column.key]) }}
                </span>
            </div>
        </div>
    </div>
</template>
