<script setup>
import { computed } from "vue";
import { Head } from "@inertiajs/vue3";
import ScoreboardLayout from "../../Shared/Layouts/ScoreboardLayout.vue";
import ScoreboardHeader from "../../Components/scoreboard/ScoreboardHeader.vue";
import AthleteInfo from "../../Components/scoreboard/AthleteInfo.vue";
import FormBadge from "../../Components/scoreboard/FormBadge.vue";

const props = defineProps({
    draw: {
        type: Object,
        default: null,
    },
    tournament: {
        type: Object,
        default: null,
    },
    display: {
        type: Object,
        default: null,
    },
    courtId: {
        type: [Number, String],
        default: null,
    },
});

const drawData = computed(() => {
    if (props.draw) {
        return props.draw;
    }

    const tournament = props.display?.tournament ?? props.tournament;
    const court = props.display?.courts?.find(
        (item) => String(item.id) === String(props.courtId),
    );
    const target = court?.current ?? null;

    if (
        !tournament ||
        !target?.bout_id ||
        !["running", "scoring"].includes(target.status)
    ) {
        return null;
    }

    const matchesId = (left, right) => String(left) === String(right);
    const category = tournament.categories?.find((item) =>
        item.rounds?.some((round) =>
            round.bouts?.some((bout) => matchesId(bout.id, target.bout_id)),
        ),
    );
    const round = category?.rounds?.find((item) =>
        item.bouts?.some((bout) => matchesId(bout.id, target.bout_id)),
    );
    const bout = round?.bouts?.find((item) =>
        matchesId(item.id, target.bout_id),
    );

    if (!category || !round || !bout) {
        return null;
    }

    const performancesByNumber = new Map();
    for (const performance of bout.performances ?? []) {
        if (!performancesByNumber.has(performance.form_number)) {
            performancesByNumber.set(performance.form_number, performance);
        }
    }

    const roundFormNames = round.form_names ?? [];
    const forms = roundFormNames.length
        ? roundFormNames.map((formName, index) => {
              const formNumber = index + 1;
              const performance = performancesByNumber.get(formNumber);

              return {
                  round_label: `R - ${formNumber}`,
                  form_number: formNumber,
                  symbol_key: performance?.form_badge ?? null,
                  form_name: formName,
              };
          })
        : [...performancesByNumber.values()]
              .sort((left, right) => left.form_number - right.form_number)
              .map((performance) => ({
                  round_label: `R - ${performance.form_number}`,
                  form_number: performance.form_number,
                  symbol_key: performance.form_badge,
                  form_name: performance.form_name,
              }));

    return {
        mode: target.entries?.length > 1 ? "double" : "single",
        stage: round.stage_label ?? round.name ?? "",
        title: tournament.name ?? "",
        category: category.name ?? "",
        entries: target.entries ?? [],
        forms,
    };
});

const mode = computed(() =>
    drawData.value?.mode === "double" ? "double" : "single",
);

const entries = computed(() => drawData.value?.entries ?? []);
const forms = computed(() => drawData.value?.forms ?? []);

const singleEntry = computed(() => entries.value[0] ?? null);
const doubleEntries = computed(() => entries.value.slice(0, 2));

const stage = computed(() => drawData.value?.stage ?? "");
const title = computed(() => drawData.value?.title ?? "");
const category = computed(() => drawData.value?.category ?? "");
</script>

<template>
    <!-- <Head title="draw"/> -->
    <div dir="ltr">
        <ScoreboardLayout>
            <template #header>
                <ScoreboardHeader
                    size="lg"
                    :stage="stage"
                    :event-title="title"
                    :category="category"
                />
            </template>

            <main class="flex min-h-0 flex-1 flex-col items-center px-12">
                <!-- SINGLE -->
                <section
                    v-if="mode === 'single' && singleEntry"
                    class="flex w-full flex-col items-center pt-10"
                >
                    <AthleteInfo
                        layout="horizontal"
                        size="xxl"
                        :number="singleEntry.number"
                        :name="singleEntry.name"
                        :country="singleEntry.country"
                        :country-code="singleEntry.country_code"
                        :flag-url="singleEntry.flag_url"
                        side="chung"
                    />
                </section>

                <!-- DOUBLE
                     آماده برای حالت دو نفرهٔ Chung / Hong.
                     این حالت pair/team نیست.
                -->
                <section
                    v-else-if="mode === 'double'"
                    class="grid w-full grid-cols-2 gap-10 pt-10"
                >
                    <AthleteInfo
                        v-for="entry in doubleEntries"
                        :key="entry.id"
                        layout="vertical-under"
                        size="xl"
                        :number="entry.number"
                        :name="entry.name"
                        :country="entry.country"
                        :country-code="entry.country_code"
                        :flag-url="entry.flag_url"
                        :side="entry.side"
                    />
                </section>

                <!-- FORM DRAW -->
                <section
                    v-if="forms.length"
                    class="flex w-full flex-1 items-center justify-center"
                >
                    <div
                        class="flex flex-col items-center justify-center gap-12"
                    >
                        <FormBadge
                            v-for="form in forms"
                            :key="form.round_label"
                            class="w-full"
                            size="xxxl"
                            :round-label="form.round_label"
                            :form-number="form.form_number"
                            :symbol-key="form.symbol_key"
                            :form-name="form.form_name"
                        />
                    </div>
                </section>

                <section v-else class="flex flex-1 items-center justify-center">
                    <p class="text-lg text-rtds-text-secondary">
                        No draw data available.
                    </p>
                </section>
            </main>
        </ScoreboardLayout>
    </div>
</template>
