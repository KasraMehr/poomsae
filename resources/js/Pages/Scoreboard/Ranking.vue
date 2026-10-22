<script setup>
import { computed } from "vue";
import ScoreboardLayout from "../../Shared/Layouts/ScoreboardLayout.vue";
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
            <header class="shrink-0 px-12 pt-8 pb-4">
                <div
                    class="grid grid-cols-[minmax(190px,0.85fr)_minmax(280px,1fr)_minmax(320px,1.5fr)] items-center gap-5"
                >
                    <span
                        class="text-[45px] leading-[36px] uppercase text-rtds-text-secondary"
                    >
                        {{ stage }}
                    </span>
                    <span
                        class="text-[45px] leading-[36px] uppercase text-rtds-text-secondary"
                    >
                        {{ categoryLabel }}
                    </span>
                    <div
                        class="grid grid-cols-2 justify-self-end gap-[6.55px] text-[45px] leading-[36px] uppercase text-rtds-text-secondary"
                    >
                        <span class="w-[142px] text-center">R - 1</span>
                        <span
                            class="w-[142px] border-l border-rtds-text-light text-center"
                        >
                            R - 2
                        </span>
                    </div>
                </div>
                <div class="mt-4 h-px w-full bg-rtds-divider" />
            </header>
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
                    رتبه‌بندی این دسته هنوز در دسترس نیست.
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
