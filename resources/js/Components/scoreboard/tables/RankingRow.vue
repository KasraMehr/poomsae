<script setup>
import { computed } from "vue";

/**
 * ردیف جدول رنکینگ — طرح RANKING-TABLE:
 * جعبهٔ رتبه · پرچم+کد کشور · نام (بلاک تیره) · دو ستون نمرهٔ راند (R-1 | R-2).
 *
 * - ارتفاع ردیف و فونت‌ها طبق طرح بر اساس رتبه کاهش می‌یابند (۱۳۰/۱۱۰/۹۰/۷۵/۷۰).
 * - نمره‌ها بیرون از نام نیستند: هر دو ستون داخل بلاک تیرهٔ نام می‌نشینند و
 *   ستون دوم divider سمت چپ دارد (خط روشن بین R-1 و R-2 مثل طرح).
 * - تعداد نمره‌ها ثابت ۲ است (تصمیم کاربر)؛ مقدار خالی با «—» نمایش داده می‌شود.
 * - وقتی نه پرچمی هست نه کدی، کل بلوک کشور حذف می‌شود (null-safe برای placeholderها).
 */
const props = defineProps({
    rank: { type: [Number, String], required: true },
    name: { type: String, default: "" },
    country: { type: String, default: "" }, // فقط برای alt پرچم
    countryCode: { type: String, default: "" }, // مثل IRI
    flagUrl: { type: String, default: "" },
    /** نمرهٔ کل هر راند: [R-1, R-2] */
    roundScores: { type: Array, default: () => [] },
});

/** ابعاد طرح به تفکیک رتبه؛ رتبهٔ ۵ به بعد از اندازهٔ ردیفٔ ۵ استفاده می‌کند. */
const SIZE_BY_RANK = {
    1: { row: "h-[130px]", name: "text-[84px]", score: "text-[64px]" },
    2: { row: "h-[110px]", name: "text-[74px]", score: "text-[55px]" },
    3: { row: "h-[90px]", name: "text-[64px]", score: "text-[50px]" },
    4: { row: "h-[75px]", name: "text-[54px]", score: "text-[45px]" },
    5: { row: "h-[70px]", name: "text-[44px]", score: "text-[42px]" },
};

const size = computed(() => SIZE_BY_RANK[Number(props.rank)] ?? SIZE_BY_RANK[5]);

const hasCountry = computed(() => Boolean(props.flagUrl || props.countryCode));

const scores = computed(() => [
    props.roundScores[0] ?? null,
    props.roundScores[1] ?? null,
]);

const formatScore = (value) =>
    value === null || value === undefined || value === ""
        ? "—"
        : String(value);
</script>

<template>
    <div
        dir="ltr"
        class="flex w-full items-stretch font-rtds leading-none"
        :class="size.row"
    >
        <!-- جعبهٔ رتبهٔ طرح: «N.» روی زمینهٔ تیره -->
        <div
            class="flex w-[54px] shrink-0 items-center justify-center bg-rtds-bg text-[51px] font-semibold text-white"
        >
            {{ rank }}.
        </div>

        <!-- پرچم + کد کشور -->
        <template v-if="hasCountry">
            <div class="w-[128px] shrink-0 overflow-hidden">
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
                class="flex w-[101px] shrink-0 items-center justify-center bg-white/[0.11] text-[50px] font-bold text-white"
            >
                {{ countryCode }}
            </div>
        </template>

        <!-- بلاک نام + دو ستون نمرهٔ راند (عرض ثابت تا divider هدر و ردیف‌ها یکی بایستد) -->
        <div
            class="flex min-w-0 flex-1 items-center gap-[6.55px] bg-rtds-bg pl-[25px]"
        >
            <p
                class="min-w-0 flex-1 truncate font-bold uppercase text-white"
                :class="size.name"
            >
                {{ name }}
            </p>
            <span
                class="w-[142px] shrink-0 text-center font-semibold tabular-nums text-rtds-gold"
                :class="size.score"
                >{{ formatScore(scores[0]) }}</span
            >
            <span
                class="w-[142px] shrink-0 border-l-[1.64px] border-rtds-text-light text-center font-semibold tabular-nums text-rtds-gold"
                :class="size.score"
                >{{ formatScore(scores[1]) }}</span
            >
        </div>
    </div>
</template>
