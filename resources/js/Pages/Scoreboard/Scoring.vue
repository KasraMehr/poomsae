<script setup>
import { computed } from "vue";
import ScoreboardLayout from "../../Shared/Layouts/ScoreboardLayout.vue";
import ScoreboardHeader from "../../Components/scoreboard/ScoreboardHeader.vue";
import AthleteInfo from "../../Components/scoreboard/AthleteInfo.vue";
import FormBadge from "../../Components/scoreboard/FormBadge.vue";
import ScoreItem from "../../Components/scoreboard/ScoreItem.vue";
import SingleScoringTable from "../../Components/scoreboard/tables/SingleScoringTable.vue";
import DoubleScoringTable from "../../Components/scoreboard/tables/DoubleScoringTable.vue";
import FreestyleScoringTable from "../../Components/scoreboard/tables/FreestyleScoringTable.vue";

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

const isActivePerformance = (performance) =>
    ["running", "scoring"].includes(performance.status);

const scoringVariant = (category) => {
    if (category.discipline === "freestyle") {
        return "freestyle";
    }

    if (
        category.format === "knockout" &&
        category.execution_mode === "simultaneous"
    ) {
        return "double";
    }

    return "single";
};

const scoringPanels = computed(() =>
    (props.tournament?.categories ?? []).flatMap((category) =>
        (category.rounds ?? []).flatMap((round) =>
            (round.bouts ?? [])
                .filter((bout) => bout.status === "running")
                .flatMap((bout) => {
                    const variant = scoringVariant(category);
                    const performances = bout.performances ?? [];
                    const groups =
                        variant === "double"
                            ? Array.from(
                                  performances.reduce(
                                      (grouped, performance) => {
                                          const formNumber =
                                              performance.form_number ?? 0;
                                          grouped.set(formNumber, [
                                              ...(grouped.get(formNumber) ??
                                                  []),
                                              performance,
                                          ]);
                                          return grouped;
                                      },
                                      new Map(),
                                  ),
                              ).map(([, group]) => group)
                            : performances
                                  .filter(isActivePerformance)
                                  .map((performance) => [performance]);

                    return groups
                        .filter((group) => group.some(isActivePerformance))
                        .map((group) => ({
                            id:
                                String(bout.id) +
                                "-" +
                                String(group[0]?.form_number ?? 0),
                            bout,
                            category,
                            round,
                            variant,
                            sides: group.map((performance) => ({
                                performance,
                                category,
                                entry: (bout.entries ?? []).find(
                                    (entry) =>
                                        entry.id === performance.entry_id,
                                ),
                            })),
                        }));
                }),
        ),
    ),
);

const currentCategory = computed(
    () => scoringPanels.value[0]?.category ?? null,
);
const currentRound = computed(() => scoringPanels.value[0]?.round ?? null);
const categoryLabel = computed(() => currentCategory.value?.name ?? "");
const roundLabel = computed(() => currentRound.value?.name ?? "SCORING");

const detailOf = (side) => side?.performance?.detailed_scores ?? null;
const judgesOf = (side) => detailOf(side)?.judges ?? [];
const scoreValue = (side, key) => {
    const detailed = detailOf(side);

    if (detailed?.[key] != null) {
        return detailed[key];
    }

    return key === "total"
        ? (side?.performance?.result ??
              side?.performance?.preview_result ??
              null)
        : null;
};

const maximumOf = (side, key, fallback) => {
    const detailedMaximum = detailOf(side)?.[key + "_max"];

    if (detailedMaximum != null) {
        return Number(detailedMaximum);
    }

    const ruleMaximum = side?.category?.rules?.[key + "_max"];

    if (ruleMaximum == null) {
        return fallback;
    }

    const maximum = Number(ruleMaximum);

    return maximum > 20 ? maximum / 100 : maximum;
};

const totalMaximumOf = (side) => {
    const detailedMaximum = detailOf(side)?.total_max;

    return detailedMaximum == null
        ? maximumOf(side, "accuracy", 4) + maximumOf(side, "presentation", 6)
        : Number(detailedMaximum);
};

const singleJudgesOf = (side) =>
    judgesOf(side).map((judge, index) => ({
        ...judge,
        label: judge.label ?? "J" + (judge.seat ?? index + 1),
        accuracy: judge.accuracy,
        eoe: judge.presentation_components?.[0] ?? null,
        sp: judge.presentation_components?.[1] ?? null,
        rp: judge.presentation_components?.[2] ?? null,
        presentation: judge.presentation,
    }));

const freestyleJudgesOf = (side) =>
    judgesOf(side).map((judge, index) => ({
        ...judge,
        label: judge.label ?? "J" + (judge.seat ?? index + 1),
        accuracy: judge.accuracy,
        presentation: judge.presentation,
        accuracyParts: judge.accuracy_components ?? [],
        presentationParts:
            judge.presentation_components ??
            (judge.presentation_penalties ?? []).map((value) => -Number(value)),
    }));

const isFreestylePenaltyMode = (side) =>
    detailOf(side)?.method === "accuracy_6_presentation_4" ||
    (detailOf(side)?.accuracy_components?.length === 3 &&
        detailOf(side)?.presentation_penalties?.length > 0);

const athleteProps = (entry) => ({
    number: entry?.number ?? "",
    name: entry?.name ?? "",
    side: entry?.side ?? "",
    country: entry?.country ?? "",
    countryCode: entry?.country_code ?? "",
    flagUrl: entry?.flag_url ?? "",
});
</script>

<template>
    <ScoreboardLayout
        :stage="stage"
        :center="tournament?.name ?? ''"
        :category="categoryLabel"
    >
        <template #header>
            <ScoreboardHeader
                :stage="roundLabel"
                event-title="SCORING"
                :category="categoryLabel"
            />
        </template>

        <p
            v-if="!scoringPanels.length"
            class="flex flex-1 items-center justify-center rounded-2xl border border-rtds-border-subtle bg-rtds-bg-card p-12 text-center text-rtds-text-secondary"
        >
            در حال حاضر رقابتی در حال اجرا نیست.
        </p>

        <section
            v-for="panel in scoringPanels"
            :key="panel.id"
            class="min-h-full"
        >
            <!-- Double scoring layout: mirrored athlete tables for simultaneous bouts. -->
            <div v-if="panel.variant === 'double'" class="flex flex-col gap-6">
                <div class="flex items-center justify-between gap-6 pt-10">
                    <AthleteInfo
                        v-for="side in panel.sides"
                        :key="side.entry?.id"
                        v-bind="athleteProps(side.entry)"
                        layout="horizontal"
                        size="xl"
                    />
                </div>
                <div class="grid grid-cols-2 gap-6 max-[900px]:grid-cols-1">
                    <DoubleScoringTable
                        v-for="side in panel.sides"
                        :key="side.entry?.id"
                        :side="side.entry?.side ?? 'chung'"
                        :judges="singleJudgesOf(side)"
                        :judge-count="panel.category.judge_count ?? 5"
                    />
                </div>
            </div>

            <div
                v-else-if="panel.sides[0]"
                class="flex min-h-full flex-col justify-between gap-5"
            >
                <div class="flex items-center justify-between gap-6">
                    <AthleteInfo
                        v-bind="athleteProps(panel.sides[0].entry)"
                        layout="horizontal"
                        size="xl"
                    />
                    <FormBadge
                        size="xxl"
                        :round-label="panel.round.name"
                        :form-name="panel.sides[0].performance.form_name ?? ''"
                        :form-number="
                            panel.sides[0].performance.form_number ?? null
                        "
                        :symbol-key="
                            panel.sides[0].performance.symbol_key ?? null
                        "
                    />
                </div>
                <!-- Single scoring layout: judge rows and summary scores. -->
                <div
                    v-if="panel.variant === 'single'"
                    class="grid flex-1 grid-cols-[minmax(760px,1.6fr)_minmax(440px,1fr)] items-stretch gap-5 overflow-x-auto max-[1250px]:grid-cols-1"
                >
                    <SingleScoringTable
                        :judges="singleJudgesOf(panel.sides[0])"
                        :judge-count="panel.category.judge_count ?? 5"
                    />

                    <div
                        class="grid min-h-[290px] min-w-[440px] grid-cols-[minmax(0,0.8fr)_minmax(280px,1.5fr)] items-stretch gap-5"
                    >
                        <div class="grid min-h-0 min-w-0 grid-rows-2 gap-3">
                            <ScoreItem
                                label="Accuracy"
                                :max="maximumOf(panel.sides[0], 'accuracy', 4)"
                                :value="scoreValue(panel.sides[0], 'accuracy')"
                                :side="panel.sides[0].entry?.side"
                                size="xl"
                            />

                            <ScoreItem
                                label="Presentation"
                                :max="
                                    maximumOf(panel.sides[0], 'presentation', 6)
                                "
                                :value="
                                    scoreValue(panel.sides[0], 'presentation')
                                "
                                :side="panel.sides[0].entry?.side"
                                size="xl"
                            />
                        </div>

                        <ScoreItem
                            label="Total Score"
                            :max="totalMaximumOf(panel.sides[0])"
                            :value="scoreValue(panel.sides[0], 'total')"
                            variant="total"
                            :side="panel.sides[0].entry?.side"
                            size="xxl"
                        />
                    </div>
                </div>
                <!-- Freestyle scoring layout: variable accuracy and presentation parts. -->
                <div
                    v-else
                    class="grid flex-1 grid-cols-[minmax(760px,1.6fr)_minmax(440px,1fr)] items-stretch gap-5 overflow-x-auto max-[1250px]:grid-cols-1"
                >
                    <FreestyleScoringTable
                        :judges="freestyleJudgesOf(panel.sides[0])"
                        :judge-count="panel.category.judge_count ?? 5"
                        :accuracy-count="
                            isFreestylePenaltyMode(panel.sides[0]) ? 3 : 4
                        "
                        :presentation-count="
                            isFreestylePenaltyMode(panel.sides[0]) ? 4 : 6
                        "
                        :presentation-label="
                            isFreestylePenaltyMode(panel.sides[0])
                                ? 'PRESENTATION DEDUCTIONS'
                                : 'PRESENTATION'
                        "
                    />
                    <div
                        class="grid min-h-[290px] min-w-[440px] grid-cols-[minmax(0,0.8fr)_minmax(280px,1.5fr)] items-stretch gap-5"
                    >
                        <div class="grid min-h-0 min-w-0 grid-rows-2 gap-3">
                            <ScoreItem
                                label="Accuracy"
                                :max="maximumOf(panel.sides[0], 'accuracy', 4)"
                                :value="scoreValue(panel.sides[0], 'accuracy')"
                                :side="panel.sides[0].entry?.side"
                                size="xl"
                            />
                            <ScoreItem
                                label="Presentation"
                                :max="
                                    maximumOf(panel.sides[0], 'presentation', 6)
                                "
                                :value="
                                    scoreValue(panel.sides[0], 'presentation')
                                "
                                :side="panel.sides[0].entry?.side"
                                size="xl"
                            />
                        </div>
                        <ScoreItem
                            label="Total Score"
                            :max="totalMaximumOf(panel.sides[0])"
                            :value="scoreValue(panel.sides[0], 'total')"
                            variant="total"
                            :side="panel.sides[0].entry?.side"
                            size="xxl"
                        />
                    </div>
                </div>
            </div>
        </section>
    </ScoreboardLayout>
</template>
