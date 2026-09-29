<script setup>
import { computed } from "vue";

/**
 * Arc امتیاز (accuracy) + امتیاز کل + برچسب نوع امتیاز + پس‌زمینه.
 * فقط در صفحات Scoring استفاده می‌شود.
 */
const props = defineProps({
    accuracyScore: { type: Number, default: 0 },
    totalScore: { type: Number, default: 0 },
    scoreTypeLabel: { type: String, default: "" },
    max: { type: Number, default: 10 },
});

const radius = 70;
const circumference = Math.PI * radius; // نیم‌دایره

const progress = computed(() =>
    Math.min(1, Math.max(0, props.accuracyScore / (props.max || 1))),
);

const dashOffset = computed(
    () => circumference * (1 - progress.value),
);

const formattedTotal = computed(() =>
    props.totalScore.toLocaleString("fa-IR", {
        minimumFractionDigits: 3,
        maximumFractionDigits: 3,
    }),
);
</script>

<template>
    <div
        class="flex flex-col items-center rounded-2xl border border-rtds-border bg-rtds-bg-card px-8 py-6"
    >
        <svg
            viewBox="0 0 160 90"
            class="w-44"
            role="img"
            :aria-label="scoreTypeLabel"
        >
            <!-- نیم‌دایره پس‌زمینه -->
            <path
                d="M 10 80 A 70 70 0 0 1 150 80"
                fill="none"
                stroke="var(--color-rtds-border)"
                stroke-width="12"
                stroke-linecap="round"
            />
            <!-- نیم‌دایره accuracy -->
            <path
                d="M 10 80 A 70 70 0 0 1 150 80"
                fill="none"
                stroke="var(--color-rtds-gold-bright)"
                stroke-width="12"
                stroke-linecap="round"
                :stroke-dasharray="circumference"
                :stroke-dashoffset="dashOffset"
            />
            <text
                x="80"
                y="72"
                text-anchor="middle"
                class="fill-rtds-text-light text-2xl font-semibold"
            >
                {{ accuracyScore }}
            </text>
        </svg>

        <b class="mt-2 text-4xl tabular-nums text-rtds-text-light">{{
            formattedTotal
        }}</b>
        <span
            v-if="scoreTypeLabel"
            class="mt-1 text-sm uppercase tracking-wide text-rtds-text-muted"
            >{{ scoreTypeLabel }}</span
        >
    </div>
</template>
