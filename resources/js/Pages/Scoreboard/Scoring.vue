<script setup>
import { computed } from "vue";
import { Head } from "@inertiajs/vue3";
import ScoreboardLayout from "../../Shared/Layouts/ScoreboardLayout.vue";
import ScoreboardHeader from "../../Components/scoreboard/ScoreboardHeader.vue";
import AthleteInfo from "../../Components/scoreboard/AthleteInfo.vue";
import FormBadge from "../../Components/scoreboard/FormBadge.vue";
import ScoreCard from "../../Components/scoreboard/ScoreCard.vue";
import ScoreItem from "../../Components/scoreboard/ScoreItem.vue";
import SingleScoringTable from "../../Components/scoreboard/tables/SingleScoringTable.vue";

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

// فقط داده‌ای که امروز در snapshot هست: boutهای در حال اجرا.
const active = computed(() =>
    (props.tournament?.categories ?? []).flatMap((category) =>
        category.rounds.flatMap((round) =>
            round.bouts
                .filter((bout) => bout.status === "running")
                .map((bout) => ({
                    ...bout,
                    category: category.name,
                    judgeCount: category.judge_count,
                    round: round.name,
                })),
        ),
    ),
);

const sideLabels = { chung: "چونگ", hong: "هونگ" };

const score = (value) =>
    value == null
        ? "—"
        : Number(value).toLocaleString("fa-IR", {
              minimumFractionDigits: 3,
              maximumFractionDigits: 6,
          });

// ردیف‌های جدول: هر اجرا یک ردیف (نام فرم + نمرهٔ منتشرشده).
const tableRows = (bout) =>
    bout.performances.map((performance) => ({
        key: performance.id,
        label: performance.form_name ?? `فرم ${performance.form_number}`,
        scores: [],
        total: performance.result != null ? score(performance.result) : null,
    }));

const totalOf = (bout, entryId) => Number(bout.totals?.[entryId]) || 0;

/*
 * TODO / Proposed Contract (مرحلهٔ بعد):
 * - execution_mode (SINGLE | DOUBLE | SINGLE-FREESTYLE)
 *   → انتخاب SingleScoringTable vs DoubleScoringTable vs FreestyleScoringTable
 * - judge scores per sheet (در snapshot فعلی display=true است و scores[] خالی می‌آید)
 *   → ستون‌های قاضی ۱..۵/۷ در جدول
 * - accuracy_score و presentation_score هر entry (برای ScoreItem در ScoreCard)
 * - country / country_code / flag / number هر entry
 * - logoUrl هدر
 *
 * مجموع (accuracy + presentation) از Backend می‌آید و در `total` پاس داده می‌شود؛
 * سقف‌های ۴/۶/۱۰ ثابت‌اند و فرانت آن‌ها را جمع نمی‌کند.
 */
</script>

<template>
    <Head title="نمایش زنده" />
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

        <!--
          ساختار placeholder: فقط داده‌ای که همین الان در snapshot هست.
          → variantهای SINGLE | DOUBLE | SINGLE-FREESTYLE فعلاً رندر نمی‌شوند.
        -->
        <p
            v-if="!active.length"
            class="flex flex-1 items-center justify-center rounded-2xl border border-rtds-border-subtle bg-rtds-bg-card p-12 text-center text-rtds-text-secondary"
        >
            در حال حاضر رقابتی در حال اجرا نیست.
        </p>

        <section
            v-for="bout in active"
            :key="bout.id"
            class="mb-8 last:mb-0"
        >
            <div class="mb-4 flex items-center justify-between">
                <h2>
                    {{ bout.category }} · {{ bout.round }} · رقابت
                    {{ bout.sequence }}
                </h2>
                <FormBadge
                    size="sm"
                    :round-label="bout.round"
                    :form-name="
                        bout.performances[0]?.form_name ??
                        `رقابت ${bout.sequence}`
                    "
                    :form-number="bout.performances[0]?.form_number ?? null"
                />
            </div>

            <div class="grid grid-cols-2 gap-6 max-[850px]:grid-cols-1">
                <article
                    v-for="entry in bout.entries"
                    :key="entry.id"
                    class="rounded-2xl border border-rtds-border bg-rtds-bg-card p-6"
                    :class="
                        entry.side === 'chung'
                            ? 'border-t-4 border-t-rtds-blue'
                            : 'border-t-4 border-t-rtds-red'
                    "
                >
                    <AthleteInfo
                        layout="horizontal"
                        size="md"
                        :name="entry.name"
                        :side="entry.side"
                    />

                    <div class="mt-4">
                        <!--
                          TODO [Proposed Contract]: دادهٔ سه امتیاز (Accuracy/Presentation/Total)
                          هنوز از Backend نمی‌آید؛ تا آن زمان null یعنی «--».
                          سقف‌ها ثابت‌اند: ۴ و ۶ و ۱۰.
                        -->
                        <ScoreCard :side="entry.side" size="sm" class="max-w-[220px]">
                            <ScoreItem
                                label="Accuracy"
                                :max="4"
                                :value="null"
                                :side="entry.side"
                                size="sm"
                            />
                            <ScoreItem
                                label="Presentation"
                                :max="6"
                                :value="null"
                                :side="entry.side"
                                size="sm"
                            />
                            <ScoreItem
                                label="Total Score"
                                :max="10"
                                :value="totalOf(bout, entry.id)"
                                variant="total"
                                :side="entry.side"
                                size="sm"
                            />
                        </ScoreCard>
                    </div>

                    <div
                        class="mt-4 flex items-center justify-between border-t border-rtds-border-subtle pt-4"
                    >
                        <span class="text-rtds-text-secondary"
                            >میانگین دو فرم</span
                        >
                        <b class="text-3xl tabular-nums">
                            {{ score(bout.totals?.[entry.id]) }}
                        </b>
                    </div>
                </article>
            </div>

            <div class="mt-6">
                <SingleScoringTable
                    :judge-count="bout.judgeCount ?? 5"
                    :rows="tableRows(bout)"
                />
            </div>
        </section>
    </ScoreboardLayout>
</template>
