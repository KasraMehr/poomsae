<script setup>
import { computed, ref, watch } from 'vue';
import { useDisplayUpdates } from '../../Shared/Composables/useDisplayUpdates';
import Standby from '../Scoreboard/Standby.vue';
import LiveBoard from '../Scoreboard/LiveBoard.vue';
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

// All RTDS phase durations are milliseconds. Live/scoring remain backend-driven.
const phaseDurations = {
    result: 10_000,
    winner: 10_000,
    ranking: 10_000,
    standbyBeforeCall: 4_000,
};

const resolvePresentation = ({ publishedAge, currentStatus, hasWinner, hasRanking, publishedAt, clockOffset, winnerConfirmedAt, now }) => {
    if (publishedAge === null) return null;
    if (publishedAge < phaseDurations.result || currentStatus === 'decision') return 'result';

    const winnerStart = Math.max(
        Date.parse(publishedAt) - clockOffset + phaseDurations.result,
        winnerConfirmedAt ?? 0,
    );
    if (hasWinner && now < winnerStart + phaseDurations.winner) return 'winner';

    const rankingStart = hasWinner
        ? winnerStart + phaseDurations.winner
        : Date.parse(publishedAt) - clockOffset + phaseDurations.result;
    if (hasRanking && now < rankingStart + phaseDurations.ranking) return 'ranking';

    return null;
};

const resolvePhase = ({ currentStatus, presentationPhase, hasReadyPerformance, now, queueSince, presentationEnd, winnerEnd }) => {
    if (currentStatus === 'running') return 'live';
    if (currentStatus === 'scoring') return 'scoring';
    if (presentationPhase) return presentationPhase;
    if (!hasReadyPerformance) return 'standby';

    const waitingAge = now - Math.max(queueSince, presentationEnd, winnerEnd);
    return waitingAge < phaseDurations.standbyBeforeCall ? 'standby' : 'call';
};

const sequence = computed(() => [
    { phase: 'result', duration: phaseDurations.result },
    ...(hasWinner.value ? [{ phase: 'winner', duration: phaseDurations.winner }] : []),
    ...(hasRanking.value ? [{ phase: 'ranking', duration: phaseDurations.ranking }] : []),
]);
const sequenceDuration = computed(() => sequence.value.reduce((total, step) => total + step.duration, 0));
const publishedAge = computed(() => latest.value
    ? Math.max(0, now.value + clockOffset.value - Date.parse(latest.value.performance.result_published_at)) : null);
const presentation = computed(() => resolvePresentation({
    publishedAge: publishedAge.value,
    currentStatus: current.value?.status,
    hasWinner: hasWinner.value,
    hasRanking: hasRanking.value,
    publishedAt: latest.value?.performance.result_published_at,
    clockOffset: clockOffset.value,
    winnerConfirmedAt: winnerConfirmedAt.value,
    now: now.value,
}));
const winnerEnd = computed(() => hasWinner.value && winnerConfirmedAt.value
    ? winnerConfirmedAt.value + phaseDurations.winner + (hasRanking.value ? phaseDurations.ranking : 0)
    : 0);
const presentationEnd = computed(() => latest.value
    ? Date.parse(latest.value.performance.result_published_at) - clockOffset.value + sequenceDuration.value
    : 0);
const phase = computed(() => resolvePhase({
    currentStatus: current.value?.status,
    presentationPhase: presentation.value,
    hasReadyPerformance: Boolean(firstReady.value),
    now: now.value,
    queueSince: queueSince.value,
    presentationEnd: presentationEnd.value,
    winnerEnd: winnerEnd.value,
}));
const screens = { standby: Standby, call: LiveBoard, live: LiveBoard, scoring: Scoring, result: Result, winner: Winner, ranking: Ranking };
const screen = computed(() => screens[phase.value]);

// Each RTDS URL shows only its own court. A result on another court cannot
// replace this court's result or winner screen.
const screenDisplay = computed(() => {
    const focus = ['result', 'winner'].includes(phase.value) ? latest.value : null;
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
        :upcoming="upcoming"
        :focus-bout-id="latest?.bout.id"
        :focus-form-number="latest?.performance.form_number"
    />
</template>
