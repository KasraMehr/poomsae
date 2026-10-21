<script setup>
import { computed } from "vue";
import ScoreboardLayout from "../../Shared/Layouts/ScoreboardLayout.vue";
import ScoreboardHeader from "../../Components/scoreboard/ScoreboardHeader.vue";
import AthleteInfo from "../../Components/scoreboard/AthleteInfo.vue";
import FormBadge from "../../Components/scoreboard/FormBadge.vue";
import Timer from "../../Components/scoreboard/Timer.vue";
import { Head } from "@inertiajs/vue3";

const props = defineProps({
    draw: {
        type: Object,
        default: null,
    },
    tournament: {
        type: Object,
        default: null,
    },
    courtId: {
        type: [String, Number],
        default: null,
    },
});

const statusLabels = {
    draft: "DRAFT",
    ready: "READY",
    running: "IN PROGRESS",
    completed: "COMPLETED",
};

const stage = computed(() => statusLabels[props.tournament?.status] ?? "");

const finished = computed(() => props.tournament?.status === "completed");

const currentExecution = computed(() => {
    for (const category of props.tournament?.categories ?? []) {
        for (const round of category.rounds ?? []) {
            for (const bout of round.bouts ?? []) {
                if (bout.status !== "running") continue;

                if (
                    props.courtId != null &&
                    bout.court_id != null &&
                    String(bout.court_id) !== String(props.courtId)
                ) {
                    continue;
                }

                const performance = bout.performances?.find((item) =>
                    ["running", "scoring"].includes(item.status),
                );

                if (!performance) continue;

                return {
                    category,
                    round,
                    bout,
                    performance,
                    entries: bout.entries ?? [],
                    state:
                        performance.status === "running"
                            ? "running"
                            : "scoring",
                };
            }
        }
    }

    return null;
});

const mode = computed(() =>
    currentExecution.value?.category?.execution_mode === "simultaneous"
        ? "double"
        : "single",
);

const visibleEntries = computed(() => {
    const execution = currentExecution.value;

    if (!execution) return [];

    if (mode.value === "double") {
        return execution.entries.slice(0, 2);
    }

    const performanceEntryId = execution.performance.entry_id;

    const activeEntry = execution.entries.find(
        (entry) => String(entry.id) === String(performanceEntryId),
    );

    return activeEntry ? [activeEntry] : execution.entries.slice(0, 1);
});

const roundLabel = computed(() => {
    const name = currentExecution.value?.round?.name ?? "";

    if (!name) return "";

    return /^round/i.test(name) ? name : `${name}`;
});

const categoryName = computed(
    () => currentExecution.value?.category?.name ?? "",
);

const DEFAULT_EXECUTION_SECONDS = 90;

const durationSeconds = computed(
    () =>
        props.tournament?.execution_duration_seconds ??
        DEFAULT_EXECUTION_SECONDS,
);

const courts = computed(() => props.tournament?.courts ?? []);
</script>

<template>
    <!-- <Head title="standby"/> -->
    <ScoreboardLayout
        :stage="stage"
        :center="roundLabel"
        :category="categoryName"
    >
        <template #header>
            <ScoreboardHeader
                :stage="stage"
                :event-title="roundLabel"
                :category="categoryName"
            />
        </template>

        <main class="flex min-h-0 flex-1 flex-col px-12 pb-6">
            <template v-if="currentExecution">
                <!-- ATHLETES -->
                <section
                    class="grid w-full shrink-0 items-start gap-10 pt-10"
                    :class="mode === 'single' ? 'grid-cols-1' : 'grid-cols-2'"
                >
                    <AthleteInfo
                        v-for="entry in visibleEntries"
                        :key="entry.id"
                        :layout="
                            mode === 'single' ? 'horizontal' : 'vertical-under'
                        "
                        :class="
                            mode === 'single' ? 'justify-self-center' : 'w-full'
                        "
                        :size="mode === 'single' ? 'xxl' : 'xl'"
                        :number="entry.number"
                        :name="entry.name"
                        :country="entry.country"
                        :country-code="entry.country_code"
                        :flag-url="entry.flag_url"
                        :side="entry.side"
                    />
                </section>

                <!-- FORM + TIMER -->
                <section
                    class="grid min-h-0 flex-1 grid-cols-2 items-center gap-10"
                >
                    <!-- FORM: LEFT -->
                    <div class="flex min-w-0 items-center justify-start">
                        <FormBadge
                            size="xxxl"
                            :form-number="
                                currentExecution.performance.form_name
                                    ?.form_number ??
                                currentExecution.performance.form_number
                            "
                            :symbol-key="
                                currentExecution.performance.form_name
                                    ?.symbol_key ??
                                currentExecution.performance.symbol_key
                            "
                            :form-name="
                                currentExecution.performance.form_name
                                    ?.form_name ??
                                currentExecution.performance.form_name ??
                                ''
                            "
                        />
                    </div>

                    <!-- TIMER: RIGHT -->
                    <div class="flex items-center justify-center">
                        <Timer
                            size="xl"
                            :duration-seconds="durationSeconds"
                            :started-at="
                                currentExecution.performance.started_at
                            "
                            :ended-at="currentExecution.performance.ended_at"
                        />
                    </div>
                </section>
            </template>

            <!-- NO CURRENT EXECUTION -->
            <section
                v-else
                class="flex flex-1 flex-col items-center justify-center gap-6 text-center"
            >
                <p class="text-4xl font-semibold uppercase">
                    {{
                        finished
                            ? "TOURNAMENT COMPLETED"
                            : "NO ACTIVE PERFORMANCE"
                    }}
                </p>

                <p class="text-rtds-text-secondary">
                    Results are published after operator approval.
                </p>

                <div
                    v-if="courts.length"
                    class="mt-4 flex flex-wrap justify-center gap-3"
                >
                    <span
                        v-for="court in courts"
                        :key="court.id"
                        class="rounded-lg border border-rtds-border bg-rtds-bg-card px-4 py-2 text-sm text-rtds-text-secondary"
                    >
                        {{ court.name }}
                    </span>
                </div>
            </section>
        </main>
    </ScoreboardLayout>
</template>
