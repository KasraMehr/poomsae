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

const judgeFontScale = computed(() => 7 / Math.max(rows.value.length, 1));
const scaledFontSize = (baseVw, minPx, maxPx) =>
    `clamp(${minPx}px, calc(${baseVw}vw * ${judgeFontScale.value}), ${maxPx}px)`;

const judgeLabel = (row, index) => row.label ?? `J${index + 1}`;

const format = (value) => {
    if (value === null || value === undefined || value === "") return "—";
    return typeof value === "number" && Number.isInteger(value)
        ? value.toFixed(1)
        : String(value);
};

const gridColumns = computed(() =>
    props.showSubScores
        ? "minmax(3.5rem, 0.72fr) minmax(0, 1.05fr) repeat(3, minmax(0, 0.72fr)) minmax(0, 1.45fr)"
        : "minmax(3.5rem, 0.72fr) minmax(0, 1.05fr) minmax(0, 1.45fr)",
);

const panelClass = (tone) => {
    if (tone === "judge") return "rounded-lg bg-rtds-gold-bright/20 shadow-lg";
    if (tone === "score") return "rounded-t-[2px] bg-[rgba(30,41,59,0.4)]";
    return "";
};

const valueClass = (tone) => {
    if (tone === "judge")
        return "font-rtds font-bold leading-none text-rtds-gold-bright";
    if (tone === "sub")
        return "font-rtds font-medium leading-none text-rtds-text-light";
    return "font-rtds font-semibold leading-none text-rtds-surface-light";
};

const valueStyle = (tone) => {
    if (tone === "judge") {
        return { fontSize: scaledFontSize(1.8, 16, 48) };
    }
    if (tone === "sub") {
        return { fontSize: scaledFontSize(1.7, 14, 46) };
    }

    return { fontSize: scaledFontSize(2.5, 16, 60) };
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
    <div class="flex h-full min-h-0 w-full min-w-0 flex-col">
        <div
            class="grid h-[67px] w-full shrink-0 items-end border-b border-rtds-divider/30"
            :style="{ gridTemplateColumns: gridColumns }"
        >
            <span
                v-for="column in columns"
                :key="column.key"
                class="min-w-0 overflow-hidden text-ellipsis whitespace-nowrap text-center font-rtds font-normal uppercase leading-9"
                :class="headerClass(column.tone)"
                :style="{ fontSize: scaledFontSize(1.25, 10, 28) }"
            >
                {{ column.label }}
            </span>
        </div>

        <div
            v-for="(row, index) in rows"
            :key="index"
            class="grid min-h-[54px] w-full min-w-0 flex-1 items-stretch border-b border-rtds-divider/20"
            :style="{ gridTemplateColumns: gridColumns }"
        >
            <div
                v-for="column in columns"
                :key="column.key"
                class="flex min-h-0 min-w-0 items-center justify-center overflow-hidden px-1 sm:px-2"
                :class="panelClass(column.tone)"
            >
                <span
                    class="min-w-0 whitespace-nowrap text-center"
                    :class="[
                        valueClass(column.tone),
                        column.key === 'judge'
                            ? ''
                            : scoreStateClass(row, column.key),
                    ]"
                    :style="valueStyle(column.tone)"
                >
                    {{ column.key === "judge" ? judgeLabel(row, index) : format(row[column.key]) }}
                </span>
            </div>
        </div>
    </div>
</template>
