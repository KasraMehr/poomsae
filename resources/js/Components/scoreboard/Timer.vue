<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";

/**
 * تایمر مسابقه — نمایش «زمان باقی‌مانده» طبق طرح.
 *
 * نقش: **فقط نمایش**. این کامپوننت هیچ فرمانی ندارد و خودش چیزی را شروع/متوقف نمی‌کند؛
 * کنترل کامل از پنل اپراتور است و وضعیت از snapshot می‌آید (`started_at` / `ended_at`).
 * یعنی هر چند نمایشگری که به یک مسابقه وصل باشند، همگی یک لحظه شروع و پایان را می‌بینند.
 *
 * حالت‌های نمایشی از timestampهای اپراتور مشتق می‌شوند و ساعت سرور، اختلاف ساعت مرورگر را جبران می‌کند:
 *  1. پیش از شروع  → `startedAt` خالی است → مدت کل نمایش داده می‌شود
 *  2. حین اجرا     → شمارش معکوس تا صفر؛ در صفر قرمز می‌شود و می‌ایستد
 *  3. پایان‌یافته   → `endedAt` هست → زمان باقی‌ماندهٔ لحظهٔ پایان چشمک‌زن می‌ماند
 *
 * این کامپوننت فقط نمایش می‌دهد؛ شروع و پایان را پنل اپراتور ثبت می‌کند.
 */
const props = defineProps({
    /** مدت استاندارد اجرا به ثانیه — از تنظیمات مسابقه (`execution_duration_seconds`). */
    durationSeconds: { type: Number, default: 0 },
    /** شروع اجرا (epoch ms یا ISO) — خالی یعنی هنوز شروع نشده. */
    startedAt: { type: [Number, String], default: null },
    /** پایان اجرا (epoch ms یا ISO) — خالی یعنی هنوز متوقف نشده. */
    endedAt: { type: [Number, String], default: null },
    /** Server clock sample used to align display timers with the operator console. */
    serverNow: { type: [Number, String], default: null },
    /** اندازه — `lg` برای Standby (ابعاد طرح)، `sm` برای LiveBoard. */
    size: { type: String, default: "lg" },
});

/** مبدأ زمان به میلی‌ثانیه؛ ورودی نامعتبر null می‌دهد. */
const toMs = (value) => {
    if (value == null || value === "") return null;
    const ms = typeof value === "number" ? value : new Date(value).getTime();
    return Number.isNaN(ms) ? null : ms;
};

const now = ref(Date.now());
const clockOffset = ref(0);
let timerId = null;

const startMs = computed(() => toMs(props.startedAt));
const endMs = computed(() => toMs(props.endedAt));
const syncedNow = computed(() => now.value + clockOffset.value);

watch(() => props.serverNow, (value) => {
    const serverMs = toMs(value);
    if (serverMs != null) clockOffset.value = serverMs - Date.now();
}, { immediate: true });

/** ثانیهٔ گذشته از شروع اجرا. */
const elapsedSeconds = computed(() => {
    if (startMs.value == null) return 0;
    const end = endMs.value ?? syncedNow.value;
    return Math.max(0, Math.floor((end - startMs.value) / 1000));
});

/** ثانیهٔ باقی‌مانده؛ همیشه معکوس، هرگز منفی. */
const remainingSeconds = computed(() =>
    Math.max(0, Math.floor(props.durationSeconds) - elapsedSeconds.value)
);

/** وضعیت نمایشی — فقط برای رنگ و برچسب، نه برای منطق شمارش. */
const state = computed(() => {
    if (startMs.value == null) return "idle"; // پیش از شروع
    if (endMs.value != null) return "stopped"; // پایان‌یافته (اپراتور متوقف کرده)
    if (remainingSeconds.value === 0) return "expired";
    return "running"; // حین اجرا
});
const alertState = computed(() => ["stopped", "expired"].includes(state.value));

const display = computed(() => {
    const total = remainingSeconds.value;
    const minutes = Math.floor(total / 60);
    const seconds = total % 60;
    return `${String(minutes).padStart(2, "0")}:${String(seconds).padStart(2, "0")}`;
});

/**
 * اندازه‌ها دقیقاً از طرح (lg) و نسخهٔ کوچک‌شدهٔ آن (sm):
 * کادر ۳۱۵×۲۳۰ با حاشیهٔ ۴px در lg؛ برچسب و عدد از ۹۶px به ۴۸px در sm.
 */
const sizeClasses = {
    lg: {
        wrap: "gap-2 pb-5",
        label: "text-[34px] leading-[12px] tracking-[3px] font-semibold",
        box: "h-[230px] w-[315px] border-4 rounded-lg",
        digits: "text-[96px] leading-[96px] font-bold",
    },
    sm: {
        wrap: "gap-1 pb-2",
        label: "text-[17px] leading-[6px] tracking-[1.5px] font-bold",
        box: "h-[115px] w-[158px] border-2 rounded-md",
        digits: "text-[48px] leading-[48px] font-semibold",
    },
};

const s = computed(() => sizeClasses[props.size] ?? sizeClasses.lg);

onMounted(() => {
    // در حالت idle/stopped نیازی به تیک زدن نیست، ولی یک ثانیه‌ای بودن ارزان است
    // و تضمین می‌کند لحظهٔ رسیدن به صفر دقیقاً رندر شود.
    timerId = setInterval(() => {
        now.value = Date.now();
    }, 250);
});

onBeforeUnmount(() => {
    if (timerId) clearInterval(timerId);
});
</script>

<template>
    <div
        class="flex flex-col items-center justify-center"
        :class="[s.wrap, 'font-rtds uppercase select-none']"
    >
        <!-- برچسب: زمان باقی‌مانده -->
        <span class="text-rtds-gold py-3" :class="s.label">Remaining Time</span>

        <!-- کادر + عدد -->
        <div
            class="flex flex-col items-center justify-center"
            :class="[s.box, alertState ? 'border-rtds-error' : 'border-rtds-yellow']"
        >
            <span
                class="tabular-nums"
                :class="[s.digits, alertState ? 'text-rtds-error' : 'text-rtds-yellow', state === 'stopped' ? 'timer-stopped' : '']"
                >{{ display }}</span
            >
        </div>
    </div>
</template>

<style scoped>
@keyframes timer-blink {
    50% {
        visibility: hidden;
    }
}

.timer-stopped {
    animation: timer-blink 1s steps(1, end) infinite;
}
</style>
