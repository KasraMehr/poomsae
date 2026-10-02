<script setup>
import { computed } from "vue";
import { Head } from "@inertiajs/vue3";
import ScoreboardLayout from "../../Shared/Layouts/ScoreboardLayout.vue";
import ScoreboardHeader from "../../Components/scoreboard/ScoreboardHeader.vue";
import AthleteInfo from "../../Components/scoreboard/AthleteInfo.vue";
import FormBadge from "../../Components/scoreboard/FormBadge.vue";
import Timer from "../../Components/scoreboard/Timer.vue";

/**
 * Standby — فقط نمایش «اجرای فعلی + Timer اجرا + state پایان».
 *
 * stateها (طبق قرارداد):
 * - running  = اجرای فیزیکی فعلی، Timer در حال شمارش از لحظهٔ شروع
 * - scoring  = اجرا تمام شده (Operator finish کرده)، Timer متوقف، نمایش پایان اجرا
 *
 * ممنوعیت: صف اجرا / اجرای قبلی / اجرای بعدی / timeline
 * → همه‌ی آن‌ها متعلق به LiveBoard.vue است.
 *
 * صفحهٔ placeholder — هنوز از Backend رندر نمی‌شود
 * (هیچ controller/route‌ای دست نخورده است؛ repoint به بعد از Contract نهایی موکول است).
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

/**
 * اجرای فعلی: فقط bout در حال اجرا و performance با وضعیت running/scoring.
 * (pending = اجرای بعدی → عمداً اینجا پیدا نمی‌شود؛ جای آن LiveBoard است.)
 */
const currentExecution = computed(() => {
    for (const category of props.tournament?.categories ?? []) {
        for (const round of category.rounds) {
            for (const bout of round.bouts) {
                if (bout.status !== "running") continue;
                const performance = bout.performances.find((p) =>
                    ["running", "scoring"].includes(p.status),
                );
                if (!performance) continue;
                return {
                    round,
                    bout,
                    performance,
                    entries: bout.entries,
                    state: performance.status === "running" ? "running" : "scoring",
                };
            }
        }
    }
    return null;
});

const isRunning = computed(() => currentExecution.value?.state === "running");

/**
 * مدت استاندارد اجرا به ثانیه — منبعی از تنظیمات مسابقه (execution_duration_seconds).
 *
 * فعلاً این مقدار در snapshot موجود نیست؛ تا وقتی بک‌اند اضافه‌اش نکرده مقدار پیش‌فرض ۹۰ ثانیه
 * استفاده می‌شود (۱:۳۰، مطابق طرح). به‌محض رسیدن فیلد، همین یک خط کافی است.
 */
const DEFAULT_EXECUTION_SECONDS = 90;

const durationSeconds = computed(
    () =>
        props.tournament?.execution_duration_seconds ?? DEFAULT_EXECUTION_SECONDS
);

/*
 * TODO / Proposed Contract (مرحلهٔ بعد):
 * - logoUrl            → لوگوی رویداد در ScoreboardHeader
 * - durationSeconds    → مدت زمان استاندارد اجرا (تنظیمات مسابقه) برای Timer
 * - entry country/country_code/flag → NationalityBadge در AthleteInfo
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

        <!-- اجرای فعلی: running (Timer شمارش) یا scoring (اجرای تمام‌شده) -->
        <section
            v-if="currentExecution"
            class="flex flex-1 flex-col items-center justify-center gap-8 text-center"
        >
            <span
                class="rounded-full border px-4 py-1 text-sm"
                :class="
                    isRunning
                        ? 'border-rtds-blue text-rtds-blue-soft'
                        : 'border-rtds-yellow text-rtds-yellow'
                "
            >
                {{
                    isRunning
                        ? "اجرای در حال اجرا"
                        : "اجرای پایان یافت — در انتظار نتیجه"
                }}
            </span>

            <FormBadge
                size="lg"
                :round-label="currentExecution.round.name"
                :form-name="currentExecution.performance.form_name ?? ''"
                :form-number="currentExecution.performance.form_number ?? null"
            />

            <div class="flex flex-wrap items-center justify-center gap-10">
                <AthleteInfo
                    v-for="entry in currentExecution.entries"
                    :key="entry.id"                        layout="horizontal"
                        size="lg"
                        :name="entry.name"
                        :side="entry.side"
                />
            </div>

            <!-- سه حالت تایمر: پیش از شروع (عدد کل) / حین اجرا (شمارش معکوس) / پایان‌یافته (متوسط)
                 همه از started_at و ended_at مشتق می‌شوند؛ کنترل با پنل اپراتور است. -->
            <Timer
                size="lg"
                :duration-seconds="durationSeconds"
                :started-at="currentExecution.performance.started_at"
                :ended-at="currentExecution.performance.ended_at"
            />
        </section>

        <!-- پایان اجرا / حالت آماده (بدون صف و بدون اجرای بعدی) -->
        <section
            v-else
            class="flex flex-1 flex-col items-center justify-center gap-6 text-center"
        >
            <p class="text-4xl font-semibold uppercase">
                {{
                    finished
                        ? "مسابقه پایان یافت"
                        : "اجرایی در جریان نیست"
                }}
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
