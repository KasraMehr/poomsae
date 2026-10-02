<script setup>
import { computed } from "vue";
import { Head } from "@inertiajs/vue3";
import ScoreboardLayout from "../../Shared/Layouts/ScoreboardLayout.vue";
import ScoreboardHeader from "../../Components/scoreboard/ScoreboardHeader.vue";
import AthleteInfo from "../../Components/scoreboard/AthleteInfo.vue";

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
                .map((bout) => ({
                    id: bout.id,
                    label: `${round.name} · رقابت ${bout.sequence}`,
                    winner: bout.entries.find(
                        (entry) => entry.id === bout.winner_entry_id,
                    )?.name ?? null,
                    winnerSide: bout.entries.find(
                        (entry) => entry.id === bout.winner_entry_id,
                    )?.side ?? null,
                    total: bout.totals?.[bout.winner_entry_id] ?? null,
                    roundsCount: category.rounds.length,
                })),
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

const score = (value) =>
    value == null
        ? "—"
        : Number(value).toLocaleString("fa-IR", {
              minimumFractionDigits: 3,
              maximumFractionDigits: 6,
          });

/*
 * TODO / Proposed Contract (مرحلهٔ بعد):
 * - country / country_code / flag برندگان → AthleteInfo کامل‌تر
 * - کلید سمبل فرم هر رقابت (`form_number`)
 * - scoreTypeLabel / نوع نمره (میانگین دو فرم)
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
                    class="flex items-center justify-between gap-4 rounded-xl bg-rtds-bg-card px-5 py-4"
                >
                    <div class="min-w-0">
                        <span class="block text-xs text-rtds-text-tertiary">
                            {{ bout.label }}
                        </span>
                        <AthleteInfo
                            v-if="bout.winner"
                            class="mt-2"
                            layout="horizontal"
                            size="sm"
                            :name="bout.winner"
                            :side="bout.winnerSide"
                        />
                    </div>
                    <b class="shrink-0 text-2xl tabular-nums text-rtds-success">
                        {{ score(bout.total) }}
                    </b>
                </li>
            </ul>
        </section>
    </ScoreboardLayout>
</template>
