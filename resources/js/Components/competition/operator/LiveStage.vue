<script setup>
import { computed } from 'vue';
import { usePerformanceClock } from '../../../Shared/Composables/usePerformanceTiming';
import FormErrors from '../../../Shared/Components/FormErrors.vue';
import { getEntryMemberNames, getEntrySideClass } from './entryPresentation';
import { vAutoFitText } from './autoFitText';

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
const isOvertime = computed(() => props.performance?.status === 'running'
    && Boolean(props.performance?.started_at)
    && elapsedSeconds.value >= durationSeconds.value);
const timerStopped = computed(() => Boolean(props.performance?.started_at)
    && ['scoring', 'approved', 'completed', 'cancelled'].includes(props.performance?.status));
const timerText = computed(() => {
    const seconds = remainingSeconds.value;

    return `${String(Math.floor(seconds / 60)).padStart(2, '0')}:${String(seconds % 60).padStart(2, '0')}`;
});
const elapsedText = computed(() => `${String(Math.floor(elapsedSeconds.value / 60)).padStart(2, '0')}:${String(elapsedSeconds.value % 60).padStart(2, '0')}`);
const canStart = computed(() => props.performance?.status === 'pending'
    && props.court?.queue?.some(item => item.id === props.performance.id));
const athleteProfiles = computed(() => props.match?.entries?.map((entry, index) => {
    const profile = props.category?.entries?.find(categoryEntry => categoryEntry.id === entry.id) ?? null;

    return {
        ...entry,
        sideClass: getEntrySideClass(entry, index),
        sideLabel: entry.side?.toUpperCase() ?? (index === 0 ? 'CHUNG / BLUE' : 'HONG / RED'),
        profile,
        memberNames: getEntryMemberNames(entry, profile),
    };
}) ?? []);
const activeEntryIds = computed(() => {
    const status = props.performance?.status;
    if (!['pending', 'running', 'scoring'].includes(status)) {
        return [];
    }

    if (props.category?.execution_mode === 'simultaneous') {
        return (props.match?.entries ?? []).map(entry => entry.id);
    }

    return props.performance?.entry_id ? [props.performance.entry_id] : [];
});
const isActiveEntry = entry => activeEntryIds.value.includes(entry.id);
const activeStateText = computed(() => ({
    pending: canStart.value ? 'READY TO PERFORM' : 'UP NEXT',
    running: 'NOW PERFORMING',
    scoring: 'SCORING THIS ENTRY',
})[props.performance?.status] ?? '');
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
        <div class="operator-live-athletes" :class="{ 'single-athlete': athleteProfiles.length === 1 }">
            <article v-for="(entry, index) in athleteProfiles" :key="entry.id" class="operator-live-athlete" :class="[entry.sideClass, { solo: athleteProfiles.length === 1, active: isActiveEntry(entry), scoring: isActiveEntry(entry) && performance?.status === 'scoring' }]">
                <header>
                    <strong>{{ athleteProfiles.length === 1 ? 'ATHLETE' : entry.sideLabel }}</strong>
                    <span class="operator-live-club" :title="entry.profile?.club || entry.profile?.country_code || entry.team || ''">
                        {{ entry.profile?.club || entry.profile?.country_code || entry.team || '' }}
                    </span>
                </header>
                <div class="operator-live-entry-content">
                    <div
                        v-auto-fit-text="{ min: 18, maxLines: 2 }"
                        class="operator-live-member-list"
                        :class="`member-count-${Math.min(entry.memberNames.length, 3)}`"
                        dir="ltr"
                        :title="entry.memberNames.join(' / ')"
                    >
                        {{ entry.memberNames.join(' / ') }}
                    </div>
                    <span
                        v-if="isActiveEntry(entry)"
                        v-auto-fit-text="{ min: 8 }"
                        class="operator-live-athlete-state"
                    >{{ activeStateText }}</span>
                </div>
            </article>
        </div>

        <div class="operator-live-clock-panel" :class="[performance?.status, { overtime: isOvertime, stopped: timerStopped }]">
            <span class="operator-live-clock-label">TIME REMAINING</span>
            <strong class="operator-live-clock" role="timer" aria-live="off">{{ timerText }}</strong>
            <span class="operator-live-elapsed">ELAPSED {{ elapsedText }}</span>
        </div>

        <div class="operator-live-footer">
            <FormErrors :errors="errors" />
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
        </div>
    </section>

    <section v-else class="operator-live-empty" aria-label="Live match unavailable">
        <h2>No match selected</h2>
        <p>Select a match from the list to view its live controls.</p>
    </section>
</template>

<style scoped>
.operator-live-stage {
    display: grid;
    grid-template-rows: auto minmax(0, 1fr) auto;
    align-content: stretch;
    gap: clamp(8px, 1.2vh, 14px);
    height: 100%;
    min-height: 0;
    padding: clamp(5px, .8vw, 10px) 0 0;
    box-sizing: border-box;
    color: #0d192b;
}

.operator-live-athletes {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    align-items: stretch;
    gap: 14px;
    min-height: 0;
}

.operator-live-athletes.single-athlete {
    grid-template-columns: minmax(0, 1fr);
    justify-content: stretch;
}
.operator-live-athlete.solo {
    background: #edf2f9;
}

.operator-live-athlete {
    display: grid;
    grid-template-rows: auto minmax(0, 1fr);
    align-content: stretch;
    gap: 4px;
    min-width: 0;
    min-height: clamp(110px, 16vh, 148px);
    padding: 10px 15px 12px;
    border: 1px solid #c4c9d2;
    background: #ebf1fd;
    text-align: center;
}

.operator-live-athlete.active {
    border: 3px solid #2475bf;
    padding: 8px 13px 10px;
    box-shadow: 0 5px 16px #2475bf20;
}

.operator-live-athlete.hong {
    background: #f6f2f4;
}

.operator-live-athlete.hong.active {
    border-color: #c44750;
    box-shadow: 0 5px 16px #c4475020;
}

.operator-live-athlete.scoring {
    border-style: dashed;
}

.operator-live-athlete header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    width: 100%;
    min-width: 0;
    font-size: 12px;
    font-weight: 900;
    letter-spacing: 1px;
    color: #2475bf;
}

.operator-live-athlete.hong header {
    color: #c44750;
    flex-direction: row-reverse;
}

.operator-live-entry-content {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    align-content: stretch;
    gap: clamp(8px, 1vw, 14px);
    min-width: 0;
    min-height: 0;
    direction: ltr;
}

.operator-live-athlete.hong .operator-live-entry-content {
    direction: rtl;
}

.operator-live-member-list {
    display: -webkit-box;
    flex: 1 1 0;
    width: auto;
    min-width: min-content;
    max-width: 100%;
    max-height: 2.9em;
    min-height: 0;
    overflow: hidden;
    color: #0d192b;
    direction: ltr;
    font-weight: 800;
    line-height: 1.4;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    overflow-wrap: break-word;
    text-align: left;
    word-break: normal;
}

.operator-live-athlete.hong .operator-live-member-list {
    text-align: right;
}

.operator-live-member-list.member-count-1 {
    font-size: clamp(28px, 3.4vw, 44px);
}

.operator-live-member-list.member-count-2 {
    font-size: clamp(22px, 2.8vw, 34px);
}

.operator-live-member-list.member-count-3 {
    font-size: clamp(19px, 2.4vw, 30px);
}

.operator-live-club {
    min-width: 0;
    max-width: 58%;
    overflow: hidden;
    color: #667184;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0;
    text-align: right;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.operator-live-athlete.hong .operator-live-club {
    text-align: left;
}

.operator-live-athlete-state {
    align-self: flex-end;
    flex: 0 1 auto;
    box-sizing: border-box;
    width: max-content;
    min-width: 0;
    max-width: 100%;
    margin: 0 0 2px;
    margin-inline-start: auto;
    padding: 5px 10px;
    background: #dce4f3;
    color: #536176;
    font-size: clamp(8px, .7vw, 10px);
    font-weight: 900;
    letter-spacing: .8px;
    line-height: 1.15;
    direction: ltr;
    text-align: center;
    white-space: nowrap;
}

.operator-live-clock-panel {
    align-self: center;
    justify-self: center;
    display: grid;
    justify-items: center;
    gap: 8px;
    padding: 10px 12px;
}

.operator-live-clock-label {
    color: #738094;
    font-size: clamp(11px, 1vw, 14px);
    font-weight: 800;
    letter-spacing: 1.4px;
}

.operator-live-elapsed {
    color: #738094;
    font-size: clamp(13px, 1.15vw, 17px);
    font-weight: 900;
    letter-spacing: 1.2px;
    line-height: 1.2;
}

.operator-live-clock {
    color: #0a1422;
    font-family: "OperatorMixedMono", Consolas, monospace;
    font-size: clamp(58px, 8.5vw, 116px);
    font-weight: 800;
    font-variant-numeric: tabular-nums;
    line-height: 1;
}

.operator-live-clock-panel.overtime .operator-live-clock,
.operator-live-clock-panel.overtime .operator-live-elapsed,
.operator-live-clock-panel.stopped .operator-live-clock,
.operator-live-clock-panel.stopped .operator-live-elapsed {
    color: #a9232b;
}

.operator-live-footer {
    display: flex;
    min-width: 0;
    flex-direction: column;
    gap: 4px;
    align-self: end;
}

.operator-live-footer :deep([role="alert"]) {
    margin: 0;
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

@media (max-height: 800px) {
    .operator-live-stage {
        gap: clamp(5px, 1vh, 10px);
        padding-block: clamp(4px, .7vh, 8px);
    }

    .operator-live-athletes {
        gap: clamp(8px, 1vw, 14px);
    }

    .operator-live-athlete {
        min-height: clamp(96px, 16vh, 136px);
        padding: clamp(6px, 1vh, 10px) 12px clamp(8px, 1vh, 12px);
    }

    .operator-live-athlete.active {
        padding: clamp(5px, .8vh, 9px) 10px clamp(7px, .9vh, 11px);
    }

    .operator-live-member-list.member-count-1 {
        font-size: clamp(24px, 3.8vh, 38px);
    }

    .operator-live-member-list.member-count-2 {
        font-size: clamp(19px, 3.2vh, 30px);
    }

    .operator-live-member-list.member-count-3 {
        font-size: clamp(17px, 2.8vh, 26px);
    }

    .operator-live-clock-panel {
        gap: 5px;
        padding-block: 6px;
    }

    .operator-live-clock {
        font-size: clamp(54px, 11vh, 96px);
    }

    .operator-live-elapsed {
        font-size: clamp(12px, 1.8vh, 15px);
    }

    .operator-live-controls {
        gap: 10px;
        padding-top: 7px;
    }

    .operator-live-action {
        min-height: 40px;
        padding-block: 8px;
    }
}

@media (max-height: 620px) {
    .operator-live-athlete {
        min-height: 88px;
    }

    .operator-live-athlete header,
    .operator-live-club,
    .operator-live-controls p {
        font-size: 10px;
    }

    .operator-live-member-list.member-count-1 {
        font-size: clamp(19px, 3vh, 26px);
    }

    .operator-live-member-list.member-count-2 {
        font-size: clamp(16px, 2.6vh, 22px);
    }

    .operator-live-member-list.member-count-3 {
        font-size: clamp(14px, 2.2vh, 19px);
    }

    .operator-live-athlete-state {
        padding: 3px 6px;
        font-size: 8px;
    }

    .operator-live-clock {
        font-size: clamp(46px, 9vh, 68px);
    }

    .operator-live-clock-label {
        font-size: 9px;
    }

    .operator-live-elapsed {
        font-size: 11px;
    }
}

@media (max-width: 740px) {
    .operator-live-stage {
        grid-template-rows: auto minmax(0, 1fr) auto;
        gap: 8px;
        min-height: 0;
    }

    .operator-live-athletes {
        gap: 8px;
    }

    .operator-live-athlete {
        min-height: 128px;
        padding: 7px 7px 9px;
    }

    .operator-live-athlete.active {
        padding: 5px 5px 7px;
    }

    .operator-live-athlete header {
        font-size: 9px;
    }

    .operator-live-member-list.member-count-1 {
        font-size: clamp(20px, 5vw, 30px);
    }

    .operator-live-member-list.member-count-2 {
        font-size: clamp(17px, 4vw, 25px);
    }

    .operator-live-member-list.member-count-3 {
        font-size: clamp(15px, 3.5vw, 21px);
    }

    .operator-live-club {
        font-size: 10px;
    }

    .operator-live-athlete-state {
        padding: 4px 6px;
        font-size: 8px;
    }

    .operator-live-clock {
        font-size: clamp(50px, 15vw, 82px);
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

@media (max-width: 740px) and (max-height: 700px) {
    .operator-live-stage {
        grid-template-rows: auto minmax(0, 1fr) auto;
        gap: 5px;
        padding: 5px 0 0;
    }

    .operator-live-athlete {
        min-height: 92px;
    }

    .operator-live-controls {
        flex-direction: row;
        align-items: center;
    }

    .operator-live-action {
        width: auto;
        min-width: 0;
    }
}

@media (min-width: 741px) and (min-height: 801px) and (max-height: 1000px) {
    .operator-live-athlete {
        min-height: clamp(110px, 14vh, 148px);
    }

    .operator-live-clock {
        font-size: clamp(58px, 11.5vh, 116px);
    }
}

@media (min-width: 741px) and (min-height: 621px) and (max-height: 800px) {
    .operator-live-stage {
        gap: clamp(5px, .8vh, 8px);
    }

    .operator-live-athlete {
        min-height: clamp(92px, 14.5vh, 136px);
    }
}

@media (max-width: 740px) and (max-height: 800px) {
    .operator-live-stage {
        gap: clamp(4px, .8vh, 7px);
        padding-block: clamp(3px, .6vh, 5px);
    }

    .operator-live-athletes {
        gap: clamp(5px, 1.2vw, 8px);
    }

    .operator-live-athlete {
        min-height: clamp(84px, 14vh, 128px);
        padding: clamp(4px, .8vh, 7px) clamp(4px, 1vw, 7px);
    }

    .operator-live-athlete.active {
        padding: clamp(2px, .5vh, 5px) clamp(2px, .7vw, 5px);
    }

    .operator-live-member-list {
        min-width: 0;
    }

    .operator-live-clock-panel {
        gap: clamp(3px, .6vh, 5px);
        padding-block: clamp(3px, .6vh, 5px);
    }

    .operator-live-clock {
        font-size: clamp(46px, 9vh, 82px);
    }
}

@media (max-height: 620px) {
    .operator-live-stage {
        gap: 4px;
        padding-block: 3px;
    }

    .operator-live-athlete {
        min-height: 88px;
        padding-block: 4px 5px;
    }

    .operator-live-athlete.active {
        padding-block: 2px 3px;
    }

    .operator-live-clock-panel {
        gap: 2px;
        padding-block: 2px;
    }

    .operator-live-clock {
        font-size: clamp(42px, 9vh, 68px);
    }

    .operator-live-controls {
        gap: 6px;
        padding-top: 4px;
    }

    .operator-live-action {
        min-height: 36px;
        padding-block: 5px;
    }
}
</style>
