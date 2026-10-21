<script setup>
import { computed } from "vue";
import ScoreboardLayout from "../../Shared/Layouts/ScoreboardLayout.vue";
import TopFourTable from "../../Components/scoreboard/tables/TopFourTable.vue";

const props = defineProps({
    tournament: { type: Object, default: null },
});

const categories = computed(() =>
    (props.tournament?.categories ?? []).map((category) => ({
        id: category.id,
        name: category.name ?? "",
        rows: [...(category.placements ?? [])]
            .filter((placement) => Number(placement.rank) <= 4)
            .sort((left, right) => Number(left.rank) - Number(right.rank))
            .map((placement) => ({
                rank: placement.rank,
                name: placement.name ?? "",
                country: placement.country ?? "",
                countryCode: placement.country_code ?? "",
                flagUrl: placement.flag_url ?? "",
                score: placement.score ?? null,
            })),
    })),
);

const stage = computed(
    () =>
        props.tournament?.categories?.[0]?.stage ??
        props.tournament?.stage ??
        "",
);
const categoryLabel = computed(() => categories.value[0]?.name ?? "");
</script>

<template>
    <ScoreboardLayout
        :stage="stage"
        :center="categoryLabel"
        :category="categoryLabel"
    >
        <template #header>
            <header class="shrink-0 px-12 pt-8 pb-4">
                <div
                    class="grid grid-cols-[minmax(0,1.5fr)_minmax(280px,1fr)] items-center gap-5"
                >
                    <span
                        class="text-[64px] leading-[36px] uppercase text-rtds-text-secondary"
                    >
                        {{ stage }}
                    </span>
                    <span
                        class="text-[64px] leading-[36px] uppercase text-rtds-text-secondary"
                    >
                        {{ categoryLabel }}
                    </span>
                </div>
                <div class="mt-4 h-px w-full bg-rtds-divider" />
            </header>
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
