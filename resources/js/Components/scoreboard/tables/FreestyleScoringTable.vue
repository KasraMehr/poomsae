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
});

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

/** زیرستون‌ها فقط وقتی showSubScores روشن است و داده دارند نمایش داده می‌شوند. */
const partsOf = (row, key) => {
    const parts = row[`${key}Parts`];
    return props.showSubScores && Array.isArray(parts) && parts.length
        ? parts
        : [row[key]];
};

/** عرض ستون‌ها ثابت است: با تقسیم/ادغام زیرستون‌ها، ستون‌ها جابه‌جا نمی‌شوند. */
const widths = { judge: "59px", accuracy: "268px", presentation: "432px" };
</script>

<template>
    <div class="w-[759px] min-w-0">
        <!-- Header: سه هدر ثابت، هم‌عرض با ستون‌های بدنه -->
        <div class="flex h-[42px] items-end">
            <div
                class="flex h-9 items-center justify-center bg-rtds-gold-bright/20"
                :style="{ width: widths.judge }"
            >
                <span
                    class="whitespace-nowrap font-rtds text-xl font-normal uppercase leading-9 text-rtds-gold-bright"
                    >JUDGE</span
                >
            </div>
            <div
                class="flex h-9 items-center justify-center bg-rtds-border"
                :style="{ width: widths.accuracy }"
            >
                <span
                    class="whitespace-nowrap font-rtds text-xl font-normal uppercase leading-9 text-rtds-text-light"
                    >ACCURACY</span
                >
            </div>
            <div
                class="flex h-9 items-center justify-center bg-rtds-blue-soft/40"
                :style="{ width: widths.presentation }"
            >
                <span
                    class="whitespace-nowrap font-rtds text-xl font-normal uppercase leading-9 text-rtds-text-light"
                    >PRESENTATION</span
                >
            </div>
        </div>

        <!-- Rows -->
        <div
            v-for="(row, index) in rows"
            :key="index"
            class="flex h-[77px] items-center border-b border-white/5"
        >
            <div
                class="flex shrink-0 items-center justify-center"
                :style="{ width: widths.judge }"
            >
                <span class="font-rtds text-2xl font-semibold leading-7 text-rtds-gold-bright">
                    {{ judgeLabel(row, index) }}
                </span>
            </div>

            <div
                class="flex shrink-0 items-center justify-center gap-4 px-6"
                :style="{ width: widths.accuracy }"
            >
                <span
                    v-for="(value, partIndex) in partsOf(row, 'accuracy')"
                    :key="partIndex"
                    class="flex-1 text-center font-rtds text-[32px] font-medium leading-[52px] text-rtds-text-light"
                >
                    {{ format(value) }}
                </span>
            </div>

            <div
                class="flex shrink-0 items-center justify-center gap-2 px-6"
                :style="{ width: widths.presentation }"
            >
                <span
                    v-for="(value, partIndex) in partsOf(row, 'presentation')"
                    :key="partIndex"
                    class="flex-1 text-center font-rtds text-[32px] font-semibold leading-9 text-rtds-text-light"
                >
                    {{ format(value) }}
                </span>
            </div>
        </div>
    </div>
</template>
