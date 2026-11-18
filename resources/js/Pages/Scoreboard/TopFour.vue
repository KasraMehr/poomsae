<script setup>
import { computed } from "vue";
import { Head } from "@inertiajs/vue3";
import ScoreboardLayout from "../../Shared/Layouts/ScoreboardLayout.vue";
import ScoreboardHeader from "../../Components/scoreboard/ScoreboardHeader.vue";
import TopFourTable from "../../Components/scoreboard/tables/TopFourTable.vue";

const props = defineProps({
    display: { type: Object, default: null },
    tournament: { type: Object, default: null },
});

// Read the production Inertia envelope while keeping standalone lab snapshots supported.
const tournament = computed(
    () => props.tournament ?? props.display?.tournament ?? null,
);

const categories = computed(() =>
    (tournament.value?.categories ?? []).map((category) => {
        const placements =
            category.placements?.length > 0
                ? category.placements
                : category.format === "round_robin"
                  ? category.standings ?? []
                  : [];

        return {
            id: category.id,
            name: category.name ?? "",
            rows: [...placements]
                .filter((placement) => {
                    const rank = Number(placement.rank);

                    return (
                        placement.rank != null &&
                        Number.isFinite(rank) &&
                        rank >= 1 &&
                        rank <= 4
                    );
                })
                .sort((left, right) => Number(left.rank) - Number(right.rank))
                .map((placement) => ({
                    rank: placement.rank,
                    name: placement.name ?? "",
                    country: placement.country ?? "",
                    countryCode: placement.country_code ?? "",
                    flagUrl: placement.flag_url ?? "",
                    score: placement.score ?? null,
                })),
        };
    }),
);

const stage = computed(
    () =>
        tournament.value?.categories?.[0]?.stage ??
        tournament.value?.stage ??
        "",
);
const categoryLabel = computed(() => categories.value[0]?.name ?? "");
</script>

<template>
    <!-- <Head title="topFour"/> -->
    <ScoreboardLayout
        :stage="stage"
        :center="categoryLabel"
        :category="categoryLabel"
    >
        <template #header>
            <ScoreboardHeader
                :stage="stage"
                :category="categoryLabel"
                right-label="TOP FOUR"
            />
        </template>

        <div v-if="categories.length" class="flex flex-1 flex-col">
            <section
                v-for="(category, index) in categories"
                :key="category.id"
                class="mx-2 flex min-w-0 flex-1 flex-col justify-center"
            >
                <h2
                    v-if="index > 0"
                    class="mb-4 text-2xl uppercase text-rtds-text-secondary"
                >
                    {{ category.name }}
                </h2>
                <TopFourTable v-if="category.rows.length" :rows="category.rows" />
                <p
                    v-else
                    class="rounded-xl border border-rtds-border-subtle bg-rtds-bg-card p-6 text-center text-rtds-text-secondary"
                >
                    چهار رتبهٔ برتر این دسته هنوز در دسترس نیست.
                </p>
            </section>
        </div>

        <p
            v-else
            class="flex flex-1 items-center justify-center rounded-2xl border border-rtds-border-subtle bg-rtds-bg-card p-12 text-center text-rtds-text-secondary"
        >
            رتبه‌بندی‌ای برای نمایش وجود ندارد.
        </p>
    </ScoreboardLayout>
</template>
