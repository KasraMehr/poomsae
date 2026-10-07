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

const labelWidth = "132px";
const labelGap = "15px"; // فاصلهٔ ستون برچسب تا ناحیهٔ اعداد

const sideLabelClass = computed(() =>
    isHong.value ? "text-rtds-red" : "text-rtds-blue",
);

const placeClass = (alignSide) => {
    // alignSide: برچسب‌ها در سمت «بیرون» جدول‌اند
    const onLeft = alignSide === "left";
    return [
        onLeft ? "mr-[15px] justify-end text-right" : "ml-[15px] justify-start text-left",
    ];
};

const reverseClass = computed(() => (isHong.value ? "flex-row-reverse" : ""));
</script>

<template>
    <div class="flex w-[580px] flex-col gap-4">
        <!-- نوار داوران (فقط بالای ستون‌های اعداد) -->
        <div class="flex w-full" :class="reverseClass">
            <div
                class="shrink-0"
                :style="{ width: labelWidth, marginLeft: isHong ? labelGap : undefined, marginRight: isHong ? undefined : labelGap }"
            />
            <div
                class="flex h-[60px] flex-1 items-center gap-[15px] bg-rtds-gold-bright/20 px-2.5"
            >
                <span
                    v-for="(row, index) in seatRows"
                    :key="index"
                    class="flex-1 text-center font-rtds text-[36px] font-bold leading-10 text-rtds-gold-bright"
                >
                    {{ judgeLabel(row, index) }}
                </span>
            </div>
        </div>

        <!-- سطرهای نمره -->
        <div
            v-for="category in categories"
            :key="category.key"
            class="flex w-full"
            :class="reverseClass"
        >
            <div
                class="flex shrink-0 items-center"
                :class="placeClass(isHong ? 'right' : 'left')"
                :style="{ width: labelWidth }"
            >
                <span
                    class="whitespace-nowrap font-rtds text-[25px] font-normal uppercase leading-9"
                    :class="category.score ? sideLabelClass : 'text-rtds-text-light'"
                >
                    {{ category.label }}
                </span>
            </div>

            <div
                class="flex h-[55px] flex-1 gap-6 px-2"
                :class="
                    category.score
                        ? 'rounded-t-[2px] bg-[rgba(30,41,59,0.4)]'
                        : ''
                "
            >
                <span
                    v-for="(row, index) in seatRows"
                    :key="index"
                    class="flex flex-1 items-center justify-center"
                    :class="
                        category.score
                            ? 'font-rtds text-[50px] font-semibold leading-10 text-rtds-surface-light'
                            : 'font-rtds text-[36px] font-medium leading-10 text-rtds-text-muted'
                    "
                >
                    {{ format(row[category.key]) }}
                </span>
            </div>
        </div>
    </div>
</template>
