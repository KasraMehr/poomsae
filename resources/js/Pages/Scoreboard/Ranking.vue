<script setup>
import { computed } from "vue";
import ScoreboardLayout from "../../Shared/Layouts/ScoreboardLayout.vue";
import ScoreboardHeader from "../../Components/scoreboard/ScoreboardHeader.vue";
import RankingTable from "../../Components/scoreboard/tables/RankingTable.vue";
import { Head } from "@inertiajs/vue3";

const props = defineProps({
    display: { type: Object, default: null },
    tournament: { type: Object, default: null },
});

// Read the production Inertia envelope while keeping standalone lab snapshots supported.
const tournament = computed(
    () => props.tournament ?? props.display?.tournament ?? null,
);

const categories = computed(() =>
    (tournament.value?.categories ?? []).map((category) => ({
        id: category.id,
        name: category.name ?? "",
        rows: (category.placements?.length
            ? category.placements
            : category.format === "round_robin"
              ? (category.standings ?? []).filter(
                    (standing) => standing.rank != null,
                )
              : []
        ).map((placement) => ({
            rank: placement.rank,
            name: placement.name ?? "",
            country: placement.country ?? "",
            countryCode: placement.country_code ?? "",
            flagUrl: placement.flag_url ?? "",
            roundScores:
                placement.round_scores ?? [placement.r1 ?? null, placement.r2 ?? null],
        })),
    })),
);

const stage = computed(
    () =>
        tournament.value?.categories?.[0]?.stage ??
        tournament.value?.stage ??
        "",
);
const categoryLabel = computed(
    () => categories.value[0]?.name ?? "",
);
</script>

<template>
    <!-- <Head title="ranking"/> -->
    <ScoreboardLayout
        :stage="stage"
        :center="categoryLabel"
        :category="categoryLabel"
    >
        <template #header>
            <ScoreboardHeader
                :stage="stage"
                :category="categoryLabel"
                right-label="RANKING"
            />
        </template>

        <div v-if="categories.length" class="flex flex-col gap-8">
            <section
                v-for="(category, index) in categories"
                :key="category.id"
                class="mx-2 min-w-0"
            >
                <h2
                    v-if="index > 0"
                    class="mb-4 text-2xl uppercase text-rtds-text-secondary"
                >
                    {{ category.name }}
                </h2>
                <RankingTable v-if="category.rows.length" :rows="category.rows" />
                <p
                    v-else
                    class="rounded-xl border border-rtds-border-subtle bg-rtds-bg-card p-6 text-center text-rtds-text-secondary"
                >
                    Ranking is not available for this category yet.
                </p>
            </section>
        </div>

        <p
            v-else
            class="flex flex-1 items-center justify-center rounded-2xl border border-rtds-border-subtle bg-rtds-bg-card p-12 text-center text-rtds-text-secondary"
        >
            No ranking is available.
        </p>
    </ScoreboardLayout>
</template>
