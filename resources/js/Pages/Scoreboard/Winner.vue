<script setup>
import { computed } from "vue";
import ScoreboardLayout from "../../Shared/Layouts/ScoreboardLayout.vue";
import AthleteInfo from "../../Components/scoreboard/AthleteInfo.vue";
import FormBadge from "../../Components/scoreboard/FormBadge.vue";
import ScoreItem from "../../Components/scoreboard/ScoreItem.vue";

const props = defineProps({
    display: { type: Object, default: null },
    tournament: { type: Object, default: null },
});

const tournament = computed(
    () => props.display?.tournament ?? props.tournament,
);

const completedWinnerBouts = computed(() => {
    const bouts = [];

    for (const category of tournament.value?.categories ?? []) {
        for (const round of category.rounds ?? []) {
            for (const bout of round.bouts ?? []) {
                if (
                    bout.status !== "completed" ||
                    bout.winner_entry_id == null ||
                    !Array.isArray(bout.entries) ||
                    bout.entries.length !== 2
                ) {
                    continue;
                }

                const winnerEntry = bout.entries.find(
                    (entry) =>
                        String(entry.id) === String(bout.winner_entry_id),
                );

                if (!winnerEntry) {
                    continue;
                }

                bouts.push({
                    category,
                    round,
                    bout,
                    winnerEntry,
                    total: bout.totals?.[winnerEntry.id] ?? null,
                });
            }
        }
    }

    return bouts;
});

const winner = computed(() => {
    const currentResult = tournament.value?.current_result;

    if (currentResult != null) {
        if (
            currentResult.status !== "completed" ||
            ![1, 2].includes(Number(currentResult.round_count)) ||
            currentResult.winner_entry_id == null ||
            !Array.isArray(currentResult.bout_ids) ||
            currentResult.bout_ids.length !== Number(currentResult.round_count)
        ) {
            return null;
        }

        const category = tournament.value?.categories?.find(
            (item) => String(item.id) === String(currentResult.category_id),
        );
        const categoryBouts =
            category?.rounds?.flatMap((round) =>
                (round.bouts ?? []).map((bout) => ({ round, bout })),
            ) ?? [];
        const executionBouts = currentResult.bout_ids.map((id) =>
            categoryBouts.find(({ bout }) => String(bout.id) === String(id)),
        );
        const hasValidExecutionBouts =
            new Set(currentResult.bout_ids.map(String)).size ===
                currentResult.bout_ids.length &&
            executionBouts.every(
                ({ bout } = {}) =>
                    bout?.status === "completed" &&
                    Array.isArray(bout.entries) &&
                    bout.entries.length === 2 &&
                    bout.entries.some(
                        (entry) =>
                            String(entry.id) ===
                            String(currentResult.winner_entry_id),
                    ),
            );
        const firstBoutEntries = executionBouts[0]?.bout?.entries ?? [];
        const sameMatchup =
            hasValidExecutionBouts &&
            executionBouts.every(({ bout }) =>
                firstBoutEntries.every((entry) =>
                    bout.entries.some(
                        (otherEntry) =>
                            String(otherEntry.id) === String(entry.id),
                    ),
                ),
            );
        const winnerEntry = sameMatchup
            ? executionBouts[0].bout.entries.find(
                  (entry) =>
                      String(entry.id) ===
                      String(currentResult.winner_entry_id),
              )
            : null;

        if (!winnerEntry) {
            return null;
        }

        return {
            category,
            winnerEntry,
            executionBouts,
            roundCount: Number(currentResult.round_count),
            total: currentResult.winner_total ?? null,
            totalMax: currentResult.total_max ?? null,
        };
    }

    return completedWinnerBouts.value.length === 1
        ? completedWinnerBouts.value[0]
        : null;
});

const stage = computed(
    () =>
        winner.value?.executionBouts?.at(-1)?.round?.stage_label ??
        winner.value?.round?.stage_label ??
        "",
);
const categoryLabel = computed(() => winner.value?.category?.name ?? "");

const roundResults = computed(() => {
    const executionBouts =
        winner.value?.executionBouts ??
        (winner.value?.bout
            ? [{ round: winner.value.round, bout: winner.value.bout }]
            : []);

    return executionBouts.map(({ round, bout }, index) => {
        const performances = (bout.performances ?? [])
            .filter(
                (item) =>
                    String(item.entry_id) ===
                    String(winner.value.winnerEntry.id),
            )
            .sort(
                (left, right) =>
                    Number(left.form_number) - Number(right.form_number),
            );

        return {
            key: String(round?.id ?? bout.id),
            roundLabel: `R-${index + 1}`,
            forms: performances.map((performance) => ({
                key: String(performance.id),
                formNumber: performance.form_number,
                symbolKey: performance.form_badge ?? null,
                formName: performance.form_name ?? "",
            })),
            total: bout.totals?.[winner.value.winnerEntry.id] ?? null,
            totalMax: null,
        };
    });
});

const isTwoRoundResult = computed(
    () => (winner.value?.roundCount ?? roundResults.value.length) === 2,
);
</script>

<template>
    <ScoreboardLayout :stage="stage" center="WINNER" :category="categoryLabel">
        <template #header>
            <header class="shrink-0 px-10 pt-8 pb-4">
                <div class="grid grid-cols-3 items-center gap-4">
                    <span
                        class="justify-self-start text-[45px] leading-[36px] uppercase tracking-wide text-rtds-text-secondary"
                    >
                        {{ stage }}
                    </span>
                    <h1
                        class="text-center text-[64px] leading-[36px] font-semibold uppercase tracking-wider text-rtds-gold-bright"
                    >
                        WINNER
                    </h1>
                    <span
                        class="justify-self-end text-right text-[45px] leading-[36px] uppercase tracking-wide text-rtds-text-secondary"
                    >
                        {{ categoryLabel }}
                    </span>
                </div>
                <div class="mt-4 h-px w-full bg-rtds-divider" />
            </header>
        </template>

        <section
            v-if="winner"
            class="flex min-h-0 flex-1 flex-col items-center justify-center gap-5"
        >
            <div class="w-full bg-rtds-gold-bright/20 px-8 py-2">
                <AthleteInfo
                    :number="winner.winnerEntry.number ?? ''"
                    :name="winner.winnerEntry.name ?? ''"
                    :side="winner.winnerEntry.side ?? ''"
                    :country="winner.winnerEntry.country ?? ''"
                    :country-code="winner.winnerEntry.country_code ?? ''"
                    :flag-url="winner.winnerEntry.flag_url ?? ''"
                    layout="horizontal"
                    size="xxl"
                    class="w-full"
                />
            </div>

            <template v-if="isTwoRoundResult">
                <ScoreItem
                    label="Total Score"
                    :max="winner.totalMax"
                    :value="winner.total"
                    variant="total"
                    :side="winner.winnerEntry.side ?? 'chung'"
                    size="xxl"
                    class="h-[min(26vh,250px)] w-full max-w-[760px]"
                />

                <div
                    class="grid min-h-0 w-full flex-1 grid-cols-2 items-center gap-8 px-6"
                >
                    <div
                        v-for="round in roundResults"
                        :key="round.key"
                        class="flex min-w-0 flex-col items-center justify-start gap-3"
                    >
                        <div class="flex flex-col items-center gap-3">
                            <FormBadge
                                v-for="form in round.forms"
                                :key="form.key"
                                :round-label="round.roundLabel"
                                :form-number="form.formNumber"
                                :symbol-key="form.symbolKey"
                                :form-name="form.formName"
                                size="xxl"
                            />
                        </div>
                        <ScoreItem
                            label="Round Total"
                            :max="round.totalMax"
                            :value="round.total"
                            variant="total"
                            :side="winner.winnerEntry.side ?? 'chung'"
                            size="xl"
                            class="h-[min(23vh,210px)] w-full max-w-[520px]"
                        />
                    </div>
                </div>
            </template>

            <template v-else>
                <ScoreItem
                    label="Total Score"
                    :max="winner.totalMax"
                    :value="winner.total"
                    variant="total"
                    :side="winner.winnerEntry.side ?? 'chung'"
                    size="xxl"
                    class="h-[min(42vh,420px)] w-full max-w-[760px]"
                />

                <div
                    v-if="roundResults[0]?.forms.length"
                    class="flex flex-col items-center gap-3"
                >
                    <FormBadge
                        v-for="form in roundResults[0].forms"
                        :key="form.key"
                        :round-label="roundResults[0].roundLabel"
                        :form-number="form.formNumber"
                        :symbol-key="form.symbolKey"
                        :form-name="form.formName"
                        size="xxxl"
                    />
                </div>
            </template>
        </section>

        <p
            v-else
            class="flex flex-1 items-center justify-center text-center text-2xl uppercase text-rtds-text-secondary"
        >
            No current completed winner bout is available.
        </p>
    </ScoreboardLayout>
</template>
