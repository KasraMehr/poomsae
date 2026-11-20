<script setup>
import { computed, ref, watch } from 'vue';

const props = defineProps({
    match: { type: Object, default: null },
    category: { type: Object, default: null },
    performance: { type: Object, default: null },
    bout: { type: Object, default: null },
    judges: { type: Array, default: () => [] },
    errors: { type: Object, default: () => ({}) },
    manualPerformance: { type: Object, default: null },
    processing: { type: Boolean, default: false },
});

const emit = defineEmits(['command', 'manual', 'details', 'continue']);
const hasResult = computed(() => ['scoring', 'approved', 'completed'].includes(props.performance?.status));
const number = value => value == null ? '—' : Number(value).toFixed(2);
const scorePart = (score, part) => score?.values?.[part] == null ? '—' : number(score.values[part] / 100);
const formPerformanceFor = entry => (props.bout?.performances ?? []).find(item => item.entry_id === entry.id && item.form_number === props.performance?.form_number)
    ?? (props.performance?.entry_id === entry.id ? props.performance : null);
const isCurrentPerformance = entry => {
    const formPerformance = formPerformanceFor(entry);

    if (formPerformance?.id === props.performance?.id) {
        return true;
    }

    return props.category?.execution_mode === 'simultaneous'
        && ['running', 'scoring', 'approved'].includes(props.performance?.status)
        && formPerformance?.form_number === props.performance?.form_number
        && ['running', 'scoring', 'approved'].includes(formPerformance?.status);
};
const scoreFor = entry => {
    const formPerformance = formPerformanceFor(entry);

    return formPerformance?.result ?? formPerformance?.preview_result ?? formPerformance?.detailed_scores?.total ?? null;
};
const detailedScoresFor = entry => formPerformanceFor(entry)?.detailed_scores ?? null;
const hasManualScoreAction = computed(() => Boolean(props.manualPerformance));
const errorMessages = computed(() => Object.values(props.errors ?? {})
    .flatMap(value => Array.isArray(value) ? value : [value])
    .filter(Boolean)
    .map(String));
const simultaneousEntries = computed(() =>
    props.category?.execution_mode === 'simultaneous' && (props.match?.entries?.length ?? 0) > 1,
);
const judgeTables = computed(() => {
    const entries = props.match?.entries ?? [];
    const tableEntries = simultaneousEntries.value
        ? entries
        : entries.filter(entry => entry.id === props.performance?.entry_id).slice(0, 1);
    const resolvedEntries = tableEntries.length > 0
        ? tableEntries
        : entries.slice(0, 1);

    return resolvedEntries.map((entry, index) => {
        const tablePerformance = simultaneousEntries.value
            ? formPerformanceFor(entry)
            : props.performance;
        const scores = tablePerformance?.scores ?? [];

        return {
            entryId: entry.id,
            entry,
            sideLabel: entry.side?.toUpperCase() ?? (index === 0 ? 'CHUNG / BLUE' : 'HONG / RED'),
            performance: tablePerformance,
            rows: [...props.judges]
                .sort((left, right) => left.seat - right.seat)
                .map(judge => ({
                    judge,
                    score: scores.find(score => score.judge_assignment_id === judge.id)
                        ?? scores.find(score => score.seat === judge.seat)
                        ?? null,
                })),
            judgeCount: props.judges.length || tablePerformance?.judge_count || props.category?.judge_count || 0,
        };
    });
});
const selectedJudgeTableId = ref(null);
watch(
    () => judgeTables.value.map(table => table.entryId),
    entryIds => {
        if (!entryIds.includes(selectedJudgeTableId.value)) {
            selectedJudgeTableId.value = entryIds[0] ?? null;
        }
    },
    { immediate: true },
);
const scoreStatus = score => {
    if (!score) {
        return { label: 'NO SCORE', className: 'none' };
    }

    if (score.submission_mode === 'operator_proxy') {
        return score.status === 'draft'
            ? { label: 'MANUAL REVIEW', className: 'manual-review' }
            : { label: 'MANUAL CONFIRMED', className: 'manual-confirmed' };
    }

    return { label: 'DIRECT ENTRY', className: 'direct' };
};
const judgeStatus = score => {
    if (!score) {
        return { label: 'SCORE PENDING', className: 'pending' };
    }

    if (score.status === 'draft') {
        return { label: 'REVIEW REQUIRED', className: 'review-required' };
    }

    return { label: 'SCORE SUBMITTED', className: 'submitted' };
};
const scoreTotal = score => score?.values
    ? number((score.values.score ?? ((score.values.accuracy ?? 0) + (score.values.presentation ?? 0))) / 100)
    : '—';
const tableSummary = table => {
    if (table.performance?.result != null) {
        return `FINAL AVG: ${number(table.performance.result)}`;
    }

    if (table.performance?.preview_result != null) {
        return `PREVIEW: ${number(table.performance.preview_result)}`;
    }

    return `${table.performance?.submitted_count ?? 0} / ${table.judgeCount} SCORES`;
};
</script>

<template>
    <section
        v-if="match"
        class="operator-result-stage"
        :class="{ 'with-table-tabs': judgeTables.length > 1 }"
        aria-label="Match result and scoring"
    >
        <template v-if="hasResult">
            <div class="operator-result-cards" :class="{ 'single-entry': match.entries.length === 1 }">
                <article
                    v-for="(entry, index) in match.entries"
                    :key="entry.id"
                    class="operator-result-card"
                    :class="[
                        match.entries.length === 1 ? 'solo' : entry.side?.toLowerCase() === 'hong' || index === 1 ? 'hong' : 'chung',
                        {
                            winner: entry.id === bout?.winner_entry_id,
                            'not-performed': !formPerformanceFor(entry),
                            'form-performer': isCurrentPerformance(entry),
                        },
                    ]"
                >
                    <div class="operator-result-identity">
                        <div class="operator-result-card-head">
                            <span>{{ match.entries.length === 1 ? 'ATHLETE' : entry.side?.toUpperCase() ?? (index === 0 ? 'CHUNG / BLUE' : 'HONG / RED') }}</span>
                        </div>
                        <h2>{{ entry.short_name || entry.name }}</h2>
                    </div>
                    <div class="operator-result-card-scores">
                        <div class="operator-result-components">
                            <div><small>ACCURACY</small><strong>{{ number(detailedScoresFor(entry)?.accuracy) }}</strong></div>
                            <div><small>PRESENTATION</small><strong>{{ number(detailedScoresFor(entry)?.presentation) }}</strong></div>
                        </div>
                        <div class="operator-result-total">
                            <small>TOTAL</small>
                            <strong>{{ number(scoreFor(entry)) }}</strong>
                        </div>
                    </div>
                </article>
                <article v-if="!match.entries.length" class="operator-result-no-athletes">No athletes assigned.</article>
            </div>

            <div v-if="judgeTables.length > 1" class="operator-result-table-tabs" role="group" aria-label="Choose a side's judge scores">
                <button
                    v-for="table in judgeTables"
                    :key="table.entryId"
                    type="button"
                    :aria-pressed="selectedJudgeTableId === table.entryId"
                    :class="{ active: selectedJudgeTableId === table.entryId, hong: table.entry.side?.toLowerCase() === 'hong' }"
                    @click="selectedJudgeTableId = table.entryId"
                >
                    {{ table.sideLabel }}
                </button>
            </div>

            <div class="operator-result-matrices" :class="{ paired: judgeTables.length > 1 }">
                <article
                    v-for="table in judgeTables"
                    :key="table.entryId"
                    class="operator-result-matrix"
                    :class="{
                        hong: table.entry.side?.toLowerCase() === 'hong',
                        'mobile-selected': selectedJudgeTableId === table.entryId,
                    }"
                >
                    <div class="operator-result-matrix-head">
                        <strong>JUDGE SCORES</strong>
                        <span v-if="tableSummary(table)">{{ tableSummary(table) }}</span>
                    </div>
                    <div class="operator-result-table-wrap">
                        <table>
                            <thead><tr><th>JUDGE</th><th>ACCURACY</th><th>PRESENTATION</th><th>TOTAL</th><th>SCORE ENTRY</th><th>JUDGE STATUS</th></tr></thead>
                            <tbody>
                                <tr v-for="row in table.rows" :key="row.judge.id">
                                    <td>J{{ row.judge.seat }}</td>
                                    <td>{{ scorePart(row.score, 'accuracy') }}</td>
                                    <td>{{ scorePart(row.score, 'presentation') }}</td>
                                    <td>{{ scoreTotal(row.score) }}</td>
                                    <td>
                                        <span class="operator-result-status-badge score-entry-badge" :class="scoreStatus(row.score).className" :title="scoreStatus(row.score).label">
                                            {{ scoreStatus(row.score).label }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="operator-result-status-badge judge-status-badge" :class="judgeStatus(row.score).className" :title="judgeStatus(row.score).label">
                                            {{ judgeStatus(row.score).label }}
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="!table.rows.length"><td colspan="6">No judges assigned.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </article>
            </div>

            <div class="operator-result-actions">
                <div v-if="errorMessages.length" class="operator-result-action-messages">
                    <p v-if="errorMessages.length" class="operator-result-errors" role="alert">{{ errorMessages.join(' · ') }}</p>
                </div>
                <div class="operator-result-action-buttons">
                    <button type="button" class="operator-result-details" @click="emit('details')">Match controls and decisions ↗</button>
                    <button v-if="hasManualScoreAction" type="button" class="operator-result-manual" @click="emit('manual')">{{ manualPerformance.pending_review_seats?.length ? 'Review manual score' : 'Manual score / correct judge score' }}</button>
                    <button v-if="performance?.status === 'scoring'" type="button" class="operator-result-primary" :disabled="processing || performance.submitted_count !== performance.judge_count || performance.pending_review_seats?.length" @click="emit('command', 'approve')">Confirm and publish result</button>
                </div>
            </div>
        </template>

        <div v-else class="operator-result-waiting">
            <div v-if="errorMessages.length" class="operator-result-action-messages">
                <p class="operator-result-errors" role="alert">{{ errorMessages.join(' · ') }}</p>
            </div>
            <div class="operator-result-action-buttons">
                <button v-if="performance?.status === 'running'" type="button" @click="emit('continue', 'live')">RETURN TO LIVE</button>
                <button type="button" class="operator-result-details" @click="emit('details')">Match controls and decisions ↗</button>
            </div>
        </div>
    </section>

    <section v-else class="operator-result-empty" aria-label="Result unavailable">
        <h2>No match selected</h2>
        <p>Select a match from the list to review its result.</p>
    </section>
</template>

<style scoped>
.operator-result-stage {
    display: grid;
    height: 100%;
    gap: clamp(6px, 1vh, 10px);
    min-height: 0;
    grid-template-rows: auto minmax(0, 1fr) auto;
    padding: 8px 0 0;
    box-sizing: border-box;
    color: #0d192b;
}

.operator-result-stage.with-table-tabs {
    grid-template-rows: auto auto minmax(0, 1fr) auto;
}

.operator-result-cards {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
}

.operator-result-cards.single-entry {
    grid-template-columns: minmax(0, 1fr);
}
.operator-result-card.solo { background: #edf2f9; }

.operator-result-card {
    position: relative;
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1.35fr);
    align-items: center;
    gap: 10px;
    min-width: 0;
    padding: 9px 10px;
    border: 1px solid #c4c9d2;
    background: #ebf1fd;
}

.operator-result-card.hong {
    background: #f6f2f4;
}

.operator-result-card.winner {
    border: 2px solid #0d192b;
}

.operator-result-card.form-performer {
    border: 2px solid #2475bf;
    box-shadow: 0 4px 14px #0d192b16;
}

.operator-result-card.hong.form-performer {
    border-color: #c44750;
}

.operator-result-card.not-performed {
    opacity: .65;
}

.operator-result-identity {
    min-width: 0;
}

.operator-result-card-head,
.operator-result-card-scores {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 6px;
}

.operator-result-card-head span {
    color: #2475bf;
    font-size: 11px;
    font-weight: 900;
    letter-spacing: 1px;
}

.operator-result-card.hong .operator-result-card-head span {
    color: #c44750;
}

.operator-result-card h2 {
    overflow: hidden;
    margin: 4px 0 2px;
    font-size: clamp(16px, 1.5vw, 22px);
    font-weight: 800;
    text-align: left;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.operator-result-card-scores {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 4px;
    min-width: 0;
}

.operator-result-components {
    display: contents;
}

.operator-result-components > div,
.operator-result-total {
    display: flex;
    min-width: 0;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 4px 3px;
    background: #ffffff75;
}

.operator-result-total {
    gap: 5px;
}

.operator-result-components small,
.operator-result-total small {
    color: #657083;
    font-size: 7px;
    font-weight: 800;
    letter-spacing: .2px;
    white-space: nowrap;
}

.operator-result-components strong,
.operator-result-total strong {
    font-family: "OperatorMixedMono", Consolas, monospace;
    font-size: clamp(16px, 1.55vw, 23px);
    font-weight: 900;
    line-height: 1.15;
}

.operator-result-components strong {
    color: #0d192b;
}

.operator-result-total strong {
    font-size: clamp(21px, 2vw, 30px);
}

.operator-result-card.chung .operator-result-total strong {
    color: #2475bf;
}

.operator-result-card.hong .operator-result-total strong {
    color: #c44750;
}

.operator-result-table-tabs {
    display: none;
}

.operator-result-matrices {
    display: grid;
    min-width: 0;
    min-height: 0;
    grid-template-columns: minmax(0, 1fr);
    gap: 8px;
}

.operator-result-matrices.paired {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.operator-result-matrix {
    display: flex;
    min-width: 0;
    min-height: 0;
    flex-direction: column;
    border: 1px solid #c4c9d2;
    background: #f8f9fc;
}

.operator-result-matrix.hong {
    border-color: #d5b8bc;
}

.operator-result-matrix-head {
    display: flex;
    min-width: 0;
    flex: none;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 6px 8px;
    background: #edf1f9;
    font-size: 10px;
}

.operator-result-matrix.hong .operator-result-matrix-head {
    background: #f9e8e9;
    color: #a13c45;
}

.operator-result-matrix:not(.hong) .operator-result-matrix-head {
    background: #e5efff;
    color: #1f64a8;
}

.operator-result-matrix-head strong {
    overflow: hidden;
    min-width: 0;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.operator-result-matrix-head span {
    flex: none;
    padding: 1px 7px;
    background: #dce4f3;
    font-family: "OperatorMixedMono", Consolas, monospace;
    font-size: 9px;
}

.operator-result-matrix.hong .operator-result-matrix-head > span {
    background: #eddfe1;
}

.operator-result-table-wrap {
    min-width: 0;
    min-height: 0;
    flex: 1;
    overflow: hidden;
}

.operator-result-table-wrap table {
    width: 100%;
    height: 100%;
    border-collapse: collapse;
    table-layout: fixed;
    text-align: left;
}

.operator-result-table-wrap th,
.operator-result-table-wrap td {
    overflow: hidden;
    padding: 5px 7px;
    border-bottom: 1px solid #e0e3ea;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.operator-result-table-wrap th:nth-child(1),
.operator-result-table-wrap td:nth-child(1) { width: 10%; }
.operator-result-table-wrap th:nth-child(2),
.operator-result-table-wrap td:nth-child(2) { width: 16%; }
.operator-result-table-wrap th:nth-child(3),
.operator-result-table-wrap td:nth-child(3) { width: 17%; }
.operator-result-table-wrap th:nth-child(4),
.operator-result-table-wrap td:nth-child(4) { width: 12%; }
.operator-result-table-wrap th:nth-child(5),
.operator-result-table-wrap td:nth-child(5),
.operator-result-table-wrap th:nth-child(6),
.operator-result-table-wrap td:nth-child(6) { width: 22.5%; }

.operator-result-table-wrap th {
    background: #dae1ee;
    color: #596579;
    font-size: 10px;
    letter-spacing: .2px;
}

.operator-result-table-wrap td {
    font-family: "OperatorMixedMono", Consolas, monospace;
    font-size: 12px;
    font-weight: 700;
}

.operator-result-status-badge {
    display: inline-flex;
    max-width: 100%;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    padding: 2px 5px;
    border: 1px solid currentColor;
    border-radius: 0;
    font-family: inherit;
    font-size: 9px;
    font-weight: 900;
    line-height: 1.2;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.operator-result-status-badge.none,
.operator-result-status-badge.pending {
    border-color: #8b98ac;
    background: #dce3ed;
    color: #44536a;
}

.operator-result-status-badge.manual-review,
.operator-result-status-badge.review-required {
    border-color: #b87917;
    background: #ffe2a6;
    color: #603900;
}

.operator-result-status-badge.manual-confirmed {
    border-color: #6653a5;
    background: #ded6f7;
    color: #3e2c79;
}

.operator-result-status-badge.direct {
    border-color: #346baf;
    background: #d5e7ff;
    color: #173d73;
}

.operator-result-status-badge.submitted {
    border-color: #28734e;
    background: #ccebd8;
    color: #104a2e;
}

.operator-result-status-badge {
    text-align: center;
}

.operator-result-actions {
    position: sticky;
    bottom: 0;
    z-index: 5;
    display: grid;
    width: 100%;
    align-self: end;
    gap: 3px;
    padding: 0;
    border-top: 1px solid #c4c9d2;
    background: #f7f8fc;
    box-shadow: 0 -8px 14px #0d192b12;
}

.operator-result-action-messages {
    display: grid;
    gap: 2px;
    min-width: 0;
}

.operator-result-action-buttons {
    display: flex;
    min-width: 0;
    direction: ltr;
    align-items: center;
    justify-content: flex-start;
    flex-wrap: nowrap;
    gap: 8px;
}

.operator-result-action-buttons > button {
    min-width: 0;
    max-width: 240px;
    flex: 0 1 auto;
    min-height: 34px;
    padding: 6px 10px;
    font-size: 10px;
    line-height: 1.2;
    text-align: center;
    white-space: normal;
}

.operator-result-primary,
.operator-result-manual,
.operator-result-actions .operator-result-details,
.operator-result-waiting button {
    min-height: 42px;
    padding: 9px 14px;
    border: 1px solid #0d192b;
    background: #0d192b;
    color: #fff;
    font-size: 12px;
    font-weight: 800;
    cursor: pointer;
}

.operator-result-actions .operator-result-action-buttons > button {
    min-height: 34px;
    padding: 6px 10px;
    font-size: 10px;
    line-height: 1.2;
}

.operator-result-primary:disabled {
    cursor: wait;
    opacity: .55;
}

.operator-result-manual {
    border-color: #b7752b;
    background: #fff1da;
    color: #6c3f13;
}

.operator-result-errors {
    color: #81541e;
    font-size: 12px;
    font-weight: 700;
}

.operator-result-errors {
    margin: 0;
    overflow-wrap: anywhere;
    padding: 3px 6px;
    border-left: 3px solid #9b1f27;
    background: #fce9e9;
    color: #9b1f27;
    line-height: 1.2;
}

.operator-result-waiting {
    position: sticky;
    bottom: 0;
    z-index: 5;
    display: grid;
    grid-row: -2;
    align-self: end;
    gap: 3px;
    padding: 0;
    border-top: 1px solid #c4c9d2;
    background: #f7f8fc;
    box-shadow: 0 -8px 14px #0d192b12;
}

.operator-result-details {
    min-width: 0;
    border-color: #bfc6d2;
    background: #e6ebf4;
    color: #0d192b;
}

.operator-result-no-athletes,
.operator-result-empty {
    padding: 18px;
    color: #667184;
}

.operator-result-empty {
    display: grid;
    min-height: 280px;
    align-content: center;
    justify-items: center;
}

@media (max-width: 600px) {
    .operator-result-cards {
        gap: 7px;
    }

    .operator-result-card {
        grid-template-columns: minmax(0, 1fr);
        padding: 8px;
    }

    .operator-result-card h2 {
        font-size: clamp(15px, 4vw, 18px);
    }

    .operator-result-card-scores {
        gap: 5px;
    }

    .operator-result-components strong {
        font-size: clamp(15px, 4vw, 19px);
    }

    .operator-result-total strong {
        font-size: clamp(21px, 5vw, 25px);
    }

    .operator-result-actions {
        gap: 3px;
    }

    .operator-result-action-buttons {
        gap: 4px;
    }

    .operator-result-action-buttons > button {
        min-width: 0;
        flex: 0 1 auto;
        padding: 5px 6px;
        font-size: 9px;
        line-height: 1.25;
        white-space: normal;
    }

    .operator-result-actions .operator-result-action-buttons > button {
        min-height: 30px;
        padding: 5px 6px;
        font-size: 9px;
        line-height: 1.25;
    }

    .operator-result-errors {
        font-size: 10px;
    }

    .operator-result-stage {
        padding: 6px 0 0;
        gap: 5px;
    }

    .operator-result-matrix-head {
        gap: 4px;
        padding: 5px;
        font-size: 8px;
    }

    .operator-result-matrix-head > span {
        padding: 1px 3px;
        font-size: 7px;
    }

    .operator-result-table-wrap th,
    .operator-result-table-wrap td {
        padding: 4px 3px;
    }

    .operator-result-table-wrap th {
        font-size: 8px;
        letter-spacing: 0;
    }

    .operator-result-table-wrap td {
        font-size: 10px;
    }

    .operator-result-status-badge {
        padding: 2px 3px;
        font-size: 8px;
    }
}

@media (max-width: 900px) {
    .operator-result-table-tabs {
        display: flex;
        min-width: 0;
        gap: 5px;
    }

    .operator-result-table-tabs button {
        min-width: 0;
        flex: 1;
        overflow: hidden;
        padding: 5px 8px;
        border: 1px solid #aeb9cc;
        background: #edf1f9;
        color: #2475bf;
        font-size: 9px;
        font-weight: 900;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .operator-result-table-tabs button.hong {
        border-color: #d5b8bc;
        background: #f6eeee;
        color: #c44750;
    }

    .operator-result-table-tabs button.active {
        outline: 2px solid currentColor;
        outline-offset: -2px;
    }

    .operator-result-matrices.paired {
        grid-template-columns: minmax(0, 1fr);
    }

    .operator-result-matrices.paired .operator-result-matrix {
        display: none;
    }

    .operator-result-matrices.paired .operator-result-matrix.mobile-selected {
        display: flex;
    }
}
</style>
