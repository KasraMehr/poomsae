<script setup>
import { computed } from "vue";
import { Head } from "@inertiajs/vue3";
import ScoreboardLayout from "../../Shared/Layouts/ScoreboardLayout.vue";
import ScoreboardHeader from "../../Components/scoreboard/ScoreboardHeader.vue";
import RankingTable from "../../Components/scoreboard/tables/RankingTable.vue";

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
 * فقط داده‌ای که امروز در snapshot هست:
 * - standings فقط برای format=round_robin
 * - champion فقط برای format=knockout
 * Proposed Contract: placements[]  (برای هر قالبی رتبه‌بندی یکدست بدهد)
 */
const categories = computed(() =>
    (props.tournament?.categories ?? []).map((category) => ({
        id: category.id,
        name: category.name,
        // ردیف‌های RankingRow از standings واقعی ساخته می‌شوند.
        rows: (category.standings ?? []).map((row) => ({
            rank: row.rank,
            name: row.name,
            // [Proposed] placements[].round_scores — تا رسیدن بک‌اند نمرهٔ راند‌ها خالی است («—»)
            roundScores: [],
        })),
        champion:
            category.champion_id != null
                ? (category.entries.find(
                      (entry) => entry.id === category.champion_id,
                  )?.name ?? null)
                : null,
    })),
);

/*
 * TODO / Proposed Contract (مرحلهٔ بعد):
 * - placements[] برای قالب knockout (الان فقط round_robin standings داریم)
 * - placements[].round_scores [R-1, R-2] → ستون‌های نمرهٔ جدول
 * - country / country_code / flag هر ورزشکار → بلوک کشور ردیف
 * - هدر (stage/دسته/برچسب R-1|R-2) متعلق به صفحه است — برچسب ستون‌ها را صفحه رندر می‌کند
 * - logoUrl هدر
 */
</script>

<template>
    <!-- <Head title="رتبه‌بندی" /> -->
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

        <section
            v-for="category in categories"
            :key="category.id"
            class="mb-8 last:mb-0"
        >
            <div class="mb-4 flex items-center justify-between">
                <h2>{{ category.name }}</h2>
                <b
                    v-if="category.champion"
                    class="rounded-full border border-rtds-yellow-dark bg-rtds-bg-card px-4 py-1 text-sm text-rtds-yellow"
                >
                    قهرمان: {{ category.champion }}
                </b>
            </div>

            <RankingTable v-if="category.rows.length" :rows="category.rows" />

            <p
                v-else-if="!category.champion"
                class="rounded-xl border border-rtds-border-subtle bg-rtds-bg-card p-6 text-center text-rtds-text-secondary"
            >
                رتبه‌بندی این دسته هنوز در دسترس نیست.
            </p>
        </section>
    </ScoreboardLayout>
</template>
