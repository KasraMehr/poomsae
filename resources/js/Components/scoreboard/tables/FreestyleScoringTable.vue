<script setup>
import { computed } from "vue";

/**
 * جدول امتیاز Freestyle — طرح: سطر = داور، سه ستون JUDGE / ACCURACY / PRESENTATION.
 *
 * - `showSubScores=true` → ستون ACCURACY به ۴ و PRESENTATION به ۶ زیرستون تقسیم می‌شود
 *   (زیرستون‌ها فعلاً بدون هدرند؛ نام/منبع داده‌شان بعداً مشخص می‌شود).
 * - `showSubScores=false` → همان جدول سه‌ستونه؛ عرض ستون‌ها تغییری نمی‌کند.
 *
 * هدرها ثابت و انگلیسی‌اند. داده از `judges` می‌آید:
 * [{ label?, accuracy, presentation, accuracyParts?, presentationParts? }]
 */
const props = defineProps({
    showSubScores: { type: Boolean, default: true },
    judgeCount: { type: Number, default: 5 }, // حالت خالی
    judges: { type: Array, default: () => [] },
    accuracyCount: { type: Number, default: 4 },
    presentationCount: { type: Number, default: 6 },
    presentationLabel: { type: String, default: "PRESENTATION" },
});

const rows = computed(() =>
    props.judges.length
        ? props.judges
        : Array.from({ length: props.judgeCount }, () => ({})),
);

const judgeFontScale = computed(() => 7 / Math.max(rows.value.length, 1));
const scaledFontSize = (baseVw, minPx, maxPx) =>
    `clamp(${minPx}px, calc(${baseVw}vw * ${judgeFontScale.value}), ${maxPx}px)`;

const gridColumns = "minmax(3.25rem, 0.58fr) minmax(0, 1fr) minmax(0, 1.65fr)";

const judgeLabel = (row, index) => row.label ?? `J${index + 1}`;

const format = (value) => {
    if (value === null || value === undefined || value === "") return "—";
    return typeof value === "number" && Number.isInteger(value)
        ? value.toFixed(1)
        : String(value);
};

/** زیرستون‌ها فقط وقتی showSubScores روشن است و داده دارند نمایش داده می‌شوند. */
const partsOf = (row, key) => {
    const parts = row[`${key}Parts`];
    return props.showSubScores && Array.isArray(parts) && parts.length
        ? parts
        : [row[key]];
};

const valuesOf = (row, key, count) => {
    const parts = partsOf(row, key);

    return parts.length === 1 && count > 1 ? Array(count).fill(parts[0]) : parts;
};

const isRejected = (row, key, partIndex) => {
    const states = [
        row.acceptance?.[key]?.[partIndex],
        row.accepted?.[key]?.[partIndex],
        row.acceptance?.[key + "_components"]?.[partIndex],
        row.accepted?.[key + "_components"]?.[partIndex],
        row.acceptance?.[key + "_penalties"]?.[partIndex],
        row.accepted?.[key + "_penalties"]?.[partIndex],
        row.score_status?.[key + "_components"]?.[partIndex],
        row.score_status?.[key + "_penalties"]?.[partIndex],
        row[key + "_accepted"]?.[partIndex],
    ];
    const scoreState =
        row.acceptance?.[key] ??
        row.accepted?.[key] ??
        row.score_status?.[key];

    return (
        states.includes(false) ||
        states.includes("rejected") ||
        scoreState === false ||
        scoreState === "rejected" ||
        row.status === "rejected"
    );
};

const scoreStateClass = (row, key, partIndex) =>
    isRejected(row, key, partIndex)
        ? "!text-rtds-red line-through decoration-2"
        : "!text-white";
</script>

<template>
    <div class="flex h-full min-h-0 w-full min-w-0 flex-col">
        <!-- Header: سه هدر ثابت، هم‌عرض با ستون‌های بدنه -->
        <div
            class="grid h-[42px] w-full shrink-0 items-end"
            :style="{ gridTemplateColumns: gridColumns }"
        >
            <div
                class="flex min-w-0 items-center justify-center overflow-hidden bg-rtds-gold-bright/20"
            >
                <span
                    class="whitespace-nowrap font-rtds font-normal uppercase leading-9 text-rtds-gold-bright"
                    :style="{ fontSize: scaledFontSize(1.25, 10, 28) }"
                    >JUDGE</span
                >
            </div>
            <div
                class="flex min-w-0 items-center justify-center overflow-hidden bg-rtds-border"
            >
                <span
                    class="whitespace-nowrap font-rtds font-normal uppercase leading-9 text-rtds-text-light"
                    :style="{ fontSize: scaledFontSize(1.25, 10, 28) }"
                    >ACCURACY</span
                >
            </div>
            <div
                class="flex min-w-0 items-center justify-center overflow-hidden bg-rtds-blue-soft/40"
            >
                <span
                    class="whitespace-nowrap font-rtds font-normal uppercase leading-9 text-rtds-text-light"
                    :style="{ fontSize: scaledFontSize(1.25, 10, 28) }"
                    >{{ presentationLabel }}</span
                >
            </div>
        </div>

        <!-- Rows -->
        <div
            v-for="(row, index) in rows"
            :key="index"
            class="grid min-h-[58px] w-full min-w-0 flex-1 items-stretch border-b border-white/5"
            :style="{ gridTemplateColumns: gridColumns }"
        >
            <div
                class="flex min-w-0 items-center justify-center overflow-hidden"
            >
                <span
                    class="whitespace-nowrap font-rtds font-semibold leading-none text-rtds-gold-bright"
                    :style="{ fontSize: scaledFontSize(1.5, 14, 40) }"
                >
                    {{ judgeLabel(row, index) }}
                </span>
            </div>

            <div
                class="grid min-w-0 items-center gap-x-1 px-1 sm:px-2"
                :style="{ gridTemplateColumns: `repeat(${valuesOf(row, 'accuracy', accuracyCount).length}, minmax(0, 1fr))` }"
            >
                <span
                    v-for="(value, partIndex) in valuesOf(row, 'accuracy', accuracyCount)"
                    :key="partIndex"
                    class="min-w-0 whitespace-nowrap text-center font-rtds font-medium leading-none"
                    :class="scoreStateClass(row, 'accuracy', partIndex)"
                    :style="{ fontSize: scaledFontSize(1.7, 12, 38) }"
                >
                    {{ format(value) }}
                </span>
            </div>

            <div
                class="grid min-w-0 items-center gap-x-1 px-1 sm:px-2"
                :style="{ gridTemplateColumns: `repeat(${valuesOf(row, 'presentation', presentationCount).length}, minmax(0, 1fr))` }"
            >
                <span
                    v-for="(value, partIndex) in valuesOf(row, 'presentation', presentationCount)"
                    :key="partIndex"
                    class="min-w-0 whitespace-nowrap text-center font-rtds font-semibold leading-none"
                    :class="scoreStateClass(row, 'presentation', partIndex)"
                    :style="{ fontSize: scaledFontSize(1.7, 12, 38) }"
                >
                    {{ format(value) }}
                </span>
            </div>
        </div>
    </div>
</template>
