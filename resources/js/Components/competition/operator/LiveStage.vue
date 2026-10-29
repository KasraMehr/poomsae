<script setup>
import { computed } from 'vue';
import { usePerformanceClock } from '../../../Shared/Composables/usePerformanceTiming';
import FormErrors from '../../../Shared/Components/FormErrors.vue';

const props = defineProps({
    match: { type: Object, default: null },
    category: { type: Object, default: null },
    performance: { type: Object, default: null },
    court: { type: Object, default: null },
    processing: { type: Boolean, default: false },
    errors: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['command', 'continue']);
const now = usePerformanceClock(() => props.performance?.server_now);
const durationSeconds = computed(() => props.performance?.live_timing?.duration_seconds
    ?? props.performance?.timing?.maximum_duration_seconds
    ?? (props.category?.discipline === 'freestyle' ? 100 : 90));
const elapsedSeconds = computed(() => {
    if (!props.performance?.started_at) {
        return 0;
    }

    const endAt = props.performance.ended_at ? Date.parse(props.performance.ended_at) : now.value;

    return Math.max(0, Math.floor((endAt - Date.parse(props.performance.started_at)) / 1000));
});
const remainingSeconds = computed(() => Math.max(0, durationSeconds.value - elapsedSeconds.value));
const timerText = computed(() => {
    const seconds = remainingSeconds.value;

    return `${String(Math.floor(seconds / 60)).padStart(2, '0')}:${String(seconds % 60).padStart(2, '0')}`;
});
const elapsedText = computed(() => `${String(Math.floor(elapsedSeconds.value / 60)).padStart(2, '0')}:${String(elapsedSeconds.value % 60).padStart(2, '0')}`);
const canStart = computed(() => props.performance?.status === 'pending'
    && props.court?.queue?.some(item => item.id === props.performance.id));
const athleteProfiles = computed(() => props.match?.entries?.map((entry, index) => ({
    ...entry,
    sideLabel: entry.side?.toUpperCase() ?? (index === 0 ? 'CHUNG / BLUE' : 'HONG / RED'),
    profile: props.category?.entries?.find(categoryEntry => categoryEntry.id === entry.id) ?? null,
})) ?? []);
const statusText = computed(() => ({
    pending: canStart.value ? 'Match ready to begin' : 'Waiting for this match to reach the court queue',
    running: 'Performance in progress',
    scoring: 'Performance ended · scoring is available in Result',
    approved: 'Result confirmed',
    completed: 'Match completed',
    cancelled: 'Match cancelled',
})[props.performance?.status] ?? 'Match status unavailable');
</script>

<template>
    <section v-if="match" class="operator-live-stage" aria-label="Live performance">
        <header class="operator-live-context">
            <strong>No.{{ match.sequence }}</strong>
            <span>{{ match.category_name }} · {{ match.round_name }}</span>
            <span v-if="performance" class="operator-live-form">{{ performance.form_name }} · FORM {{ performance.form_number }}</span>
        </header>

        <div class="operator-live-athletes">
            <article v-for="(entry, index) in athleteProfiles" :key="entry.id" class="operator-live-athlete" :class="index === 0 ? 'chung' : 'hong'">
                <header>{{ entry.sideLabel }}</header>
                <h2>{{ entry.profile?.short_name || entry.short_name || entry.name }}</h2>
                <p>{{ entry.profile?.club || entry.profile?.country_code || entry.team || ' ' }}</p>
                <span class="operator-live-athlete-state">{{ performance?.status === 'running' ? 'PERFORMING' : performance?.status === 'pending' ? 'READY' : statusText }}</span>
            </article>
        </div>

        <div class="operator-live-clock-panel" :class="performance?.status">
            <span class="operator-live-clock-label">TIME REMAINING</span>
            <strong class="operator-live-clock" role="timer" aria-live="off">{{ timerText }}</strong>
            <span class="operator-live-elapsed">ELAPSED {{ elapsedText }}</span>
        </div>

        <footer class="operator-live-controls">
            <p>{{ statusText }}</p>
            <button v-if="canStart" type="button" class="operator-live-action primary" :disabled="processing" @click="emit('command', 'start')">
                {{ processing ? 'STARTING…' : 'START PERFORMANCE' }}
            </button>
            <button v-else-if="performance?.status === 'running'" type="button" class="operator-live-action danger" :disabled="processing" @click="emit('command', 'finish')">
                {{ processing ? 'FINISHING…' : 'FINISH PERFORMANCE' }}
            </button>
            <button v-else-if="performance?.status === 'scoring'" type="button" class="operator-live-action primary" @click="emit('continue', 'result')">
                GO TO RESULTS
            </button>
            <button v-else-if="['approved', 'completed', 'cancelled'].includes(performance?.status)" type="button" class="operator-live-action secondary" @click="emit('continue', 'confirmed')">
                VIEW MATCH SUMMARY
            </button>
        </footer>
        <FormErrors :errors="errors" />
    </section>

    <section v-else class="operator-live-empty" aria-label="Live match unavailable">
        <h2>No match selected</h2>
        <p>Select a match from the list to view its live controls.</p>
    </section>
</template>

<style scoped>
.operator-live-stage {
    display: grid;
    grid-template-rows: auto minmax(0, 1fr) auto auto;
    gap: clamp(10px, 2vh, 20px);
    height: 100%;
    min-height: 0;
    padding: clamp(10px, 1.5vw, 20px);
    box-sizing: border-box;
    color: #0d192b;
}

.operator-live-context {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto;
    align-items: center;
    gap: 12px;
    padding-bottom: 10px;
    border-bottom: 1px solid #c4c9d2;
}

.operator-live-context > strong {
    font-size: 24px;
    font-weight: 900;
}

.operator-live-context > span {
    justify-self: center;
    color: #586478;
    font-size: 14px;
    font-weight: 700;
    text-align: center;
}

.operator-live-context .operator-live-form {
    justify-self: end;
    text-align: right;
}

.operator-live-athletes {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    align-items: stretch;
    gap: 14px;
    min-height: 0;
}

.operator-live-athlete {
    display: flex;
    flex-direction: column;
    justify-content: center;
    min-width: 0;
    padding: clamp(12px, 2vw, 24px);
    border: 1px solid #c4c9d2;
    background: #ebf1fd;
    text-align: center;
}

.operator-live-athlete.hong {
    background: #f6f2f4;
}

.operator-live-athlete header {
    align-self: flex-start;
    font-size: 12px;
    font-weight: 900;
    letter-spacing: 1px;
    color: #2475bf;
}

.operator-live-athlete.hong header {
    color: #c44750;
}

.operator-live-athlete h2 {
    overflow: hidden;
    margin: 10px 0 4px;
    color: #0d192b;
    font-size: clamp(21px, 2.7vw, 34px);
    font-weight: 800;
    line-height: 1.2;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.operator-live-athlete p {
    min-height: 18px;
    margin: 0;
    color: #667184;
    font-size: 13px;
}

.operator-live-athlete-state {
    align-self: center;
    margin-top: 20px;
    padding: 5px 10px;
    background: #dce4f3;
    color: #536176;
    font-size: 10px;
    font-weight: 900;
    letter-spacing: .8px;
}

.operator-live-clock-panel {
    display: grid;
    justify-items: center;
    gap: 5px;
    padding: 8px 12px;
}

.operator-live-clock-label,
.operator-live-elapsed {
    color: #738094;
    font-size: 9px;
    font-weight: 800;
    letter-spacing: 1.4px;
}

.operator-live-clock {
    color: #0a1422;
    font-family: "OperatorMixedMono", Consolas, monospace;
    font-size: clamp(52px, 8vw, 92px);
    font-weight: 800;
    font-variant-numeric: tabular-nums;
    line-height: 1;
}

.operator-live-clock-panel.running .operator-live-clock {
    color: #a9232b;
}

.operator-live-controls {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding-top: 12px;
    border-top: 1px solid #c4c9d2;
}

.operator-live-controls p {
    margin: 0;
    color: #667184;
    font-size: 12px;
}

.operator-live-action {
    min-width: 190px;
    min-height: 48px;
    padding: 11px 18px;
    border: 1px solid #0d192b;
    font-size: 12px;
    font-weight: 900;
    letter-spacing: .8px;
    cursor: pointer;
}

.operator-live-action.primary {
    background: #0d192b;
    color: #fff;
}

.operator-live-action.danger {
    border-color: #a9232b;
    background: #a9232b;
    color: #fff;
}

.operator-live-action.secondary {
    background: #e4eaf5;
    color: #0d192b;
}

.operator-live-action:disabled {
    cursor: wait;
    opacity: .65;
}

.operator-live-empty {
    display: grid;
    min-height: 280px;
    align-content: center;
    justify-items: center;
    color: #667184;
}

.operator-live-empty h2,
.operator-live-empty p {
    margin: 4px;
}

@media (max-width: 740px) {
    .operator-live-stage {
        grid-template-rows: auto minmax(180px, 1fr) auto auto;
        gap: 10px;
        min-height: 0;
    }

    .operator-live-context {
        grid-template-columns: auto minmax(0, 1fr);
        gap: 6px 10px;
    }

    .operator-live-context > span:nth-child(2) {
        justify-self: end;
        text-align: right;
    }

    .operator-live-context .operator-live-form {
        grid-column: 1 / -1;
        justify-self: end;
    }

    .operator-live-athletes {
        gap: 8px;
    }

    .operator-live-athlete {
        padding: 10px 7px;
    }

    .operator-live-athlete header {
        font-size: 9px;
    }

    .operator-live-athlete h2 {
        font-size: clamp(16px, 5vw, 22px);
    }

    .operator-live-athlete p {
        font-size: 10px;
    }

    .operator-live-athlete-state {
        margin-top: 12px;
        padding: 4px 6px;
        font-size: 8px;
    }

    .operator-live-clock {
        font-size: clamp(48px, 15vw, 68px);
    }

    .operator-live-controls {
        align-items: stretch;
        flex-direction: column;
        gap: 8px;
    }

    .operator-live-action {
        width: 100%;
    }
}
</style>
