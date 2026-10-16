<script setup>
import { computed, useSlots } from "vue";

/**
 * بلوک امتیاز یک ورزشکار — سه آیتم (Accuracy، Presentation، Total) به‌صورت عمودی.
 *
 * صفحهٔ Result دو ورزشکار را روبه‌روی هم نشان می‌دهد و بلوک قرمز **آینه**ٔ بلوک آبی است
 * (برچسب‌ها به سمت داخل تصویر هم‌راستا). این کامپوننت آینه‌سازی و رنگ را با `side` می‌دهد
 * تا صفحه مجبور نباشد برای هر ورزشکار چیدمان جدا بنویسد.
 *
 * آیتم‌ها از slot می‌آیند (`ScoreItem`) تا خود این کامپوننت مجبور نباشد بداند سقف هر
 * امتیاز چقدر است؛ سقف‌ها و برچسب‌ها در صفحهٔ مصرف‌کننده تعیین می‌شوند.
 *
 * قاعدهٔ کادر زردِ برنده: در طرح فقط جعبهٔ **آخر** (total) کادر می‌گیرد، پس با
 * arbitrary variant تیلویند روی `:last-child` اعمال می‌شود و نیازی به شمارش slot نیست.
 */
const props = defineProps({
    /** `chung` (آبی) یا `hong` (قرمز). */
    side: { type: String, default: "chung" },
    /** برندهٔ رقابت — جعبهٔ total کادر زرد می‌گیرد (مستقل از رنگ تیم). */
    winner: { type: Boolean, default: false },
    /** فاصلهٔ عمودی بین آیتم‌ها؛ طرح اصلی ۲۰px. */
    itemGap: { type: Number, default: 20 },
});

/**
 * نسخهٔ قرمز از نظر جهت متن آینه می‌شود.
 * از `flex-row-reverse` استفاده می‌کنیم تا هر آیتم (که خودش هم RTL-agnostic است)
 * بدون تغییر داخلی، جابه‌جا شود. چیدمان عمودی است پایه، فقط محور افقی لازم است —
 * بنابراین در عمل روی همین ستون افقیِ هر آیتم اثر می‌گذارد، نه ترتیب عمودی.
 */
const isRed = computed(() => props.side === "hong");

/** کلاس کادر زرد — فقط آخرین آیتم و فقط وقتی برنده باشد. */
const winnerRing = computed(() =>
    props.winner ? "[&>*:last-child]:ring-4 [&>*:last-child]:ring-rtds-yellow" : ""
);

/**
 * برای آینه‌سازی واقعیِ برچسب‌ها (label سمت چپ برای آبی، سمت راست برای قرمز)
 * جهت را به ریشه می‌دهیم و `ScoreItem` از منطق داخلی خودش (start/justify) پیروی می‌کند.
 */
const rootDir = computed(() => (isRed.value ? "rtl" : "ltr"));

const slots = useSlots();
const hasContent = computed(() => Boolean(slots.default));
</script>

<template>
    <div
        v-if="hasContent"
        :dir="rootDir"
        class="flex flex-col"
        :style="{ gap: `${itemGap}px` }"
        :class="winnerRing"
    >
        <slot />
    </div>
</template>