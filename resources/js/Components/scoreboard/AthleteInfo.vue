<script setup>
import NationalityBadge from "./NationalityBadge.vue";

/**
 * اطلاعات ورزشکار: شماره + رنگ سمت + نام + ملیت.
 * در دو جهت افقی و عمودی (استایل‌های مختلف طراحی).
 */
const props = defineProps({
    orientation: { type: String, default: "horizontal" }, // 'horizontal' | 'vertical'
    number: { type: [String, Number], default: "" },
    color: { type: String, default: "" }, // رنگ سمت (chung/hong) به‌صورت کلاس Tailwind
    name: { type: String, default: "" },
    country: { type: String, default: "" }, // فقط برای alt پرچم
    countryCode: { type: String, default: "" }, // کد انگلیسی مثل IRI — متن نمایش‌داده‌شده در بج
    flagUrl: { type: String, default: "" },
    photoUrl: { type: String, default: "" },
    size: { type: String, default: "md" }, // 'sm' | 'md' | 'lg'
});

const nameClasses = {
    sm: "text-base",
    md: "text-xl",
    lg: "text-3xl",
};

const numberClasses = {
    sm: "h-7 w-7 text-sm",
    md: "h-9 w-9 text-base",
    lg: "h-12 w-12 text-xl",
};
</script>

<template>
    <div
        class="flex"
        :class="
            orientation === 'vertical'
                ? 'flex-col items-center gap-3 text-center'
                : 'flex-row items-center gap-4'
        "
    >
        <!-- عکس ورزشکار (اختیاری) -->
        <img
            v-if="photoUrl"
            :src="photoUrl"
            :alt="name"
            class="shrink-0 rounded-xl object-cover"
            :class="
                size === 'lg'
                    ? 'h-40 w-40'
                    : size === 'md'
                      ? 'h-24 w-24'
                      : 'h-16 w-16'
            "
        />

        <!-- شماره با رنگ سمت -->
        <span
            v-if="number !== ''"
            class="grid shrink-0 place-items-center rounded-lg font-semibold tabular-nums"
            :class="[color, numberClasses[size] ?? numberClasses.md]"
            >{{ number }}</span
        >

        <div
            class="min-w-0"
            :class="
                orientation === 'vertical' ? 'flex flex-col items-center' : ''
            "
        >
            <p
                class="truncate font-semibold text-rtds-text-light"
                :class="nameClasses[size] ?? nameClasses.md"
            >
                {{ name }}
            </p>
            <NationalityBadge
                :code="countryCode"
                :country="country"
                :flag-url="flagUrl"
                :size="size === 'lg' ? 'md' : 'sm'"
                class="mt-1"
            />
        </div>
    </div>
</template>
