<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useDisplayUpdates } from '../../Shared/Composables/useDisplayUpdates';
import Standby from '../Scoreboard/Standby.vue';
import LiveBoard from '../Scoreboard/LiveBoard.vue';
import Scoring from '../Scoreboard/Scoring.vue';
import Result from '../Scoreboard/Result.vue';
import Winner from '../Scoreboard/Winner.vue';
import Ranking from '../Scoreboard/Ranking.vue';

const props = defineProps({ display: Object, displayUrls: Object, courtId: [Number, String], publicScreen: { type: Boolean, default: false } });
const viewportElement = ref(null);
const viewportSize = ref({ width: 0, height: 0 });
let viewportObserver;
const measureViewport = () => {
    const bounds = viewportElement.value?.getBoundingClientRect();
    if (bounds) viewportSize.value = { width: bounds.width, height: bounds.height };
};
onMounted(() => {
    measureViewport();
    window.addEventListener('resize', measureViewport);
    if (typeof ResizeObserver !== 'undefined') {
        viewportObserver = new ResizeObserver(([entry]) => {
            viewportSize.value = { width: entry.contentRect.width, height: entry.contentRect.height };
        });
        viewportObserver.observe(viewportElement.value);
    }
});
onBeforeUnmount(() => {
    window.removeEventListener('resize', measureViewport);
    viewportObserver?.disconnect();
});
const stageScale = computed(() => Math.min(viewportSize.value.width / 1920, viewportSize.value.height / 1080));
const { display, now } = useDisplayUpdates(props.display, props.displayUrls.data, !props.publicScreen, props.publicScreen ? 2000 : 500);
const clockOffset = ref(Date.parse(props.display.generated_at) - Date.now());
watch(display, snapshot => clockOffset.value = Date.parse(snapshot.generated_at) - Date.now());

const court = computed(() => display.value.courts.find(item => String(item.id) === String(props.courtId)) ?? null);
const courtUrl = id => `${props.displayUrls.rtds}?court=${encodeURIComponent(id)}`;
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
const sequence = computed(() => [
    { phase: 'result', duration: 6000 },
    ...(hasWinner.value ? [{ phase: 'winner', duration: 7000 }] : []),
    ...(hasRanking.value ? [{ phase: 'ranking', duration: 8000 }] : []),
]);
const sequenceDuration = computed(() => sequence.value.reduce((total, step) => total + step.duration, 0));
const publishedAge = computed(() => latest.value
    ? Math.max(0, now.value + clockOffset.value - Date.parse(latest.value.performance.result_published_at)) : null);
const presentation = computed(() => {
    if (publishedAge.value === null) return null;
    if (publishedAge.value < 6000 || current.value?.status === 'decision') return 'result';
    const winnerStart = Math.max(
        Date.parse(latest.value.performance.result_published_at) - clockOffset.value + 6000,
        winnerConfirmedAt.value ?? 0,
    );
    if (hasWinner.value && now.value < winnerStart + 7000) return 'winner';
    if (hasRanking.value && now.value < (hasWinner.value ? winnerStart + 15000 :
        Date.parse(latest.value.performance.result_published_at) - clockOffset.value + 14000)) return 'ranking';
    return null;
});
const phase = computed(() => {
    if (current.value?.status === 'running') return 'live';
    if (current.value?.status === 'scoring') return 'scoring';
    if (presentation.value) return presentation.value;
    if (!firstReady.value) return 'waiting';
    const winnerEnd = hasWinner.value && winnerConfirmedAt.value
        ? winnerConfirmedAt.value + (hasRanking.value ? 15000 : 7000)
        : 0;
    const presentationEnd = latest.value
        ? Date.parse(latest.value.performance.result_published_at) - clockOffset.value + sequenceDuration.value
        : 0;
    const waitingAge = now.value - Math.max(queueSince.value, presentationEnd, winnerEnd);
    return waitingAge < 4000 ? 'waiting' : 'call';
});
const screens = { waiting: Standby, call: Standby, live: LiveBoard, scoring: Scoring, result: Result, winner: Winner, ranking: Ranking };
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
    <div ref="viewportElement" class="rtds-viewport">
        <div class="rtds-stage" :style="{ transform: `translate(-50%, -50%) scale(${stageScale})` }">
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
        </div>
    </div>
    <details v-if="display.courts.length > 1" class="court-switcher">
        <summary>COURT: {{ court?.name ?? 'SELECT' }}</summary>
        <nav aria-label="Select RTDS court">
            <a
                v-for="item in display.courts"
                :key="item.id"
                :href="courtUrl(item.id)"
                :aria-current="String(item.id) === String(courtId) ? 'page' : undefined"
            >{{ item.name }}</a>
        </nav>
    </details>
</template>

<style scoped>
.rtds-viewport {
    position: relative;
    width: 100%;
    height: 100vh;
    height: 100dvh;
    overflow: hidden;
    background: #081422;
}

.rtds-stage {
    position: absolute;
    top: 50%;
    left: 50%;
    width: 1920px;
    height: 1080px;
    transform-origin: center center;
}

.rtds-stage :deep(.rtds-layout),
.rtds-stage :deep(.rtds-layout > .relative.z-10) {
    min-height: 1080px;
    height: 1080px;
}

.court-switcher {
    position: fixed;
    right: 1rem;
    bottom: 1rem;
    z-index: 50;
    color: #f8fafc;
    font: 600 0.8rem/1.3 system-ui, sans-serif;
    text-align: left;
}

.court-switcher summary,
.court-switcher nav {
    border: 1px solid rgba(255, 255, 255, 0.22);
    border-radius: 0.65rem;
    background: rgba(15, 23, 42, 0.88);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
}

.court-switcher summary {
    cursor: pointer;
    padding: 0.6rem 0.8rem;
    list-style: none;
}

.court-switcher summary::-webkit-details-marker {
    display: none;
}

.court-switcher nav {
    position: absolute;
    right: 0;
    bottom: calc(100% + 0.5rem);
    min-width: 10rem;
    max-height: min(60vh, 24rem);
    overflow-y: auto;
    padding: 0.35rem;
}

.court-switcher a {
    display: block;
    border-radius: 0.4rem;
    padding: 0.55rem 0.7rem;
    color: inherit;
    text-decoration: none;
    white-space: nowrap;
}

.court-switcher a:hover,
.court-switcher a[aria-current="page"] {
    background: rgba(255, 255, 255, 0.16);
}
</style>
