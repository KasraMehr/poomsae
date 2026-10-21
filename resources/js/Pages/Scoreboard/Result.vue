<script setup>
import { computed } from "vue";
import ScoreboardLayout from "../../Shared/Layouts/ScoreboardLayout.vue";
import ScoreboardHeader from "../../Components/scoreboard/ScoreboardHeader.vue";
import AthleteInfo from "../../Components/scoreboard/AthleteInfo.vue";
import ScoreItem from "../../Components/scoreboard/ScoreItem.vue";
import { Head } from "@inertiajs/vue3";

const props = defineProps({
    tournament: { type: Object, default: null },
});

const resultPanels = computed(() => {
    const panels = [];

    for (const category of props.tournament?.categories ?? []) {
        const matchups = new Map();

        for (const round of category.rounds ?? []) {
            for (const bout of round.bouts ?? []) {
                if (
                    bout.status !== "completed" ||
                    !Array.isArray(bout.entries) ||
                    bout.entries.length !== 2
                ) {
                    continue;
                }

                const entries = [...bout.entries].sort((left, right) =>
                    String(left.id).localeCompare(String(right.id)),
                );
                const matchupKey = entries.map((entry) => entry.id).join(":");
                const matchup = matchups.get(matchupKey) ?? {
                    key: matchupKey,
                    entries,
                    rounds: [],
                };

                matchup.rounds.push({ round, bout });
                matchups.set(matchupKey, matchup);
            }
        }

        for (const matchup of matchups.values()) {
            const rounds = matchup.rounds
                .sort((left, right) =>
                    String(left.round.id).localeCompare(
                        String(right.round.id),
                        undefined,
                        {
                            numeric: true,
                        },
                    ),
                )
                .slice(0, 2);
            const sides = matchup.entries
                .sort((left, right) => {
                    const sideOrder = { chung: 0, hong: 1 };

                    return (
                        (sideOrder[left.side] ?? 2) -
                        (sideOrder[right.side] ?? 2)
                    );
                })
                .map((entry) => ({
                    entry,
                    rounds: rounds.map(({ round, bout }) => {
                        const roundEntry = bout.entries.find(
                            (candidate) => candidate.id === entry.id,
                        );

                        return {
                            id: round.id,
                            label: String(round.name ?? "").replace(
                                /\s*-\s*/g,
                                "-",
                            ),
                            accuracy: roundEntry?.accuracy_score ?? null,
                            presentation:
                                roundEntry?.presentation_score ?? null,
                            total: bout.totals?.[entry.id] ?? null,
                        };
                    }),
                }));

            panels.push({
                id: String(category.id) + "-" + matchup.key,
                category,
                stage: category.stage ?? props.tournament?.stage ?? "",
                title:
                    rounds.length > 1
                        ? "RESULT"
                        : (sides[0]?.rounds[0]?.label ?? "RESULT"),
                rounds,
                sides,
            });
        }
    }

    return panels;
});

const stage = computed(() => resultPanels.value[0]?.stage ?? "");
const categoryLabel = computed(
    () => resultPanels.value[0]?.category?.name ?? "",
);
const title = computed(() => resultPanels.value[0]?.title ?? "RESULT");
</script>

<template>
    <!-- <Head title="result"/> -->
    <ScoreboardLayout :stage="stage" :center="title" :category="categoryLabel">
        <template #header>
            <ScoreboardHeader
                :stage="stage"
                :event-title="title"
                :category="categoryLabel"
            />
        </template>

        <p
            v-if="!resultPanels.length"
            class="flex flex-1 items-center justify-center rounded-2xl border border-rtds-border-subtle bg-rtds-bg-card p-12 text-center text-rtds-text-secondary"
        >
            نتیجهٔ اجرای ثبت‌شده‌ای وجود ندارد.
        </p>

        <section
            v-for="panel in resultPanels"
            :key="panel.id"
            class="flex min-h-0 flex-1 flex-col"
        >
            <div class="grid grid-cols-2 items-center gap-[60px]">
                <AthleteInfo
                    v-for="side in panel.sides"
                    :key="side.entry.id"
                    :number="side.entry.number ?? ''"
                    :name="side.entry.name ?? ''"
                    :side="side.entry.side ?? ''"
                    :country="side.entry.country ?? ''"
                    :country-code="side.entry.country_code ?? ''"
                    :flag-url="side.entry.flag_url ?? ''"
                    layout="horizontal"
                    size="xl"
                />
            </div>

            <!-- One-round result: each athlete has a stacked subscore column and a full-height total. -->
            <div
                v-if="panel.rounds.length === 1"
                class="mt-[45px] grid flex-1 grid-cols-2 items-stretch gap-[60px]"
            >
                <div
                    v-for="side in panel.sides"
                    :key="side.entry.id"
                    class="grid min-h-[365px] grid-cols-2 grid-rows-2 gap-x-8 gap-y-6"
                >
                    <ScoreItem
                        label="Accuracy"
                        :max="4"
                        :value="side.rounds[0]?.accuracy"
                        :side="side.entry.side"
                        size="xxl"
                        :class="
                            side.entry.side === 'hong'
                                ? 'col-start-2 row-start-1'
                                : 'col-start-1 row-start-1'
                        "
                    />
                    <ScoreItem
                        label="Presentation"
                        :max="6"
                        :value="side.rounds[0]?.presentation"
                        :side="side.entry.side"
                        size="xxl"
                        :class="
                            side.entry.side === 'hong'
                                ? 'col-start-2 row-start-2'
                                : 'col-start-1 row-start-2'
                        "
                    />
                    <ScoreItem
                        label="Total Score"
                        :max="10"
                        :value="side.rounds[0]?.total"
                        variant="total"
                        :side="side.entry.side"
                        size="xxl"
                        class="row-span-2"
                        :class="
                            side.entry.side === 'hong'
                                ? 'col-start-1'
                                : 'col-start-2'
                        "
                    />
                </div>
            </div>

            <!-- Two-round result: each round shows its subscores and total, without winner highlighting. -->
            <div v-else class="mt-2 grid flex-1 grid-cols-2 gap-[60px]">
                <div
                    v-for="side in panel.sides"
                    :key="side.entry.id"
                    class="flex flex-col gap-3"
                >
                    <div
                        v-for="round in side.rounds"
                        :key="round.id"
                        class="min-h-[250px]"
                    >
                        <div
                            class="flex h-8 items-center justify-between bg-rtds-border-strong px-4 font-rtds text-2xl font-semibold uppercase text-white"
                            :dir="side.entry.side === 'hong' ? 'rtl' : 'ltr'"
                        >
                            <span>{{ round.label }}</span>
                            <span>TOTAL</span>
                        </div>
                        <div
                            class="grid min-h-[214px] grid-cols-[minmax(0,1fr)_minmax(0,1fr)] items-stretch gap-5 pt-1"
                            :dir="side.entry.side === 'hong' ? 'rtl' : 'ltr'"
                        >
                            <div class="grid min-h-0 grid-rows-2 gap-2">
                                <ScoreItem
                                    label="ACC"
                                    :max="4"
                                    :value="round.accuracy"
                                    :side="side.entry.side"
                                    size="xxl"
                                    label-placement="inline"
                                    :show-max="false"
                                />
                                <ScoreItem
                                    label="PRE"
                                    :max="6"
                                    :value="round.presentation"
                                    :side="side.entry.side"
                                    size="xxl"
                                    label-placement="inline"
                                    :show-max="false"
                                />
                            </div>
                            <ScoreItem
                                label="Total Score"
                                :max="10"
                                :value="round.total"
                                variant="total"
                                :side="side.entry.side"
                                size="xxl"
                                :show-label="false"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </ScoreboardLayout>
</template>
