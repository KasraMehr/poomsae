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
    names: { type: Array, default: () => [] },
    country: { type: String, default: "" }, // فقط برای alt پرچم
    countryCode: { type: String, default: "" },
    flagUrl: { type: String, default: "" },
    size: { type: String, default: "md" }, // 'sm' | 'md' | 'lg' — سه سایز مستقل
    nameSizeStyle: { type: String, default: "" },
    nameFit: { type: Boolean, default: false },
    badgeSizeOverride: { type: String, default: "" },
    fill: { type: Boolean, default: false },
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
        bar: "h-[107px] gap-4 px-5",
        number: "h-[69px] w-[80px] text-[57px] leading-[1.1]",
        name: "text-[57px] leading-[1.1]",
    },

    xxl: {
        bar: "h-[131px] gap-5 px-6",
        number: "h-[84px] w-[98px] text-[70px] leading-[1.1]",
        name: "text-[85px] leading-[1.1]",
    },

    xxxl: {
        bar: "h-32 gap-6 px-7",
        number: "h-20 w-[108px] text-[72px] leading-[1.1]",
        name: "text-[90px] leading-[1.1]",
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
    `bar--${sideKey.value}`,
]);

const rootClasses = computed(() => {
    const anchor = isHong.value ? "items-end" : "items-start";
    if (props.fill && props.layout === "vertical") {
        return `flex h-full w-full min-h-0 flex-col ${anchor}`;
    }

    switch (props.layout) {
        case "name-only":
            return "inline-flex";
        case "horizontal":
            return isHong.value
                ? "inline-flex flex-row-reverse items-center"
                : "inline-flex flex-row items-center";
        case "vertical-under":
            return `flex w-full flex-col ${anchor}`;
        case "vertical":
        default:
            return `inline-flex flex-col ${anchor}`;
    }
});

const showBadge = true;
const badgeSize = computed(() => {
    if (props.badgeSizeOverride) {
        return props.badgeSizeOverride;
    }

    if (props.layout === "horizontal") {
        return props.size;
    }

    const badgeSizes = {
        sm: "sm",
        md: "sm",
        lg: "md",
        xl: "xl",
        xxl: "xxl",
        xxxl: "xxl",
    };

    return badgeSizes[props.size] ?? "sm";
});

const badgeProps = computed(() => ({
    code: props.countryCode || "IRI",
    country: props.country,
    flagUrl: props.flagUrl,
    flagPosition: isHong.value ? "right" : "left",
    size: badgeSize.value,
}));

const nameLength = (value) => Math.max(Array.from(value).length, 1);
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
        <span class="bar w-full" :class="[barClasses, { 'bar--grouped': names.length > 0, 'bar--fill': fill }]">
            <!-- <span
                v-if="number !== ''"
                class="grid shrink-0 place-items-center bg-rtds-blue-soft/40 font-rtds font-bold tabular-nums text-white"
                :class="sizeClasses[size]?.number ?? sizeClasses.md.number"
            >
                {{ number }}
            </span> -->

            <span
                v-if="names.length"
                class="bar__names min-w-0 flex-1 font-rtds font-bold uppercase text-white"
                :class="[
                    isHong ? 'text-right' : 'text-left',
                    `bar__names--${size}`,
                    { 'bar__names--fit': nameFit },
                    { 'bar__names--few': (fill || nameFit) && names.length > 0 && names.length <= 2 },
                    { 'bar__names--many': nameFit && names.length > 2 },
                    { 'bar__names--single': names.length === 1 },
                    { 'bar__names--mirrored': isHong && names.length > 2 },
                ]"
                :style="nameSizeStyle ? { fontSize: nameSizeStyle } : undefined"
            >
                <span
                    v-for="(memberName, index) in names"
                    :key="`${memberName}-${index}`"
                    class="bar__name"
                    :style="{ '--name-length': nameLength(memberName) }"
                >
                    {{ memberName }}
                </span>
            </span>
            <span
                v-else-if="name !== ''"
                class="min-w-0 flex-1 truncate font-rtds font-bold uppercase text-white"
                :class="[
                    sizeClasses[size]?.name ?? sizeClasses.md.name,
                    isHong ? 'text-right' : 'text-left',
                ]"
            >
                {{ name }}
            </span>
        </span>

        <!-- بج بعد از نوار: vertical-under (پایین نوار) -->
        <NationalityBadge
            v-if="showBadge && layout === 'vertical-under'"
            v-bind="badgeProps"
            class="shrink-0"
        />
    </span>
</template>

<style scoped>
.bar--grouped {
    height: auto;
    min-height: 0;
    padding-block: 0.35rem;
}

.bar--fill {
    display: flex;
    flex: 1 1 auto;
    min-height: 0;
    align-items: stretch;
}

.bar--fill.bar--grouped {
    padding-block: 0;
}

.bar--chung.bar--grouped {
    background: linear-gradient(
        90deg,
        var(--color-rtds-blue-dark) 32%,
        color-mix(in srgb, var(--color-rtds-blue-dark) 8%, transparent)
    );
}

.bar--hong.bar--grouped {
    background: linear-gradient(
        90deg,
        color-mix(in srgb, var(--color-rtds-error) 8%, transparent),
        var(--color-rtds-error) 68%
    );
}

.bar__names {
    display: flex;
    flex-flow: row wrap;
    align-content: center;
    container-type: inline-size;
    column-gap: clamp(0.5rem, 1.2vw, 1rem);
    row-gap: 0.12em;
    line-height: 1.08;
}

.bar__names--lg {
    font-size: clamp(1.5rem, min(8cqi, 11cqb), 4rem);
}

.bar__names--sm,
.bar__names--md {
    font-size: clamp(1rem, min(2vw, 3vh), 2rem);
}

.bar__names--mirrored {
    direction: rtl;
}

.bar__names--single {
    justify-content: flex-start;
}

.bar__names--single.bar__names--lg {
    font-size: clamp(1.5rem, min(8cqi, 11cqb), 4rem);
}

.bar__names--single.bar__names--sm,
.bar__names--single.bar__names--md {
    font-size: clamp(1rem, min(2vw, 3vh), 2rem);
}

.bar__names--few.bar__names--lg {
    font-size: clamp(1.75rem, min(10cqi, 14cqb), 4.5rem);
}

.bar__names--few.bar__names--md {
    font-size: clamp(1.35rem, min(3vw, 5vh), 2.5rem);
}

.bar__names--fit.bar__names--few .bar__name {
    font-size: min(1.08em, calc(150cqi / var(--name-length)));
}

.bar__names--fit.bar__names--many .bar__name {
    font-size: min(0.9em, calc(126cqi / var(--name-length)));
}

.bar__name {
    box-sizing: border-box;
    flex: 0 1 auto;
    min-width: 0;
    max-width: 100%;
    padding: 0.08em 0.22em;
    border-radius: 0.2em;
    background: rgb(255 255 255 / 10%);
    font-size: min(1em, calc(140cqi / var(--name-length)));
    line-height: 1.08;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
</style>
