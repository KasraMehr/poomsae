<script setup>
import { computed } from "vue";
import { Head } from "@inertiajs/vue3";
import ScoreboardLayout from "../../Shared/Layouts/ScoreboardLayout.vue";
import ScoreboardHeader from "../../Components/scoreboard/ScoreboardHeader.vue";
import ScoreCard from "../../Components/scoreboard/ScoreCard.vue";
import ScoreItem from "../../Components/scoreboard/ScoreItem.vue";

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

/*
 * فقط داده‌ای که امروز در snapshot هست.
 * variant ONE-ROUND / TWO-ROUNDS از rounds.length قابل‌استخراج است (سطح ۲ قاعدهٔ mode).
 */
const categories = computed(() =>
    (props.tournament?.categories ?? []).map((category) => {
        const bouts = category.rounds.flatMap((round) =>
            round.bouts
                .filter((bout) => bout.status === "completed")
                .map((bout) => {
                    // هر ورزشکار یک بلوک امتیاز دارد؛ برنده فقط کادر زرد می‌گیرد.
                    const sides = bout.entries.map((entry) => ({
                        side: entry.side ?? "chung",
                        isWinner: entry.id === bout.winner_entry_id,
                        accuracy: entry.accuracy_score ?? null,
                        presentation: entry.presentation_score ?? null,
                        total: bout.totals?.[entry.id] ?? null,
                    }));

                    return {
                        id: bout.id,
                        label: `${round.name} · رقابت ${bout.sequence}`,
                        winner: bout.entries.find(
                            (entry) => entry.id === bout.winner_entry_id,
                        )?.name ?? null,
                        winnerSide: bout.entries.find(
                            (entry) => entry.id === bout.winner_entry_id,
                        )?.side ?? null,
                        sides,
                        total: bout.totals?.[bout.winner_entry_id] ?? null,
                        roundsCount: category.rounds.length,
                    };
                }),
        );
        return {
            id: category.id,
            name: category.name,
            mode:
                category.rounds.length > 1
                    ? "نتیجهٔ دو مرحله‌ای"
                    : "نتیجهٔ یک مرحله‌ای",
            bouts,
        };
    }),
);

/*
 * TODO / Proposed Contract (مرحلهٔ بعد):
 * - country / country_code / flag برندگان → AthleteInfo کامل‌تر
 * - کلید سمبل فرم هر رقابت (`form_number`)
 * - entry.accuracy_score / presentation_score (الان null → «--»)
 *   مجموع (total) از bout.totals می‌آید و در فرانت جمع نمی‌شود.
 * - logoUrl هدر
 */
</script>

<template>
    <Head title="نتایج" />
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
          Proposed Contract: country / country_code / flag
        -->
        <p
            v-if="!categories.some((category) => category.bouts.length)"
            class="flex flex-1 items-center justify-center rounded-2xl border border-rtds-border-subtle bg-rtds-bg-card p-12 text-center text-rtds-text-secondary"
        >
            نتیجه‌ای هنوز منتشر نشده است.
        </p>

        <section
            v-for="category in categories"
            :key="category.id"
            class="mb-8 last:mb-0"
        >
            <div class="mb-4 flex items-center justify-between">
                <h2>{{ category.name }}</h2>
                <span
                    class="rounded-full border border-rtds-border px-3 py-1 text-xs text-rtds-text-secondary"
                >
                    {{ category.mode }}
                </span>
            </div>

            <ul v-if="category.bouts.length" class="space-y-3">
                <li
                    v-for="bout in category.bouts"
                    :key="bout.id"
                    class="rounded-xl bg-rtds-bg-card px-5 py-4"
                >
                    <span class="block text-xs text-rtds-text-tertiary">
                        {{ bout.label }}
                    </span>

                    <!-- بلوک هر ورزشکار: Accuracy / Presentation / Total
                         سقف‌ها ثابت‌اند (۴/۶/۱۰) و کادر زرد فقط روی total برنده می‌آید. -->
                    <div class="mt-3 flex flex-wrap items-start gap-12">
                        <ScoreCard
                            v-for="(s, index) in bout.sides"
                            :key="index"
                            class="w-[258px]"
                            :side="s.side"
                            :winner="s.isWinner"
                        >
                            <ScoreItem
                                label="Accuracy"
                                :max="4"
                                :value="s.accuracy"
                                :side="s.side"
                            />
                            <ScoreItem
                                label="Presentation"
                                :max="6"
                                :value="s.presentation"
                                :side="s.side"
                            />
                            <ScoreItem
                                label="Total Score"
                                :max="10"
                                :value="s.total"
                                variant="total"
                                :side="s.side"
                            />
                        </ScoreCard>
                    </div>
                </li>
            </ul>
        </section>
    </ScoreboardLayout>
</template>
