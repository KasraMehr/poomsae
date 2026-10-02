<script setup>
import { computed } from "vue";
import NationalityBadge from "./NationalityBadge.vue";

/**
 * ورزشکار: «نوار نام» رنگی (شماره + نام) + بج NationalityBadge.
 *
 * - چهار چیدمان: name-only | horizontal | vertical | vertical-under
 * - دو سمت آینه‌ای: chung (آبی، لنگر چپ) / hong (قرمز، لنگر راست)؛ بدون سمت = خنثی (خاکستری، چپ)
 * - رنگ‌ها از توکن rtds: آبی #0044CC (blue-dark) با حاشیهٔ آبی روشن (blue)؛
 *   قرمز #EF4444 (error) با حاشیهٔ قرمز روشن (red) — طبق توافق قرمز هم حاشیه دارد.
 * - جعبهٔ شماره برای هر دو سمت: rtds-blue-soft با شفافیت ~۴۰٪ (مثل طرح ۴۲٪).
 * - عکس ورزشکار حذف شد (تصمیم تیم، تا اطلاع بعدی).
 * - بدون countryCode/flagUrl بج نمایش داده نمی‌شود (null-safe تا رسیدن contract).
 */
const props = defineProps({
    layout: {
        type: String,
        default: "horizontal", // 'name-only' | 'horizontal' | 'vertical' | 'vertical-under'
    },
    number: { type: [String, Number], default: "" },
    side: { type: String, default: "" }, // 'chung' | 'hong' | '' (خنثی)
    name: { type: String, default: "" },
    country: { type: String, default: "" }, // فقط برای alt پرچم
    countryCode: { type: String, default: "" },
    flagUrl: { type: String, default: "" },
    size: { type: String, default: "md" }, // 'sm' | 'md' | 'lg' — سه سایز مستقل
});

const isHong = computed(() => props.side === "hong");

// سه سایز مستقل — ارتفاع نوار = ارتفاع بج همان سایز (lg دقیقاً ابعاد طرح)
const sizeClasses = {
    lg: {
        bar: "gap-[18px] px-6 py-6",
        number: "h-[89px] w-[103px] text-[73px] leading-[1.1]",
        name: "text-[73px] leading-[1.1]",
    },
    md: {
        bar: "gap-2.5 px-4 py-3",
        number: "h-9 w-14 text-[32px] leading-[1.1]",
        name: "text-[32px] leading-[1.1]",
    },
    sm: {
        bar: "gap-1.5 px-2.5 py-1",
        number: "h-5 w-9 text-[15px] leading-[1.15]",
        name: "text-[15px] leading-[1.15]",
    },
};

const barBackgrounds = {
    chung: "bg-rtds-blue-dark", // #0044CC
    hong: "bg-rtds-error", // #EF4444
    neutral: "bg-rtds-bg-elevated",
};

// حاشیهٔ ۶/۴/۳px در لبهٔ بیرونی سمت بازیکن (آبی: چپ، قرمز: راست)
const barBorders = {
    chung: {
        lg: "border-l-[6px] border-l-rtds-blue",
        md: "border-l-4 border-l-rtds-blue",
        sm: "border-l-[3px] border-l-rtds-blue",
    },
    hong: {
        lg: "border-r-[6px] border-r-rtds-red",
        md: "border-r-4 border-r-rtds-red",
        sm: "border-r-[3px] border-r-rtds-red",
    },
    neutral: {
        lg: "border-l-[6px] border-l-rtds-border-strong",
        md: "border-l-4 border-l-rtds-border-strong",
        sm: "border-l-[3px] border-l-rtds-border-strong",
    },
};

const sideKey = computed(() =>
    props.side === "chung" || props.side === "hong" ? props.side : "neutral",
);

const barClasses = computed(() => [
    "inline-flex min-w-0 items-center border-solid",
    sizeClasses[props.size]?.bar ?? sizeClasses.md.bar,
    barBackgrounds[sideKey.value],
    barBorders[sideKey.value][props.size] ?? barBorders[sideKey.value].md,
]);

const rootClasses = computed(() => {
    const anchor = isHong.value ? "items-end" : "items-start";
    switch (props.layout) {
        case "name-only":
            return "inline-flex";
        case "horizontal":
            return isHong.value
                ? "inline-flex flex-row-reverse items-center"
                : "inline-flex flex-row items-center";
        case "vertical-under":
            return `inline-flex flex-col ${anchor}`;
        case "vertical":
        default:
            return `inline-flex flex-col ${anchor}`;
    }
});

const showBadge = computed(() => Boolean(props.countryCode || props.flagUrl));

// در چیدمان‌های عمودی بج یک سایز کوچک‌تر می‌شود (نسبت طرح)
const badgeSize = computed(() => {
    if (props.layout === "horizontal") return props.size;
    return props.size === "lg" ? "md" : "sm";
});

const badgeProps = computed(() => ({
    code: props.countryCode,
    country: props.country,
    flagUrl: props.flagUrl,
    flagPosition: isHong.value ? "right" : "left",
    size: badgeSize.value,
}));
</script>

<template>
    <span dir="ltr" class="inline-flex align-middle" :class="rootClasses">
        <!-- بج قبل از نوار: vertical (بالا) و horizontal (کنار؛ برای hong آینه‌ای) -->
        <NationalityBadge
            v-if="
                showBadge &&
                layout !== 'vertical-under' &&
                layout !== 'name-only'
            "
            v-bind="badgeProps"
            class="shrink-0"
        />

        <!-- نوار نام (در name-only هم فقط نوار داریم) -->
        <span class="bar" :class="barClasses">
            <span
                v-if="number !== ''"
                class="grid shrink-0 place-items-center bg-rtds-blue-soft/40 font-rtds font-bold tabular-nums text-white"
                :class="sizeClasses[size]?.number ?? sizeClasses.md.number"
                >{{ number }}</span
            >
            <span
                v-if="name !== ''"
                class="min-w-0 truncate font-rtds font-bold uppercase text-white"
                :class="sizeClasses[size]?.name ?? sizeClasses.md.name"
                >{{ name }}</span
            >
        </span>

        <!-- بج بعد از نوار: vertical-under (پایین نوار) -->
        <NationalityBadge
            v-if="showBadge && layout === 'vertical-under'"
            v-bind="badgeProps"
            class="shrink-0"
        />
    </span>
</template>
