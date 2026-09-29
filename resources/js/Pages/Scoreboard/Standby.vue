<script setup>
import { computed } from "vue";
import { Head } from "@inertiajs/vue3";
import ScoreboardLayout from "../../Shared/Layouts/ScoreboardLayout.vue";
import ScoreboardHeader from "../../Components/scoreboard/ScoreboardHeader.vue";
import AthleteInfo from "../../Components/scoreboard/AthleteInfo.vue";
import FormBadge from "../../Components/scoreboard/FormBadge.vue";
import Timer from "../../Components/scoreboard/Timer.vue";

/**
 * صفحهٔ placeholder — هنوز از Backend رندر نمی‌شود
 * (هیچ controller/route‌ای دست نخورده است؛ repoint به بعد از Contract نهایی موکول است).
 * تا آن زمان props اختیاری است و همهٔ دسترسی‌ها null-safe می‌مانند.
 */
const props = defineProps({
    tournament: { type: Object, default: null },
});

const statusLabels = {
    draft: "پیش‌نویس",
    ready: "آماده",
    running: "در حال برگزاری",
    completed: "پایان‌یافته",
};

const stage = computed(() => statusLabels[props.tournament?.status] ?? "");
const courts = computed(() => props.tournament?.courts ?? []);
const finished = computed(() => props.tournament?.status === "completed");

// فقط داده‌ای که امروز در snapshot هست: اجرای در حال اجرا یا بعدی.
const nextExecution = computed(() => {
    for (const category of props.tournament?.categories ?? []) {
        for (const round of category.rounds) {
            for (const bout of round.bouts) {
                if (!["running", "pending"].includes(bout.status)) continue;
                const performance = bout.performances.find(
                    (p) => ["running", "scoring", "pending"].includes(p.status),
                );
                if (!performance) continue;
                return {
                    round,
                    bout,
                    performance,
                    entries: bout.entries,
                };
            }
        }
    }
    return null;
});

const sideClasses = {
    chung: "bg-rtds-blue",
    hong: "bg-rtds-red",
};

const isRunning = computed(
    () => nextExecution.value?.performance?.status === "running",
);

/*
 * TODO / Proposed Contract (مرحلهٔ بعد):
 * - logoUrl            → لوگوی رویداد در ScoreboardHeader
 * - durationSeconds    → مدت زمان استاندارد اجرا (تنظیمات مسابقه) برای Timer
 * - entry photo_url    → عکس ورزشکار در AthleteInfo
 * - entry country/flag → NationalityBadge در AthleteInfo
 * - entry number       → شمارهٔ قرعه در AthleteInfo
 */
</script>

<template>
    <Head title="آمادگی" />
    <ScoreboardLayout
        :stage="stage"
        :center="tournament?.name ?? ''"
        category=""
    >
        <template #header>
            <ScoreboardHeader
                :stage="stage"
                :event-title="tournament?.name ?? ''"
            />
        </template>

        <!-- اجرای بعدی / در حال اجرا -->
        <section
            v-if="nextExecution"
            class="flex flex-1 flex-col items-center justify-center gap-8"
        >
            <FormBadge
                size="lg"
                :round-label="nextExecution.round.name"
                :form-name="nextExecution.performance.form_name ?? ''"
            />

            <div class="flex flex-wrap items-center justify-center gap-10">
                <AthleteInfo
                    v-for="entry in nextExecution.entries"
                    :key="entry.id"
                    orientation="horizontal"
                    size="lg"
                    :name="entry.name"
                    :color="sideClasses[entry.side] ?? 'bg-rtds-bg-elevated'"
                />
            </div>

            <div class="flex items-center gap-3 text-6xl">
                <Timer
                    :running="isRunning"
                    :started-at="nextExecution.performance.started_at"
                    :duration-seconds="0"
                />
            </div>
        </section>

        <!-- حالت پیش‌فرض: آمادهٔ اجرای بعدی -->
        <section
            v-else
            class="flex flex-1 flex-col items-center justify-center gap-6 text-center"
        >
            <p class="text-4xl font-semibold uppercase">
                {{ finished ? "مسابقه پایان یافت" : "آمادهٔ اجرای بعدی" }}
            </p>
            <p class="text-rtds-text-secondary">
                نتایج فقط پس از تأیید اپراتور منتشر می‌شوند.
            </p>

            <div
                v-if="courts.length"
                class="mt-4 flex flex-wrap justify-center gap-3"
            >
                <span
                    v-for="court in courts"
                    :key="court.id"
                    class="rounded-lg border border-rtds-border bg-rtds-bg-card px-4 py-2 text-sm text-rtds-text-secondary"
                >
                    {{ court.name }}
                </span>
            </div>
        </section>
    </ScoreboardLayout>
</template>
