<script setup>
import { computed } from "vue";

/**
 * جدول امتیاز Double — طرح: **یک سمت** (یک ورزشکار).
 * ستون‌ها = داوران (J1..Jn)، سطرها = دسته‌های نمره (ACCURACY / EOE / S&P / R&P / PRESENTATION).
 *
 * صفحه دو بار رندر می‌کند (سمت چپ `side="chung"`، سمت راست `side="hong"`) کنار هم.
 * - `side="hong"` → آینه: برچسب‌ها سمت راست، ترتیب داوران برعکس (Jn..J1)، رنگ برچسب قرمز.
 * - `showSubScores=false` → سطرهای ریزنمرات (EOE/S&P/R&P) مخفی می‌شوند؛ عرض ستون‌ها ثابت می‌ماند.
 *
 * داده همیشه در **ترتیب صندلی (J1..Jn)** می‌آید؛ آینه‌سازی داخل کامپوننت انجام می‌شود.
 */
const props = defineProps({
    side: { type: String, default: "chung" }, // 'chung' (آبی/چپ) | 'hong' (قرمز/راست)
    showSubScores: { type: Boolean, default: true },
    judgeCount: { type: Number, default: 5 }, // حالت خالی
    judges: { type: Array, default: () => [] }, // [{ label?, accuracy, eoe, sp, rp, presentation }]
});

const isHong = computed(() => props.side === "hong");

const rows = computed(() =>
    props.judges.length
        ? props.judges
        : Array.from({ length: props.judgeCount }, () => ({})),
);

/** برای سمت راست، ترتیب صندلی‌ها برعکس می‌شود تا برچسب و نمره جفت بمانند. */
const seatRows = computed(() =>
    isHong.value ? [...rows.value].reverse() : rows.value,
);

const judgeColumns = computed(() =>
    `repeat(${seatRows.value.length}, minmax(0, 1fr))`,
);
const judgeFontScale = computed(() => 7 / Math.max(seatRows.value.length, 1));
const scaledFontSize = (baseVw, minPx, maxPx) =>
    `clamp(${minPx}px, calc(${baseVw}vw * ${judgeFontScale.value}), ${maxPx}px)`;
const labelColumnStyle = { flex: "0 0 clamp(5.5rem, 22%, 8.25rem)" };

const categories = computed(() => [
    { key: "accuracy", label: "ACCURACY", score: true },
    ...(props.showSubScores
        ? [
              { key: "eoe", label: "EOE", score: false },
              { key: "sp", label: "S&P", score: false },
              { key: "rp", label: "R&P", score: false },
          ]
        : []),
    { key: "presentation", label: "PRESENTATION", score: true },
]);

const judgeLabel = (row, index) => row.label ?? `J${index + 1}`;

const format = (value) => {
    if (value === null || value === undefined || value === "") return "—";
    return typeof value === "number" && Number.isInteger(value)
        ? value.toFixed(1)
        : String(value);
};

const isRejected = (row, key) => {
    const state =
        row.acceptance?.[key] ??
        row.accepted?.[key] ??
        row.score_status?.[key];

    return (
        state === false ||
        state === "rejected" ||
        row[key + "_accepted"] === false ||
        row.status === "rejected"
    );
};

const scoreStateClass = (row, key) =>
    isRejected(row, key)
        ? "!text-rtds-red line-through decoration-2"
        : "!text-white";

const sideLabelClass = computed(() =>
    isHong.value ? "text-rtds-red" : "text-rtds-blue",
);

const placeClass = (alignSide) => {
    // alignSide: برچسب‌ها در سمت «بیرون» جدول‌اند
    const onLeft = alignSide === "left";
    return onLeft ? "justify-end text-right" : "justify-start text-left";
};

const reverseClass = computed(() => (isHong.value ? "flex-row-reverse" : ""));
</script>

<template>
    <div class="flex h-full min-h-0 w-full min-w-0 flex-col gap-2">
        <!-- نوار داوران (فقط بالای ستون‌های اعداد) -->
        <div class="flex h-[60px] w-full shrink-0 gap-2" :class="reverseClass">
            <div
                class="shrink-0"
                :style="labelColumnStyle"
            />
            <div
                class="grid min-w-0 flex-1 items-center gap-x-1 bg-rtds-gold-bright/20 px-1 sm:px-2"
                :style="{ gridTemplateColumns: judgeColumns }"
            >
                <span
                    v-for="(row, index) in seatRows"
                    :key="index"
                    class="min-w-0 whitespace-nowrap text-center font-rtds font-bold leading-none text-rtds-gold-bright"
                    :style="{ fontSize: scaledFontSize(1.4, 12, 40) }"
                >
                    {{ judgeLabel(row, index) }}
                </span>
            </div>
        </div>

        <!-- سطرهای نمره -->
        <div
            v-for="category in categories"
            :key="category.key"
            class="flex min-h-[55px] w-full min-w-0 flex-1 gap-2"
            :class="reverseClass"
        >
            <div
                class="flex min-w-0 shrink-0 items-center overflow-hidden"
                :class="placeClass(isHong ? 'right' : 'left')"
                :style="labelColumnStyle"
            >
                <span
                    class="overflow-hidden text-ellipsis whitespace-nowrap font-rtds font-normal uppercase leading-tight"
                    :class="category.score ? sideLabelClass : 'text-rtds-text-light'"
                    :style="{ fontSize: scaledFontSize(1, 10, 22) }"
                >
                    {{ category.label }}
                </span>
            </div>

            <div
                class="grid min-w-0 flex-1 items-center gap-x-1 px-1"
                :class="
                    category.score
                        ? 'rounded-t-[2px] bg-[rgba(30,41,59,0.4)]'
                        : ''
                "
                :style="{ gridTemplateColumns: judgeColumns }"
            >
                <span
                    v-for="(row, index) in seatRows"
                    :key="index"
                    class="min-w-0 whitespace-nowrap text-center"
                    :class="[
                        category.score
                            ? 'font-rtds font-semibold leading-none'
                            : 'font-rtds font-medium leading-none',
                        scoreStateClass(row, category.key),
                    ]"
                    :style="{
                        fontSize: category.score
                            ? scaledFontSize(2, 14, 50)
                            : scaledFontSize(1.5, 12, 40),
                    }"
                >
                    {{ format(row[category.key]) }}
                </span>
            </div>
        </div>
    </div>
</template>
