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
    display: {
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
    phase: { type: String, default: null },
    focusItem: { type: Object, default: null },
    upcoming: { type: Array, default: () => [] },
});

// Accept the production display envelope and the standalone lab snapshot.
const tournament = computed(
    () => props.tournament ?? props.display?.tournament ?? null,
);

const statusLabels = {
    draft: "DRAFT",
    ready: "READY",
    running: "IN PROGRESS",
    completed: "COMPLETED",
};

const stage = computed(() => props.phase === 'call' ? 'CALL TO COURT' : props.phase === 'waiting' ? 'WAITING LIST' : statusLabels[tournament.value?.status] ?? "");

const finished = computed(() => tournament.value?.status === "completed");

const currentExecution = computed(() => {
    if (props.phase === 'call' && props.focusItem) {
        for (const category of tournament.value?.categories ?? []) {
            for (const round of category.rounds ?? []) {
                const bout = (round.bouts ?? []).find(item => String(item.id) === String(props.focusItem.bout_id));
                if (!bout) continue;
                const performance = bout.performances?.find(item => String(item.id) === String(props.focusItem.id));
                if (performance) return { category, round, bout, performance, entries: bout.entries ?? [], state: 'call' };
            }
        }
    }
    if (props.phase === 'waiting') return null;
    for (const category of tournament.value?.categories ?? []) {
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

const durationSeconds = computed(() => {
    const performance = currentExecution.value?.performance;

    return (
        performance?.live_timing?.duration_seconds ??
        tournament.value?.execution_duration_seconds ??
        performance?.timing?.maximum_duration_seconds ??
        null
    );
});

const formSymbolKey = computed(() => {
    const execution = currentExecution.value;

    if (!execution || execution.category.discipline === "freestyle") {
        return null;
    }

    return execution.performance.form_badge ?? null;
});

const courts = computed(() => tournament.value?.courts ?? []);
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
                :event-title="tournament?.name ?? ''"
                :category="categoryName"
                :round-label="roundLabel"
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
                        :name="entry.name"
                        :country="entry.country"
                        :country-code="entry.country_code"
                        :flag-url="entry.flag_url"
                        :side="entry.side"
                    />
                </section>

                <!-- FORM + TIMER -->
                <section
                    class="grid min-h-0 flex-1 items-center gap-6 lg:gap-10"
                    :class="phase === 'call' ? 'grid-cols-1 lg:grid-cols-2' : 'grid-cols-2'"
                >
                    <!-- FORM: LEFT -->
                    <div class="flex min-w-0 items-center justify-start">
                        <FormBadge
                            :size="phase === 'call' ? 'xl' : 'xxxl'"
                            :symbol-key="formSymbolKey"
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
                        <span v-if="phase === 'call'" class="text-6xl font-semibold uppercase text-rtds-gold-bright">READY</span>
                        <Timer
                            v-else-if="durationSeconds != null"
                            size="xl"
                            :duration-seconds="durationSeconds"
                            :started-at="
                                currentExecution.performance.started_at
                            "
                            :ended-at="currentExecution.performance.ended_at"
                            :server-now="currentExecution.performance.server_now"
                        />
                        <span
                            v-else
                            class="text-center text-rtds-text-secondary"
                        >
                            Timer data unavailable
                        </span>
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
                            : phase === 'waiting' ? 'WAITING LIST' : "NO ACTIVE PERFORMANCE"
                    }}
                </p>

                <div v-if="phase === 'waiting' && upcoming.length" class="flex w-full max-w-5xl flex-col gap-3">
                    <div v-for="(item, index) in upcoming.slice(0, 4)" :key="item.id" class="flex items-center gap-6 rounded-xl border border-rtds-border bg-rtds-bg-card px-6 py-4 text-left">
                        <span class="text-3xl font-semibold text-rtds-gold-bright">{{ String(index + 1).padStart(2, '0') }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-2xl font-semibold">{{ item.entries.map(entry => entry.name).join(' / ') }}</p>
                            <p class="text-rtds-text-secondary">{{ item.category_name }} · {{ item.form_name }}</p>
                        </div>
                        <span class="text-rtds-text-secondary">MATCH {{ item.bout_sequence }}</span>
                    </div>
                </div>
                <p v-else class="text-rtds-text-secondary">{{ phase === 'waiting' ? 'AWAITING NEXT MATCH' : 'Results are published after operator approval.' }}</p>

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
