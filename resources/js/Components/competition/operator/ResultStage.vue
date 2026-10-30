<script setup>
import { computed } from 'vue';
import FormErrors from '../../../Shared/Components/FormErrors.vue';

const props = defineProps({
    match: { type: Object, default: null },
    performance: { type: Object, default: null },
    bout: { type: Object, default: null },
    judges: { type: Array, default: () => [] },
    manualPerformance: { type: Object, default: null },
    processing: { type: Boolean, default: false },
    errors: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['command', 'manual', 'details', 'continue']);
const scoreRows = computed(() => [...(props.performance?.scores ?? [])].sort((left, right) => left.seat - right.seat));
const hasResult = computed(() => ['scoring', 'approved', 'completed'].includes(props.performance?.status));
const number = value => value == null ? '—' : Number(value).toFixed(2);
const scorePart = (score, part) => score?.values?.[part] == null ? '—' : number(score.values[part] / 100);
const scoreFor = entry => props.match?.totals?.[entry.id]
    ?? [...(props.bout?.performances ?? [])].reverse().find(item => item.entry_id === entry.id && (item.result || item.preview_result))?.result
    ?? [...(props.bout?.performances ?? [])].reverse().find(item => item.entry_id === entry.id)?.preview_result
    ?? null;
const detailedScoresFor = entry => [...(props.bout?.performances ?? [])]
    .reverse()
    .find(item => item.entry_id === entry.id && item.detailed_scores)?.detailed_scores
    ?? null;
const hasManualScoreAction = computed(() => props.manualPerformance
    && (props.manualPerformance.missing_seats?.length || props.manualPerformance.pending_review_seats?.length));
const statusMessage = computed(() => ({
    pending: 'Results and judge scoring will appear here after the performance is finished.',
    running: 'The performance is still live. Finish it from the Live tab to collect scores here.',
    scoring: 'Review incoming judge scores and confirm the result when all required scores are ready.',
    approved: 'The result has been confirmed and published.',
    completed: 'This match is complete.',
    cancelled: 'This match was cancelled.',
})[props.performance?.status] ?? 'Select a match to review its result.');
</script>

<template>
    <section v-if="match" class="operator-result-stage" aria-label="Match result and scoring">
        <header class="operator-result-context">
            <strong>No.{{ match.sequence }}</strong>
            <span>{{ match.category_name }} · {{ match.round_name }}</span>
            <span v-if="performance" class="operator-result-form">{{ performance.form_name }} · FORM {{ performance.form_number }}</span>
        </header>

        <p class="operator-result-status" :class="performance?.status">{{ statusMessage }}</p>

        <template v-if="hasResult">
            <div class="operator-result-cards">
                <article v-for="(entry, index) in match.entries" :key="entry.id" class="operator-result-card" :class="[index === 0 ? 'chung' : 'hong', { winner: match.winner_entry_id === entry.id }]">
                    <div class="operator-result-card-head">
                        <span>{{ entry.side?.toUpperCase() ?? (index === 0 ? 'CHUNG / BLUE' : 'HONG / RED') }}</span>
                        <b v-if="match.winner_entry_id === entry.id">★ WINNER</b>
                    </div>
                    <h2>{{ entry.short_name || entry.name }}</h2>
                    <div class="operator-result-card-scores">
                        <div class="operator-result-components">
                            <div><small>ACCURACY</small><strong>{{ number(detailedScoresFor(entry)?.accuracy) }}</strong></div>
                            <div><small>PRESENTATION</small><strong>{{ number(detailedScoresFor(entry)?.presentation) }}</strong></div>
                        </div>
                        <div class="operator-result-total">
                            <small>{{ match.totals?.[entry.id] != null ? 'OFFICIAL TOTAL' : 'TOTAL' }}</small>
                            <strong>{{ number(scoreFor(entry)) }}</strong>
                        </div>
                    </div>
                </article>
                <article v-if="!match.entries.length" class="operator-result-no-athletes">No athletes assigned.</article>
            </div>

            <div class="operator-result-judge-status" aria-label="Judge score status">
                <div v-for="judge in judges" :key="judge.id" class="operator-result-judge" :class="{ submitted: judge.submitted, 'review-required': !judge.submitted && judge.pendingReview, pending: !judge.submitted && !judge.pendingReview }">
                    <strong>J{{ judge.seat }} · {{ judge.name }}</strong>
                    <small>{{ judge.submitted ? '✓ SCORE SUBMITTED' : judge.pendingReview ? 'REVIEW REQUIRED' : 'SCORE PENDING' }}</small>
                </div>
            </div>

            <div class="operator-result-matrix">
                <div class="operator-result-matrix-head">
                    <strong>JUDGE SCORE MATRIX</strong>
                    <span>{{ performance?.result != null ? `FINAL AVG: ${number(performance.result)}` : performance?.preview_result != null ? `PREVIEW: ${number(performance.preview_result)}` : `${performance?.submitted_count ?? 0} / ${performance?.judge_count ?? 0} SCORES` }}</span>
                </div>
                <div class="operator-result-table-wrap">
                    <table>
                        <thead><tr><th>JUDGE</th><th>ACCURACY</th><th>PRESENTATION</th><th>TOTAL</th><th>STATUS</th></tr></thead>
                        <tbody>
                            <tr v-for="score in scoreRows" :key="score.id">
                                <td>J{{ score.seat }}</td>
                                <td>{{ scorePart(score, 'accuracy') }}</td>
                                <td>{{ scorePart(score, 'presentation') }}</td>
                                <td>{{ score.values ? number((score.values.score ?? ((score.values.accuracy ?? 0) + (score.values.presentation ?? 0))) / 100) : '—' }}</td>
                                <td>{{ score.status === 'draft' ? 'MANUAL · REVIEW' : score.submission_mode === 'operator_proxy' ? 'MANUAL · CONFIRMED' : 'RECEIVED' }}</td>
                            </tr>
                            <tr v-if="!scoreRows.length"><td colspan="5">No scores received for this performance.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-if="performance?.status === 'scoring' && (performance.submitted_count !== performance.judge_count || performance.pending_review_seats?.length)" class="operator-result-notes" role="status">
                <span v-if="performance.submitted_count !== performance.judge_count" class="operator-result-note">Waiting for all judge scores</span>
                <span v-if="performance.pending_review_seats?.length" class="operator-result-note">Resolve manual score review before confirming</span>
            </div>
            <FormErrors :errors="errors" />

            <div class="operator-result-actions">
                <button v-if="performance?.status === 'scoring'" type="button" class="operator-result-primary" :disabled="processing || performance.submitted_count !== performance.judge_count || performance.pending_review_seats?.length" @click="emit('command', 'approve')">Confirm and publish result</button>
                <button v-if="hasManualScoreAction" type="button" class="operator-result-manual" @click="emit('manual')">{{ manualPerformance.pending_review_seats?.length ? 'Review manual score' : 'Manual score / tablet issue' }}</button>
                <button type="button" class="operator-result-details" @click="emit('details')">Match controls and decisions ↗</button>
            </div>
        </template>

        <div v-else class="operator-result-waiting">
            <button v-if="performance?.status === 'running'" type="button" @click="emit('continue', 'live')">RETURN TO LIVE</button>
            <button type="button" class="operator-result-details" @click="emit('details')">Match controls and decisions ↗</button>
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
    gap: 12px;
    min-height: 100%;
    padding: 12px;
    box-sizing: border-box;
    color: #0d192b;
}

.operator-result-context {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto;
    align-items: center;
    gap: 12px;
    padding-bottom: 10px;
    border-bottom: 1px solid #c4c9d2;
}

.operator-result-context strong {
    font-size: 24px;
    font-weight: 900;
}

.operator-result-context > span {
    justify-self: center;
    color: #586478;
    font-size: 14px;
    font-weight: 700;
    text-align: center;
}

.operator-result-context .operator-result-form {
    justify-self: end;
    text-align: right;
}

.operator-result-status {
    margin: 0;
    padding: 9px 12px;
    background: #edf1f9;
    color: #596579;
    font-size: 12px;
}

.operator-result-status.scoring {
    background: #fff5df;
    color: #805c31;
}

.operator-result-status.approved,
.operator-result-status.completed {
    background: #e9f4ed;
    color: #167651;
}

.operator-result-cards {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}

.operator-result-card {
    min-width: 0;
    padding: 12px;
    border: 1px solid #c4c9d2;
    background: #ebf1fd;
}

.operator-result-card.hong {
    background: #f6f2f4;
}

.operator-result-card.winner {
    border: 2px solid #0d192b;
}

.operator-result-card-head,
.operator-result-card-scores {
    display: flex;
    justify-content: space-between;
    gap: 8px;
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

.operator-result-card-head b {
    align-self: flex-start;
    padding: 3px 6px;
    background: #0d192b;
    color: white;
    font-size: 9px;
}

.operator-result-card h2 {
    overflow: hidden;
    margin: 8px 0;
    font-size: clamp(20px, 2vw, 28px);
    font-weight: 800;
    text-align: center;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.operator-result-card-scores {
    align-items: stretch;
    margin-top: 8px;
}

.operator-result-components {
    display: grid;
    flex: 1;
    gap: 5px;
}

.operator-result-components > div,
.operator-result-total {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 5px;
    background: #ffffff75;
}

.operator-result-components small,
.operator-result-total small {
    color: #657083;
    font-size: 9px;
    font-weight: 800;
    letter-spacing: .4px;
}

.operator-result-components strong {
    font-family: "OperatorMixedMono", Consolas, monospace;
    font-size: clamp(25px, 2.4vw, 36px);
    font-weight: 900;
}

.operator-result-total {
    min-width: 34%;
}

.operator-result-total strong {
    font-family: "OperatorMixedMono", Consolas, monospace;
    font-size: clamp(42px, 4.5vw, 64px);
    font-weight: 900;
    line-height: 1.1;
}

.operator-result-card.chung .operator-result-total strong {
    color: #2475bf;
}

.operator-result-card.hong .operator-result-total strong {
    color: #c44750;
}

.operator-result-judge-status {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 6px;
}

.operator-result-judge {
    min-width: 0;
    padding: 7px;
    border: 1px solid #c7ccd5;
    background: #f8f9fc;
}

.operator-result-judge strong,
.operator-result-judge small {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.operator-result-judge strong {
    font-size: 10px;
}

.operator-result-judge small {
    margin-top: 4px;
    color: #667184;
    font-size: 9px;
    font-weight: 900;
}

.operator-result-judge.submitted small {
    color: #167651;
}

.operator-result-judge.review-required small {
    color: #a16a22;
}

.operator-result-matrix {
    min-width: 0;
    border: 1px solid #c4c9d2;
}

.operator-result-matrix-head {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    padding: 8px;
    background: #edf1f9;
    font-size: 11px;
}

.operator-result-matrix-head span {
    padding: 1px 7px;
    background: #dce4f3;
    font-family: "OperatorMixedMono", Consolas, monospace;
}

.operator-result-table-wrap {
    overflow: auto;
}

.operator-result-table-wrap table {
    width: 100%;
    min-width: 430px;
    border-collapse: collapse;
    text-align: left;
}

.operator-result-table-wrap th,
.operator-result-table-wrap td {
    padding: 7px 10px;
    border-bottom: 1px solid #e0e3ea;
}

.operator-result-table-wrap th {
    background: #dae1ee;
    color: #596579;
    font-size: 10px;
    letter-spacing: .4px;
}

.operator-result-table-wrap td {
    font-family: "OperatorMixedMono", Consolas, monospace;
    font-size: 12px;
}

.operator-result-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: nowrap;
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

.operator-result-primary:disabled {
    cursor: wait;
    opacity: .55;
}

.operator-result-manual {
    border-color: #b7752b;
    background: #fff1da;
    color: #6c3f13;
}

.operator-result-note {
    color: #81541e;
    font-size: 11px;
    font-weight: 700;
}

.operator-result-notes {
    display: grid;
    gap: 3px;
}

.operator-result-waiting {
    display: flex;
    align-items: flex-start;
    justify-content: flex-start;
}

.operator-result-details {
    width: fit-content;
    min-width: 0;
    max-width: 240px;
    min-height: 42px;
    flex: none;
    justify-self: start;
    align-self: start;
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

@media (max-width: 850px) {
    .operator-result-judge-status {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (max-width: 600px) {
    .operator-result-context {
        grid-template-columns: auto minmax(0, 1fr);
        gap: 6px 10px;
    }

    .operator-result-context > span:nth-child(2) {
        justify-self: end;
        text-align: right;
    }

    .operator-result-context .operator-result-form {
        grid-column: 1 / -1;
        justify-self: end;
    }

    .operator-result-cards {
        gap: 7px;
    }

    .operator-result-card {
        padding: 8px;
    }

    .operator-result-card h2 {
        font-size: 17px;
    }

    .operator-result-card-scores {
        gap: 5px;
    }

    .operator-result-components strong {
        font-size: 24px;
    }

    .operator-result-total strong {
        font-size: 38px;
    }

    .operator-result-actions {
        gap: 5px;
    }

    .operator-result-actions > button {
        min-width: 0;
        flex: 1 1 0;
        padding: 6px 4px;
        font-size: 9px;
        line-height: 1.25;
        white-space: normal;
    }

    .operator-result-judge-status {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
</style>
