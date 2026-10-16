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

/** عرض ستون‌ها ثابت است تا با مخفی شدن ریزنمرات، بقیه تغییر نکنند. */
const columnWidth = { judge: "121px", accuracy: "81px", sub: "64px", presentation: "80px" };
const widthOf = (column) =>
    column.tone === "sub" ? columnWidth.sub : columnWidth[column.key];

const panelClass = (tone) => {
    if (tone === "judge") return "rounded-lg bg-rtds-gold-bright/20 shadow-lg";
    if (tone === "score") return "rounded-t-[2px] bg-[rgba(30,41,59,0.4)]";
    return "";
};

const valueClass = (tone) => {
    if (tone === "judge")
        return "font-rtds text-[36px] font-bold leading-10 text-rtds-gold-bright";
    if (tone === "sub")
        return "font-rtds text-[36px] font-medium leading-10 text-rtds-text-muted";
    return "font-rtds text-[50px] font-semibold leading-10 text-rtds-surface-light";
};

const headerClass = (tone) =>
    tone === "judge"
        ? "text-rtds-gold-bright"
        : "text-rtds-text-light";
</script>

<template>
    <div class="min-w-0">
        <!-- Header: سلول‌ها content-size و با justify-between روی همان عرض بدنه -->
        <div
            class="flex h-[67px] items-end justify-between border-b border-rtds-divider/30"
        >
            <span
                v-for="column in columns"
                :key="column.key"
                class="whitespace-nowrap font-rtds text-xl font-normal uppercase leading-9"
                :class="headerClass(column.tone)"
            >
                {{ column.label }}
            </span>
        </div>

        <!-- Body -->
        <div class="flex">
            <!-- ستون داوران -->
            <div
                class="mr-2.5 flex flex-col gap-[26px] px-10 py-[30px]"
                :class="panelClass('judge')"
                :style="{ width: columnWidth.judge, minWidth: '120px' }"
            >
                <div
                    v-for="(row, index) in rows"
                    :key="index"
                    class="flex h-10 items-center justify-center"
                    :class="valueClass('judge')"
                >
                    {{ judgeLabel(row, index) }}
                </div>
            </div>

            <!-- ستون‌های امتیاز -->
            <div class="flex gap-4">
                <div
                    v-for="column in columns.filter((c) => c.tone !== 'judge')"
                    :key="column.key"
                    class="flex flex-col gap-[26px] px-2 py-[30px]"
                    :class="panelClass(column.tone)"
                    :style="{ width: widthOf(column) }"
                >
                    <div
                        v-for="(row, index) in rows"
                        :key="index"
                        class="flex h-10 items-center justify-center"
                        :class="valueClass(column.tone)"
                    >
                        {{ format(row[column.key]) }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
