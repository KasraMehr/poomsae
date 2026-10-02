<script setup>
/**
 * بج کشور: پرچم + کد سه‌حرفی انگلیسی (مثل IRI / KOR / TUR).
 *
 * - نمایش متنی: فقط کد انگلیسی (ISO 3166-1 alpha-3 / کدهای المپیک)؛ نام فارسی نمایش داده نمی‌شود.
 * - دو جهت: flagPosition = 'left' (پرچم چپ) | 'right' (پرچم راست).
 * - نبود flagUrl → placeholder: همان کد داخل قاب پرچم (بدون حدس زدن پرچم).
 * - سه سایز مستقل sm/md/lg (lg با ابعاد/CSS طرح: ارتفاع ۱۳۵px، فونت ~۹۶px Oswald).
 */
defineProps({
    code: { type: String, default: "" }, // کد انگلیسی مثل IRI
    country: { type: String, default: "" }, // فقط برای alt پرچم (a11y)
    flagUrl: { type: String, default: "" },
    flagPosition: { type: String, default: "left" }, // 'left' | 'right'
    size: { type: String, default: "md" }, // 'sm' | 'md' | 'lg'
});

// سه سایز مستقل (بر اساس CSS طرح: کارت نیمه‌شفاف + پرچمِ نیمی از کارت)
const sizeClasses = {
    sm: {
        root: "h-8",
        flag: "w-[46px]",
        code: "min-w-[46px] px-1.5 text-[15px] leading-none",
        placeholder: "text-[10px]",
    },
    md: {
        root: "h-16",
        flag: "w-[92px]",
        code: "min-w-[92px] px-2.5 text-[34px] leading-none",
        placeholder: "text-sm",
    },
    lg: {
        root: "h-[135px]", // دقیقاً ارتفاع طرح
        flag: "w-[196px]",
        code: "min-w-[196px] px-[10px] text-[96px] leading-[30px]",
        placeholder: "text-3xl",
    },
};
</script>

<template>
    <span
        dir="ltr"
        class="inline-flex overflow-hidden"
        :class="[
            sizeClasses[size]?.root ?? sizeClasses.md.root,
            flagPosition === 'right' ? 'flex-row-reverse' : 'flex-row',
        ]"
    >
        <!-- قاب پرچم: تصویر واقعی یا placeholder (کد کشور) -->
        <span
            class="relative shrink-0"
            :class="sizeClasses[size]?.flag ?? sizeClasses.md.flag"
        >
            <img
                v-if="flagUrl"
                :src="flagUrl"
                :alt="country || code"
                class="h-full w-full object-cover"
            />
            <span
                v-else
                class="flex h-full w-full items-center justify-center border border-dashed border-rtds-border-strong bg-rtds-bg-elevated font-rtds font-bold tracking-wider text-rtds-text-secondary"
                :class="sizeClasses[size]?.placeholder ?? sizeClasses.md.placeholder"
                >{{ code }}</span
            >
        </span>

        <!-- کارت کد: پس‌زمینهٔ نیمه‌شفاف + کد انگلیسی (Oswald) -->
        <span
            class="flex flex-1 items-center justify-center bg-white/[0.11] px-2 font-rtds font-bold tracking-wide text-white"
            :class="sizeClasses[size]?.code ?? sizeClasses.md.code"
            >{{ code }}</span
        >
    </span>
</template>
