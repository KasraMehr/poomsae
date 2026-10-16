<script setup>
import { computed } from "vue";
import { medalSymbol } from "../../../Shared/Services/symbols";

/**
 * ردیف جدول چهار نفر برتر — طرح TOP-4-TABLE:
 * نشان رتبه · پرچم+کد کشور · بلاک رنگیِ نام که نمرهٔ نهایی داخل همان بلاک است.
 *
 * - نفر اول بزرگ‌تر و بقیه به ترتیب کوچک‌تر: ارتفاع ردیف، سایز نشان،
 *   فونت نام/نمره، رنگ بلاک و رنگ نمره همگی به تفکیک رتبهٔ ۱..۴ در طرح‌اند.
 * - رنگ نمره: رتبهٔ ۱ طلایی (#FFCC00)، بقیه روشن (#F1F5F9).
 * - نشان‌ها از همان asset های مدال پروژه می‌آیند (medal-1..5.webp).
 */
const props = defineProps({
    rank: { type: [Number, String], required: true },
    name: { type: String, default: "" },
    country: { type: String, default: "" }, // فقط برای alt پرچم
    countryCode: { type: String, default: "" }, // مثل IRI
    flagUrl: { type: String, default: "" },
    score: { type: [Number, String], default: null },
});

/** ابعاد طرح به تفکیک رتبه؛ رتبهٔ ۴ به بعد از اندازهٔ ردیفٔ ۴ استفاده می‌کند. */
const SIZE_BY_RANK = {
    1: {
        row: "h-[150px]",
        badge: "h-[150px] w-[150px]",
        code: "text-[64px]",
        name: "text-[84px]",
        score: "text-[84px]",
        bar: "bg-[rgba(201,168,0,0.45)]",
        scoreColor: "text-rtds-gold",
    },
    2: {
        row: "h-[130px]",
        badge: "h-[116px] w-[129px]",
        code: "text-[50px]",
        name: "text-[74px]",
        score: "text-[80px]",
        bar: "bg-[#64748B]",
        scoreColor: "text-rtds-surface-light",
    },
    3: {
        row: "h-[110px]",
        badge: "h-[110px] w-[113px]",
        code: "text-[50px]",
        name: "text-[64px]",
        score: "text-[70px]",
        bar: "bg-[#6B442D]",
        scoreColor: "text-rtds-surface-light",
    },
    4: {
        row: "h-[90px]",
        badge: "h-[90px] w-[90px]",
        code: "text-[50px]",
        name: "text-[54px]",
        score: "text-[60px]",
        bar: "bg-rtds-bg",
        scoreColor: "text-rtds-surface-light",
    },
};

const size = computed(() => SIZE_BY_RANK[Number(props.rank)] ?? SIZE_BY_RANK[4]);

/** نشان شش‌ضلعی رتبه از map دارایی‌های پروژه؛ رتبهٔ خارج از ۱..۵ نشان نمی‌گیرد. */
const badgeUrl = computed(() => medalSymbol(Number(props.rank)));

const hasCountry = computed(() => Boolean(props.flagUrl || props.countryCode));

const formattedScore = computed(() =>
    props.score === null || props.score === undefined || props.score === ""
        ? "—"
        : String(props.score),
);
</script>

<template>
    <div
        dir="ltr"
        class="flex w-full items-stretch font-rtds leading-none"
        :class="size.row"
    >
        <!-- جعبهٔ نشان رتبه؛ خود نشان با رتبه کوچک می‌شود -->
        <div
            class="flex w-[150px] shrink-0 items-center justify-center bg-rtds-bg"
        >
            <img
                v-if="badgeUrl"
                :src="badgeUrl"
                :alt="`رتبهٔ ${rank}`"
                class="shrink-0 object-contain"
                :class="size.badge"
            />
        </div>

        <!-- پرچم + کد کشور -->
        <template v-if="hasCountry">
            <div class="w-[150px] shrink-0 overflow-hidden">
                <img
                    v-if="flagUrl"
                    :src="flagUrl"
                    :alt="country || countryCode"
                    class="h-full w-full object-cover"
                />
                <span
                    v-else
                    class="flex h-full w-full items-center justify-center bg-rtds-bg-elevated text-2xl font-bold text-white"
                >
                    {{ countryCode }}
                </span>
            </div>
            <div
                v-if="countryCode"
                class="flex w-[101px] shrink-0 items-center justify-center bg-white/[0.11] font-bold text-white"
                :class="size.code"
            >
                {{ countryCode }}
            </div>
        </template>

        <!-- بلاک رنگی نام + نمرهٔ نهایی داخل همان بلاک -->
        <div
            class="flex min-w-0 flex-1 items-center pl-[25px]"
            :class="size.bar"
        >
            <p
                class="min-w-0 flex-1 truncate font-bold uppercase text-white"
                :class="size.name"
            >
                {{ name }}
            </p>
            <span
                class="shrink-0 pr-[20px] text-right font-semibold tabular-nums"
                :class="[size.score, size.scoreColor]"
                >{{ formattedScore }}</span
            >
        </div>
    </div>
</template>
