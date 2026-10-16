<script setup>
import { computed } from "vue";

/**
 * یک آیتم امتیاز: «برچسب /max» روی یک ردیف، و عدد بزرگ در یک جعبهٔ تیره زیرش.
 *
 * سه حالت بصری که از `variant` می‌آید (در طرح TotalScoreContainer دیده می‌شود):
 *  - `sub`   → آیتم‌های زیرمجموعه (Accuracy, Presentation): عدد **سفید**، فونت ۱۰۰px
 *  - `total` → امتیاز کل: عدد **رنگ تیم**، فونت ۱۳۵px (بزرگ‌تر)، جعبهٔ بلندتر
 *
 * این کامپوننت خودش «چه برچسبی» و «چه سقفی» را نمی‌داند؛ فقط نمایش می‌دهد.
 * سقف‌های پومسه ثابت‌اند (Accuracy=4, Presentation=6, Total=10) و در `ScoreCard`
 * تعریف شده‌اند، ولی هر آیتم `max` خودش را می‌گیرد تا مستقل و قابل استفادهٔ مجدد بماند.
 *
 * دادهٔ ورودی همیشه از snapshot می‌آید؛ این کامپوننت هیچ محاسبهٔ امتیازی نمی‌کند
 * (جمع accuracy+presentation از وظیفهٔ بک‌اند است).
 */
const props = defineProps({
    /** برچسب نمایشی، دقیقاً همان‌طور که در امتیاز نوشته شده (لاتین/انگلیسی). */
    label: { type: String, required: true },
    /** امتیاز؛ null یعنی هنوز ثبت نشده → «--» نشان داده می‌شود. */
    value: { type: [Number, String], default: null },
    /** سقف امتیاز (مثل 4، 6، 10). */
    max: { type: Number, default: null },
    /** رنگ و جهت: `chung` (آبی) یا `hong` (قرمز). */
    side: { type: String, default: "chung" },
    /** `sub` (پیش‌فرض) یا `total`. */
    variant: { type: String, default: "sub" },
    /** `lg` برای صفحهٔ Result، `sm` برای جاهای فشرده. */
    size: { type: String, default: "lg" },
});

/**
 * رنگ تیم فقط روی «عدد» و «برچسب» اثر می‌گذارد.
 * آیتم‌های زیرمجموعه در طرح همیشه عدد سفید دارند و فقط total رنگی می‌شود؛
 * ولی برچسب هر دو رنگ تیم را دارد. این تفاوت از طریق کلاس‌های زیر اعمال می‌شود.
 */
const sideText = {
    chung: "text-rtds-blue",
    hong: "text-rtds-red",
};

/** کلاس رنگ برای متن (برچسب/عدد بیرونی) بر اساس side. */
const textColor = computed(() => sideText[props.side] ?? sideText.chung);

/** کلاس رنگ برای عدد داخل جعبه: total رنگی، sub سفید. */
const valueColor = computed(() =>
    props.variant === "total" ? textColor.value : "text-white",
);

/**
 * قالب‌بندی عدد: همیشه دو رقم اعشار با ممیز لاتین (مثل ۸.۴۶ در طرح، نه «۸٫۴۶»).
 * استفاده از toFixed + به‌جایگزینی جداکنندهٔ هزارگانِ فارسی تا خروجی با طرح یکی بماند.
 */
const formattedValue = computed(() => {
    if (props.value == null || props.value === "") return "--";
    const num = Number(props.value);
    if (Number.isNaN(num)) return "--";
    return num.toFixed(2);
});

/** ابعاد از طرح برای lg؛ sm نصف. */
const sizes = {
    lg: {
        label: "text-[24px] leading-[1.1] font-normal",
        box: "h-[144px] w-full",
        value: "text-[100px] leading-[1.1] font-bold",
        totalBox: "h-[230px] w-full",
        totalValue: "text-[135px] leading-[1.05] font-bold",
    },
    sm: {
        label: "text-[12px] leading-[1.1] font-normal",
        box: "h-[72px] w-full",
        value: "text-[50px] leading-[1.1] font-bold",
        totalBox: "h-[115px] w-full",
        totalValue: "text-[68px] leading-[1.05] font-bold",
    },
};

const s = computed(() => sizes[props.size] ?? sizes.lg);

const boxClasses = computed(() =>
    props.variant === "total" ? s.value.totalBox : s.value.box,
);
const valueClasses = computed(() =>
    props.variant === "total" ? s.value.totalValue : s.value.value,
);
</script>

<template>
    <div class="flex flex-col items-start font-rtds uppercase select-none">
        <!-- ردیف برچسب و سقف: «ACCURACY      /4.0» -->
        <div class="flex w-full items-baseline justify-between">
            <span class="truncate" :class="[s.label, textColor]">
                {{ label }}
            </span>
            <span
                v-if="max != null"
                class="shrink-0 text-rtds-text-tertiary"
                :class="s.label"
                >/{{ max.toFixed(1) }}</span
            >
        </div>

        <!-- جعبهٔ عدد -->
        <div
            class="mt-1 flex items-center justify-center bg-rtds-bg-card"
            :class="boxClasses"
        >
            <span class="tabular-nums" :class="[valueClasses, valueColor]">{{
                formattedValue
            }}</span>
        </div>
    </div>
</template>
