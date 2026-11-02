<script setup>
import ScoreboardHeader from "../Components/scoreboard/ScoreboardHeader.vue";
import AthleteInfo from "../Components/scoreboard/AthleteInfo.vue";
import NationalityBadge from "../Components/scoreboard/NationalityBadge.vue";
import FormBadge from "../Components/scoreboard/FormBadge.vue";
import SymbolContainer from "../Components/scoreboard/SymbolContainer.vue";
import Timer from "../Components/scoreboard/Timer.vue";
import ScoreCard from "../Components/scoreboard/ScoreCard.vue";
import ScoreItem from "../Components/scoreboard/ScoreItem.vue";
import SingleScoringTable from "../Components/scoreboard/tables/SingleScoringTable.vue";
import FreestyleScoringTable from "../Components/scoreboard/tables/FreestyleScoringTable.vue";
import DoubleScoringTable from "../Components/scoreboard/tables/DoubleScoringTable.vue";
import RankingTable from "../Components/scoreboard/tables/RankingTable.vue";
import RankingRow from "../Components/scoreboard/tables/RankingRow.vue";
import TopFourTable from "../Components/scoreboard/tables/TopFourTable.vue";
import TopFourRow from "../Components/scoreboard/tables/TopFourRow.vue";
import DrawPage from "../Pages/Scoreboard/Draw.vue";
import StandbyPage from "../Pages/Scoreboard/Standby.vue";
import LiveBoardPage from "../Pages/Scoreboard/LiveBoard.vue";
import ScoringPage from "../Pages/Scoreboard/Scoring.vue";
import ResultPage from "../Pages/Scoreboard/Result.vue";
import RankingPage from "../Pages/Scoreboard/Ranking.vue";
import TopFourPage from "../Pages/Scoreboard/TopFour.vue";
import WinnerPage from "../Pages/Scoreboard/Winner.vue";
import { computed, ref } from "vue";
import {
    headerCases,
    athleteCases,
    nationalityCases,
    formBadgeCases,
    symbolCases,
    medalCases,
    formSymbolStrip,
    timerCases,
    scoreCardCases,
    singleFive,
    singleFiveCompact,
    singleSeven,
    singleEmpty,
    freestyleFive,
    freestyleFiveCompact,
    freestyleSeven,
    doubleFiveChung,
    doubleFiveHong,
    doubleFiveCompact,
    doubleSevenChung,
    doubleSevenHong,
    placementsRoundRobin,
    placementsKnockout,
    extraRankingRows,
    topFourRows,
    extraTopFourRows,
    contractFields,
    drawSingle,
    drawDouble,
} from "./data.js";

/**
 * جفت رو‌به‌رو برای نمایش صفحهٔ Result: آبی سمت چپ، قرمز سمت راست (قرمز برنده است).
 * مقادیر از همان دادهٔ صفحهٔ Result گرفته شده‌اند تا با تصویر طرح مطابق باشد.
 */
const facingPair = [
    {
        card: { side: "chung", winner: false, size: "lg" },
        items: [
            { variant: "sub", label: "Accuracy", value: 3.46, max: 4 },
            { variant: "sub", label: "Presentation", value: 5.0, max: 6 },
            { variant: "total", label: "Total Score", value: 8.46, max: 10 },
        ],
    },
    {
        card: { side: "hong", winner: true, size: "lg" },
        items: [
            { variant: "sub", label: "Accuracy", value: 3.52, max: 4 },
            { variant: "sub", label: "Presentation", value: 5.2, max: 6 },
            { variant: "total", label: "Total Score", value: 8.72, max: 10 },
        ],
    },
];

const selectedPage = ref("live-board");

const pageTabs = [
    {
        id: "draw-single",
        label: "Draw · Single",
        component: DrawPage,
        draw: drawSingle,
    },
    {
        id: "draw-double",
        label: "Draw · Double",
        component: DrawPage,
        draw: drawDouble,
    },
    { id: "standby", label: "Standby · Previous / Current / Next", component: StandbyPage },
    { id: "live-board", label: "LiveBoard", component: LiveBoardPage },
    { id: "scoring-single", label: "Scoring · Single", component: ScoringPage },
    { id: "scoring-double", label: "Scoring · Double", component: ScoringPage },
    { id: "scoring-freestyle", label: "Scoring · Freestyle 4+6", component: ScoringPage },
    { id: "scoring-freestyle-6-4", label: "Scoring · Freestyle 6+4", component: ScoringPage },
    { id: "result-one-round", label: "Result · One Round", component: ResultPage },
    { id: "result-two-rounds", label: "Result · Two Rounds", component: ResultPage },
    { id: "ranking", label: "Ranking", component: RankingPage },
    { id: "top-four", label: "TopFour", component: TopFourPage },
    { id: "winner-one-round", label: "Winner · One Round", component: WinnerPage },
    { id: "winner-two-rounds", label: "Winner · Two Rounds", component: WinnerPage },
];

/*
 * آداپتور Lab: از fixtureهای موجود در data.js استفاده می‌کند.
 * این داده فقط برای پیش‌نمایش صفحه‌ها در Lab است و جایگزین Snapshot بک‌اند نیست.
 */
const labEntries = drawDouble.entries.map((entry, index) => {
    const scoreItems = facingPair[index]?.items ?? [];
    const valueOf = (label) =>
        scoreItems.find((item) => item.label === label)?.value ?? null;

    return {
        ...entry,
        accuracy_score: valueOf("Accuracy"),
        presentation_score: valueOf("Presentation"),
    };
});

const labTotals = Object.fromEntries(
    labEntries.map((entry, index) => [
        entry.id,
        facingPair[index]?.items?.find((item) => item.label === "Total Score")
            ?.value ?? null,
    ]),
);

const labStandings = placementsRoundRobin.map((placement) => ({
    rank: placement.rank,
    name: placement.name,
    wins: placement.score,
}));
const labTournament = {
    id: 1,
    name: drawSingle.title,
    status: "running",
    execution_duration_seconds: 90,
    courts: [{ id: 1, name: "Court 1" }],
    categories: [
        {
            id: 1,
            name: drawSingle.category,
            execution_mode: "alternating",
            judge_count: 5,
            entries: drawSingle.entries,
            standings: labStandings,
            champion_id: null,
            rounds: [
                {
                    id: 1,
                    name: "R - 1",
                    form_names: drawSingle.forms.map((form) => form.form_name),
                    bouts: [
                        {
                            id: 1,
                            status: "running",
                            court_id: 1,
                            entries: drawSingle.entries,
                            performances: drawSingle.forms.map((form, index) => ({
                                id: index + 1,
                                entry_id: drawSingle.entries[0]?.id,
                                status: index === 0 ? "running" : "pending",
                                form_number: form.form_number,
                                form_badge: form.symbol_key,
                                form_name: form.form_name,
                                started_at: null,
                                ended_at: null,
                            })),
                        },
                    ],
                },
            ],
        },
    ],
};

const labTournamentSingle = {
    ...labTournament,
    categories: labTournament.categories.map((category) => ({
        ...category,
        execution_mode: "alternating",
    })),
};

const labTournamentDouble = {
    ...labTournament,
    categories: labTournament.categories.map((category) => ({
        ...category,
        execution_mode: "simultaneous",
    })),
};

const standbyEntries = drawDouble.entries.map((entry) => ({ ...entry }));
const standbyCategory = {
    ...labTournamentDouble.categories[0],
    execution_mode: "simultaneous",
    discipline: "recognized",
};
const standbyPerformance = (entry, status, result = null) => ({
    id: `standby-${entry.id}-${status}`,
    entry_id: entry.id,
    status,
    result,
    form_number: 1,
    form_badge: 5,
    form_name: "TAEGUK 5",
    started_at: status === "running" ? null : undefined,
    ended_at: null,
    live_timing: { duration_seconds: 90 },
});
const standbyTournament = {
    ...labTournamentDouble,
    categories: [
        {
            ...standbyCategory,
            rounds: [
                {
                    id: 71,
                    name: "R - 1",
                    bouts: [
                        {
                            id: 701,
                            sequence: 1,
                            court_id: 1,
                            status: "completed",
                            entries: standbyEntries,
                            performances: standbyEntries.map((entry, index) =>
                                standbyPerformance(entry, "approved", index === 0 ? 8.61 : 8.72),
                            ),
                            winner_entry_id: standbyEntries[1].id,
                            totals: { [standbyEntries[0].id]: 8.61, [standbyEntries[1].id]: 8.72 },
                        },
                        {
                            id: 702,
                            sequence: 2,
                            court_id: 1,
                            status: "running",
                            entries: standbyEntries,
                            performances: standbyEntries.map((entry) => standbyPerformance(entry, "running")),
                        },
                        {
                            id: 703,
                            sequence: 3,
                            court_id: 1,
                            status: "pending",
                            entries: standbyEntries,
                            performances: standbyEntries.map((entry) => standbyPerformance(entry, "pending")),
                        },
                    ],
                },
            ],
        },
    ],
};

const resultFixture = (roundCount) => {
    const roundScores = [
        {
            chung: { accuracy: 3.46, presentation: 5.0, total: 8.46 },
            hong: { accuracy: 3.52, presentation: 5.2, total: 8.72 },
        },
        {
            chung: { accuracy: 3.81, presentation: 5.03, total: 8.84 },
            hong: { accuracy: 3.33, presentation: 5.52, total: 8.86 },
        },
    ];
    const rounds = roundScores.slice(0, roundCount).map((scores, index) => {
        const entries = drawDouble.entries.map((entry) => ({
            ...entry,
            accuracy_score: scores[entry.side]?.accuracy ?? null,
            presentation_score: scores[entry.side]?.presentation ?? null,
        }));
        const totals = Object.fromEntries(
            entries.map((entry) => [entry.id, scores[entry.side]?.total ?? null]),
        );

        return {
            id: index + 1,
            name: "R - " + (index + 1),
            bouts: [
                {
                    id: 200 + index,
                    status: "completed",
                    sequence: 1,
                    entries,
                    performances: entries.flatMap((entry) =>
                        drawDouble.forms.map((form) => ({
                            id: `winner-${200 + index}-${entry.id}-${form.form_number}`,
                            entry_id: entry.id,
                            form_number: form.form_number,
                            form_badge: form.symbol_key,
                            form_name: form.form_name,
                            status: "approved",
                        })),
                    ),
                    totals,
                    winner_entry_id: entries.find((entry) => entry.side === "hong")?.id,
                },
            ],
        };
    });

    return {
        ...labTournament,
        name: "RESULT",
        stage: "SEMI-FINAL",
        categories: [
            {
                id: 20,
                name: "MALE - UNDER 30",
                stage: "SEMI-FINAL",
                execution_mode: "simultaneous",
                form_names: drawDouble.forms.map((form) => form.form_name),
                entries: drawDouble.entries,
                rounds,
            },
        ],
    };
};

const resultFixtures = {
    "result-one-round": resultFixture(1),
    "result-two-rounds": resultFixture(2),
};

const winnerFixture = (roundCount) => {
    const tournament = resultFixture(roundCount);
    const finalBout = tournament.categories[0].rounds.at(-1).bouts[0];

    return {
        ...tournament,
        current_result: {
            category_id: tournament.categories[0].id,
            status: "completed",
            round_count: roundCount,
            bout_ids: tournament.categories[0].rounds.map(
                (round) => round.bouts[0].id,
            ),
            winner_entry_id: finalBout.winner_entry_id,
            winner_total: roundCount === 1 ? 8.72 : 9.18,
            total_max: 10,
        },
    };
};

const winnerFixtures = {
    "winner-one-round": winnerFixture(1),
    "winner-two-rounds": winnerFixture(2),
};

const rankingFixture = {
    ...labTournament,
    name: "RANKING",
    stage: "SEMI-FINAL",
    categories: [
        {
            id: 30,
            name: "MALE - UNDER 30",
            stage: "SEMI-FINAL",
            format: "round_robin",
            placements: placementsRoundRobin.slice(0, 5),
        },
    ],
};

const topFourFixture = {
    ...labTournament,
    name: "TOP FOUR",
    stage: "SEMI-FINAL",
    categories: [
        {
            id: 40,
            name: "MALE - UNDER 30",
            stage: "SEMI-FINAL",
            placements: topFourRows.map((row) => ({
                rank: row.rank,
                entry_id: row.rank + 300,
                name: row.name,
                score: row.score,
                country: row.country,
                country_code: row.countryCode,
                flag_url: row.flagUrl,
            })),
        },
    ],
};

const scoringFixture = (mode) => {
    const isDouble = mode === "double";
    const isFreestyle = mode.startsWith("freestyle");
    const isPenaltyMode = mode === "freestyle-6-4";
    const entries = isDouble ? drawDouble.entries : drawSingle.entries.slice(0, 1);
    const judges = isFreestyle
        ? freestyleFive.judges.map((judge, index) => ({
              seat: index + 1,
              label: judge.label,
              accuracy: judge.accuracy,
              accuracy_components: isPenaltyMode ? [1.5, 1.4, 1.2] : judge.accuracyParts,
              presentation: judge.presentation,
              presentation_components: isPenaltyMode ? [] : judge.presentationParts,
              presentation_penalties: isPenaltyMode ? [0.2, 0.3, 0.1, 0.2] : [],
          }))
        : singleFive.judges.map((judge, index) => ({
              seat: index + 1,
              label: judge.label,
              accuracy: judge.accuracy,
              presentation: judge.presentation,
              presentation_components: [judge.eoe, judge.sp, judge.rp],
          }));
    const performances = entries.map((entry, index) => ({
        id: "scoring-performance-" + index,
        entry_id: entry.id,
        status: "scoring",
        form_number: 1,
        form_badge: 1,
        form_name: "TAEGUK 5",
        detailed_scores: {
            discipline: isFreestyle ? "freestyle" : "recognized",
            method: isPenaltyMode ? "accuracy_6_presentation_4" : "accuracy_4_presentation_6",
            judges,
            accuracy: "3.46",
            accuracy_max: "4.0",
            presentation: "5.00",
            presentation_max: "6.0",
            total: "8.46",
            total_max: "10.0",
        },
    }));

    return {
        ...labTournament,
        categories: [{
            ...labTournament.categories[0],
            name: "MALE - UNDER 30",
            discipline: isFreestyle ? "freestyle" : "recognized",
            format: "knockout",
            execution_mode: isDouble ? "simultaneous" : "alternating",
            judge_count: 5,
            rules: { accuracy_max: 400, presentation_max: 600 },
            rounds: [{
                ...labTournament.categories[0].rounds[0],
                name: "SEMI-FINAL",
                bouts: [{
                    ...labTournament.categories[0].rounds[0].bouts[0],
                    sequence: 1,
                    entries,
                    performances,
                }],
            }],
        }],
    };
};

const scoringFixtures = {
    "scoring-single": scoringFixture("single"),
    "scoring-double": scoringFixture("double"),
    "scoring-freestyle": scoringFixture("freestyle"),
    "scoring-freestyle-6-4": scoringFixture("freestyle-6-4"),
};

const activePage = computed(
    () =>
        pageTabs.find((page) => page.id === selectedPage.value) ?? pageTabs[0],
);

const activeTournament = computed(() => {
    if (winnerFixtures[selectedPage.value]) {
        return winnerFixtures[selectedPage.value];
    }

    if (selectedPage.value === "ranking") {
        return rankingFixture;
    }

    if (selectedPage.value === "top-four") {
        return topFourFixture;
    }

    if (resultFixtures[selectedPage.value]) {
        return resultFixtures[selectedPage.value];
    }

    if (scoringFixtures[selectedPage.value]) {
        return scoringFixtures[selectedPage.value];
    }

    if (selectedPage.value === "standby") {
        return standbyTournament;
    }

    if (selectedPage.value === "draw-single") {
        return labTournamentSingle;
    }

    if (selectedPage.value === "draw-double") {
        return labTournamentDouble;
    }

    return labTournament;
});

// Match the Inertia page prop: every page receives one shared `display` snapshot.
// The lab derives the broadcast court pointer from the same tournament fixture.
const activeDisplay = computed(() => {
    const tournament = activeTournament.value;
    const bouts = (tournament.categories ?? []).flatMap((category) =>
        (category.rounds ?? []).flatMap((round) =>
            (round.bouts ?? []).map((bout) => ({ ...bout, category, round })),
        ),
    );
    return {
        tournament,
        courts: (tournament.courts ?? []).map((court) => {
            const courtBouts = bouts.filter(
                (bout) => String(bout.court_id) === String(court.id),
            );
            const currentIndex = courtBouts.findIndex((bout) =>
                ["running", "scoring"].includes(bout.status) ||
                bout.performances?.some((performance) =>
                    ["running", "scoring"].includes(performance.status),
                ),
            );
            const currentBout = currentIndex < 0 ? null : courtBouts[currentIndex];
            const previousBout = courtBouts
                .slice(0, currentIndex < 0 ? undefined : currentIndex)
                .filter((bout) => bout.status === "completed")
                .at(-1);

            return {
                ...court,
                current: currentBout
                    ? {
                          bout_id: currentBout.id,
                          status: currentBout.status,
                          entries: currentBout.entries,
                          performances: currentBout.performances,
                      }
                    : null,
                previous_bout_id: previousBout?.id ?? null,
                upcoming: courtBouts
                    .slice(currentIndex < 0 ? 0 : currentIndex + 1)
                    .filter((bout) => bout.status === "pending")
                    .map((bout) => ({ bout_id: bout.id })),
            };
        }),
        revision: 1,
        generated_at: "2026-10-22T00:00:00Z",
    };
});

const sections = [
    { id: "scoreboard-header", label: "ScoreboardHeader" },
    { id: "athlete-info", label: "AthleteInfo" },
    { id: "nationality-badge", label: "NationalityBadge" },
    { id: "form-badge", label: "FormBadge" },
    { id: "symbol-container", label: "SymbolContainer / Medals" },
    { id: "timer", label: "Timer" },
    { id: "score-card", label: "ScoreCard / ScoreItem" },
    { id: "single-scoring-table", label: "SingleScoringTable" },
    { id: "freestyle-scoring-table", label: "FreestyleScoringTable" },
    { id: "double-scoring-table", label: "DoubleScoringTable" },
    { id: "ranking", label: "RankingTable / RankingRow" },
    { id: "top-four", label: "TopFourTable / TopFourRow" },
    { id: "contract", label: "فیلدهای Proposed" },
];

/** نگاشت placements[] پیشنهادی → props های RankingRow (adapter) */
const toRankingRow = (placement) => ({
    rank: placement.rank,
    name: placement.name,
    country: placement.country,
    countryCode: placement.country_code,
    flagUrl: placement.flag_url,
    roundScores: placement.round_scores ?? [],
});

const roundRobinRows = placementsRoundRobin.map(toRankingRow);
const knockoutRows = placementsKnockout.map(toRankingRow);
</script>

<template>
    <div class="fixed inset-0 h-dvh w-screen overflow-hidden bg-rtds-bg text-rtds-text">
        <label class="sr-only" for="scoreboard-lab-page">Choose display page</label>
        <select
            id="scoreboard-lab-page"
            v-model="selectedPage"
            class="fixed right-3 top-3 z-[100] max-w-[min(14rem,45vw)] appearance-none rounded-lg border border-white/20 bg-black/70 px-3 py-2 pr-8 text-xs text-white shadow-lg backdrop-blur focus:outline-none focus:ring-2 focus:ring-rtds-gold"
            aria-label="Choose display page"
        >
            <option v-for="page in pageTabs" :key="page.id" :value="page.id">
                {{ page.label }}
            </option>
        </select>
        <div class="h-full w-full overflow-hidden">
            <component
                :is="activePage.component"
                :display="activeDisplay"
                :court-id="1"
                :draw="activePage.draw"
            />
        </div>
    </div>
</template>
