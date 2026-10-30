<script setup>
import { computed, ref, watch } from 'vue';
import { useDisplayUpdates } from '../../Shared/Composables/useDisplayUpdates';
import Standby from '../Scoreboard/Standby.vue';
import LiveBoard from '../Scoreboard/LiveBoard.vue';
import Draw from '../Scoreboard/Draw.vue';
import Scoring from '../Scoreboard/Scoring.vue';
import Result from '../Scoreboard/Result.vue';
import Winner from '../Scoreboard/Winner.vue';
import Ranking from '../Scoreboard/Ranking.vue';

const props = defineProps({ display: Object, displayUrls: Object, courtId: [Number, String] });
const { display, now } = useDisplayUpdates(props.display, props.displayUrls.data);
const clockOffset = ref(Date.parse(props.display.generated_at) - Date.now());
watch(display, snapshot => clockOffset.value = Date.parse(snapshot.generated_at) - Date.now());

const court = computed(() => display.value.courts.find(item => String(item.id) === String(props.courtId)) ?? null);
const current = computed(() => court.value?.current ?? null);
const upcoming = computed(() => court.value?.upcoming ?? []);
const firstReady = computed(() => upcoming.value[0]?.ready ? upcoming.value[0] : null);
const queueSince = ref(Date.now());
watch(() => firstReady.value?.id, () => queueSince.value = Date.now());

const courtBouts = computed(() => (display.value.tournament.categories ?? []).flatMap(category =>
    (category.rounds ?? []).flatMap(round => (round.bouts ?? [])
        .filter(bout => String(bout.court_id) === String(props.courtId))
        .map(bout => ({ category, round, bout })))));
const latest = computed(() => courtBouts.value.flatMap(item => (item.bout.performances ?? [])
    .filter(performance => performance.result !== null && performance.result_published_at)
    .map(performance => ({ ...item, performance })))
    .sort((left, right) => Date.parse(right.performance.result_published_at) - Date.parse(left.performance.result_published_at))[0] ?? null);
const hasWinner = computed(() => latest.value?.bout.status === 'completed'
    && latest.value.bout.winner_entry_id != null && latest.value.bout.entries?.length === 2);
const winnerConfirmedAt = ref(null);
watch(() => hasWinner.value ? `${latest.value?.bout.id}:${latest.value?.bout.winner_entry_id}` : null,
    (value, previous) => {
        if (value && value !== previous) winnerConfirmedAt.value = Date.now();
    });
const hasRanking = computed(() => {
    const category = latest.value?.category;
    return Boolean(category && (category.placements?.length || category.standings?.some(row => row.rank != null)));
});
const hasRoundResult = computed(() => latest.value?.bout.status === 'completed'
    || current.value?.status === 'decision');

// All RTDS phase durations are milliseconds. Queue timing starts when this display first sees a ready item.
// TODO: If refreshes or multiple displays drift, replace this local clock with a shared server ready_at timestamp.
const phaseDurations = {
    finishDelay: 10_000,
    scoring: 30_000,
    result: 30_000,
    winner: 30_000,
    ranking: 20_000,
    standby: 20_000,
    draw: 20_000,
};

const resolvePresentation = ({ publishedAge, hasWinner, hasRanking, publishedAt, clockOffset, winnerConfirmedAt, now }) => {
    if (publishedAge === null) return null;
    const presentationAge = publishedAge - phaseDurations.scoring;
    if (presentationAge < phaseDurations.result) return 'result';

    const winnerStart = Math.max(
        Date.parse(publishedAt) - clockOffset + phaseDurations.scoring + phaseDurations.result,
        winnerConfirmedAt ?? 0,
    );
    if (hasWinner && now < winnerStart + phaseDurations.winner) return 'winner';

    const rankingStart = hasWinner
        ? winnerStart + phaseDurations.winner
        : Date.parse(publishedAt) - clockOffset + phaseDurations.scoring + phaseDurations.result;
    if (hasRanking && now < rankingStart + phaseDurations.ranking) return 'ranking';

    return null;
};

const resolvePhase = ({ currentStatus, finishDelayActive, publishedScoring, presentationPhase, hasReadyPerformance, hasDrawData, now, queueSince, presentationEnd, winnerEnd }) => {
    if (currentStatus === 'running') return 'live';
    if (finishDelayActive) return 'live';
    if (currentStatus === 'scoring') return 'scoring';
    if (publishedScoring) return 'scoring';
    if (presentationPhase) return presentationPhase;
    if (!hasReadyPerformance) return 'standby';

    const waitingAge = now - Math.max(queueSince, presentationEnd, winnerEnd);
    if (waitingAge < phaseDurations.standby) return 'standby';
    if (hasDrawData && waitingAge < phaseDurations.standby + phaseDurations.draw) return 'draw';

    return 'call';
};

const sequence = computed(() => [
    ...(hasRoundResult.value ? [{ phase: 'result', duration: phaseDurations.result }] : []),
    ...(hasWinner.value ? [{ phase: 'winner', duration: phaseDurations.winner }] : []),
    ...(hasRoundResult.value && hasRanking.value ? [{ phase: 'ranking', duration: phaseDurations.ranking }] : []),
]);
const sequenceDuration = computed(() => sequence.value.reduce((total, step) => total + step.duration, 0));
const publishedAge = computed(() => latest.value
    ? Math.max(0, now.value + clockOffset.value - Date.parse(latest.value.performance.result_published_at)) : null);
const finishTimestamp = computed(() => {
    const timestamps = (current.value?.performances ?? [])
        .filter(performance => performance.ended_at)
        .map(performance => Date.parse(performance.ended_at))
        .filter(Number.isFinite);

    return timestamps.length ? Math.min(...timestamps) : null;
});
const finishDelayActive = computed(() => current.value?.status === 'scoring'
    && finishTimestamp.value !== null
    && now.value + clockOffset.value - finishTimestamp.value < phaseDurations.finishDelay);
const publishedScoring = computed(() => current.value?.status !== 'running'
    && current.value?.status !== 'scoring'
    && publishedAge.value !== null
    && publishedAge.value < phaseDurations.scoring);
const presentation = computed(() => resolvePresentation({
    publishedAge: publishedAge.value,
    hasWinner: hasWinner.value,
    hasRanking: hasRoundResult.value && hasRanking.value,
    publishedAt: latest.value?.performance.result_published_at,
    clockOffset: clockOffset.value,
    winnerConfirmedAt: winnerConfirmedAt.value,
    now: now.value,
}));
const winnerEnd = computed(() => hasWinner.value && winnerConfirmedAt.value
    ? winnerConfirmedAt.value + phaseDurations.winner + (hasRanking.value ? phaseDurations.ranking : 0)
    : 0);
const presentationEnd = computed(() => latest.value
    ? Date.parse(latest.value.performance.result_published_at) - clockOffset.value + phaseDurations.scoring + sequenceDuration.value
    : 0);
const drawTarget = computed(() => firstReady.value
    ? courtBouts.value.find(item => String(item.bout.id) === String(firstReady.value.bout_id)) ?? null
    : null);
const drawData = computed(() => {
    if (!drawTarget.value) return null;

    const { category, round, bout } = drawTarget.value;
    const performancesByNumber = new Map();
    for (const performance of bout.performances ?? []) {
        if (!performancesByNumber.has(performance.form_number)) {
            performancesByNumber.set(performance.form_number, performance);
        }
    }

    const forms = (round.form_names ?? []).length
        ? round.form_names.map((formName, index) => {
            const formNumber = index + 1;
            const performance = performancesByNumber.get(formNumber);

            return {
                round_label: `R - ${formNumber}`,
                form_number: formNumber,
                symbol_key: performance?.form_badge ?? performance?.form_number ?? null,
                form_name: performance?.form_name ?? formName ?? '',
            };
        })
        : [...performancesByNumber.values()]
            .sort((left, right) => left.form_number - right.form_number)
            .map(performance => ({
                round_label: `R - ${performance.form_number}`,
                form_number: performance.form_number,
                symbol_key: performance.form_badge ?? performance.form_number ?? null,
                form_name: performance.form_name ?? '',
            }));

    return {
        mode: bout.entries?.length > 1 ? 'double' : 'single',
        stage: round.stage_label ?? round.name ?? '',
        title: display.value.tournament.name ?? '',
        category: category.name ?? '',
        entries: bout.entries ?? [],
        forms,
    };
});
const phase = computed(() => resolvePhase({
    currentStatus: current.value?.status,
    finishDelayActive: finishDelayActive.value,
    publishedScoring: publishedScoring.value,
    presentationPhase: hasRoundResult.value ? presentation.value : null,
    hasReadyPerformance: Boolean(firstReady.value),
    hasDrawData: Boolean(drawData.value?.forms.length),
    now: now.value,
    queueSince: queueSince.value,
    presentationEnd: presentationEnd.value,
    winnerEnd: winnerEnd.value,
}));
const screens = { standby: Standby, draw: Draw, call: LiveBoard, live: LiveBoard, scoring: Scoring, result: Result, winner: Winner, ranking: Ranking };
const screen = computed(() => screens[phase.value]);

// Each RTDS URL shows only its own court. A result on another court cannot
// replace this court's result or winner screen.
const screenDisplay = computed(() => {
    const focus = ['result', 'winner'].includes(phase.value) || (phase.value === 'scoring' && publishedScoring.value)
        ? latest.value : null;
    const categories = (display.value.tournament.categories ?? []).flatMap(category => {
        if (phase.value === 'ranking' && String(category.id) !== String(latest.value?.category.id)) return [];
        const rounds = (category.rounds ?? []).flatMap(round => {
            const bouts = (round.bouts ?? []).filter(bout => String(bout.court_id) === String(props.courtId)
                && (!focus || String(bout.id) === String(focus.bout.id)))
                .map(bout => phase.value === 'scoring' ? {
                    ...bout,
                    performances: (bout.performances ?? []).map(performance => performance.result != null
                        ? performance : { ...performance, preview_result: null, detailed_scores: null }),
                } : bout);
            return bouts.length ? [{ ...round, bouts }] : [];
        });
        return rounds.length || (phase.value === 'ranking' && String(category.id) === String(latest.value?.category.id))
            ? [{ ...category, rounds }] : [];
    });
    return {
        ...display.value,
        courts: court.value ? [court.value] : [],
        tournament: { ...display.value.tournament, categories, courts: court.value ? [court.value] : [], current_result: null },
    };
});
</script>

<template>
    <component
        :is="screen"
        :display="screenDisplay"
        :court-id="courtId"
        :phase="phase"
        :focus-item="firstReady"
        :draw="phase === 'draw' ? drawData : null"
        :upcoming="upcoming"
        :focus-bout-id="(['result', 'winner'].includes(phase) || (phase === 'scoring' && publishedScoring)) ? latest?.bout.id : null"
        :focus-form-number="phase === 'scoring' && publishedScoring ? latest?.performance.form_number : null"
        :focus-performance-id="phase === 'scoring' && publishedScoring ? latest?.performance.id : null"
    />
</template>
