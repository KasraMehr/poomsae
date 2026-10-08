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

const sizeClasses = {
    sm: {
        bar: "h-8 gap-1.5 px-2.5",
        number: "h-6 w-9 text-[15px] leading-[1.15]",
        name: "text-[15px] leading-[1.15]",
    },

    md: {
        bar: "h-12 gap-2.5 px-4",
        number: "h-9 w-14 text-[28px] leading-[1.1]",
        name: "text-[28px] leading-[1.1]",
    },

    lg: {
        bar: "h-16 gap-3 px-5",
        number: "h-12 w-[68px] text-[40px] leading-[1.1]",
        name: "text-[40px] leading-[1.1]",
    },

    xl: {
        bar: "h-20 gap-4 px-5",
        number: "h-14 w-[82px] text-[50px] leading-[1.1]",
        name: "text-[50px] leading-[1.1]",
    },

    xxl: {
        bar: "h-24 gap-5 px-6",
        number: "h-16 w-[94px] text-[60px] leading-[1.1]",
        name: "text-[60px] leading-[1.1]",
    },

    xxxl: {
        bar: "h-32 gap-6 px-7",
        number: "h-20 w-[108px] text-[72px] leading-[1.1]",
        name: "text-[72px] leading-[1.1]",
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
        sm: "border-l-[3px] border-l-rtds-blue",
        md: "border-l-4 border-l-rtds-blue",
        lg: "border-l-[5px] border-l-rtds-blue",
        xl: "border-l-[5px] border-l-rtds-blue",
        xxl: "border-l-[6px] border-l-rtds-blue",
        xxxl: "border-l-[7px] border-l-rtds-blue",
    },

    hong: {
        sm: "border-r-[3px] border-r-rtds-red",
        md: "border-r-4 border-r-rtds-red",
        lg: "border-r-[5px] border-r-rtds-red",
        xl: "border-r-[5px] border-r-rtds-red",
        xxl: "border-r-[6px] border-r-rtds-red",
        xxxl: "border-r-[7px] border-r-rtds-red",
    },

    neutral: {
        sm: "border-l-[3px] border-l-rtds-border-strong",
        md: "border-l-4 border-l-rtds-border-strong",
        lg: "border-l-[5px] border-l-rtds-border-strong",
        xl: "border-l-[5px] border-l-rtds-border-strong",
        xxl: "border-l-[6px] border-l-rtds-border-strong",
        xxxl: "border-l-[7px] border-l-rtds-border-strong",
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
const badgeSize = computed(() => {
    if (props.layout === "horizontal") {
        return props.size;
    }

    const badgeSizes = {
        sm: "sm",
        md: "sm",
        lg: "md",
        xl: "lg",
        xxl: "xl",
        xxxl: "xxl",
    };

    return badgeSizes[props.size] ?? "sm";
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
            <!-- <span
                v-if="number !== ''"
                class="grid shrink-0 place-items-center bg-rtds-blue-soft/40 font-rtds font-bold tabular-nums text-white"
                :class="sizeClasses[size]?.number ?? sizeClasses.md.number"
                >{{ number }}</span
            > -->
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
