<script setup>
import { computed } from 'vue';

const props = defineProps({
    match: { type: Object, default: null },
    bout: { type: Object, default: null },
});

const entries = computed(() => (props.match?.entries ?? []).map((entry, index) => {
    const isHong = ['hong', 'red'].includes(entry.side?.toLowerCase()) || index === 1;

    return {
        ...entry,
        sideClass: isHong ? 'hong' : 'chung',
        sideLabel: isHong ? 'HONG' : 'CHUNG',
        winner: props.match?.winner_entry_id === entry.id,
    };
}));
const number = value => value == null ? '—' : Number(value).toFixed(2);
const forms = computed(() => {
    const groupedForms = new Map();

    for (const performance of props.bout?.performances ?? []) {
        if (!groupedForms.has(performance.form_number)) {
            groupedForms.set(performance.form_number, { number: performance.form_number, name: performance.form_name, performances: [] });
        }

        groupedForms.get(performance.form_number).performances.push(performance);
    }

    return [...groupedForms.values()]
        .sort((left, right) => left.number - right.number)
        .map(form => ({
            ...form,
            entries: entries.value.map(entry => ({
                ...entry,
                performance: form.performances.find(item => item.entry_id === entry.id) ?? null,
            })),
        }));
});
const finalResults = computed(() => entries.value.map(entry => ({
    ...entry,
    total: props.match?.totals?.[entry.id] ?? null,
    winner: props.match?.winner_entry_id === entry.id,
})));
const tableRows = computed(() => `auto auto repeat(${forms.value.length}, minmax(0, 1fr)) minmax(0, 1.25fr)`);
const accuracyFor = performance => performance?.detailed_scores?.accuracy ?? null;
const presentationFor = performance => performance?.detailed_scores?.presentation ?? null;
const scoreFor = performance => performance?.result ?? performance?.preview_result ?? performance?.detailed_scores?.total ?? null;
</script>

<template>
    <section v-if="match" class="operator-confirmed-stage" aria-label="Confirmed form results and final result">
        <section
            v-if="entries.length"
            class="operator-confirmed-table"
            :class="{ 'single-entry': entries.length === 1 }"
            :style="{ gridTemplateRows: tableRows }"
            aria-label="Confirmed form results and final result"
        >
            <div class="operator-confirmed-table-spacer" aria-hidden="true"></div>
            <article
                v-for="entry in entries"
                :key="`athlete-${entry.id}`"
                class="operator-confirmed-athlete"
                :class="[entry.sideClass, { 'winner-column': entry.winner }]"
            >
                <span>{{ entry.sideLabel }}</span>
                <h2>{{ entry.short_name || entry.name }}</h2>
            </article>

            <div v-if="forms.length" class="operator-confirmed-form-column-head">
                FORM {{ forms[0].number }}
            </div>
            <div v-else class="operator-confirmed-table-spacer" aria-hidden="true"></div>
            <div
                v-for="entry in entries"
                :key="`score-head-${entry.id}`"
                class="operator-confirmed-score-head"
                :class="[entry.sideClass, { 'winner-column': entry.winner }]"
            >
                <span>ACCURACY</span>
                <span>PRESENTATION</span>
                <span>TOTAL</span>
            </div>

            <template v-for="form in forms" :key="form.number">
                <div
                    class="operator-confirmed-form-label"
                    :class="{ 'first-form': form.number === forms[0]?.number }"
                    :aria-label="`Form ${form.number}: ${form.name}`"
                >
                    <span v-if="form.number !== forms[0]?.number">FORM {{ form.number }}</span>
                    <strong>{{ form.name }}</strong>
                </div>
                <div
                    v-for="entry in form.entries"
                    :key="`${form.number}-${entry.id}`"
                    class="operator-confirmed-score-group"
                    :class="[entry.sideClass, { 'winner-column': entry.winner }]"
                >
                    <div class="operator-confirmed-score-part">{{ number(accuracyFor(entry.performance)) }}</div>
                    <div class="operator-confirmed-score-part">{{ number(presentationFor(entry.performance)) }}</div>
                    <strong class="operator-confirmed-score-total">{{ number(scoreFor(entry.performance)) }}</strong>
                </div>
            </template>

            <p v-if="!forms.length" class="operator-confirmed-empty">No form results are available for this match.</p>

            <div class="operator-confirmed-final-label">
                FINAL RESULT
            </div>
            <article
                v-for="entry in finalResults"
                :key="`final-${entry.id}`"
                class="operator-confirmed-final-score"
                :class="[entry.sideClass, { 'winner-column': entry.winner }]"
            >
                <div class="operator-confirmed-final-total">
                    <strong>{{ number(entry.total) }}</strong>
                    <span v-if="entry.winner">WINNER</span>
                </div>
            </article>
        </section>
    </section>

    <section v-else class="operator-confirmed-empty" aria-label="Confirmed results unavailable">
        <h2>No match selected</h2>
        <p>Select a match to review confirmed form results.</p>
    </section>
</template>

<style scoped>
.operator-confirmed-stage{display:flex;height:100%;min-height:100%;padding:12px 0 0;box-sizing:border-box;color:#0d192b}
.operator-confirmed-table{display:grid;flex:1;min-height:0;grid-template-columns:max-content repeat(2,minmax(0,1fr));border:1px solid #c4c9d2;background:#f7f8fc;direction:ltr}
.operator-confirmed-table.single-entry{grid-template-columns:max-content minmax(0,1fr)}
.operator-confirmed-table-spacer,.operator-confirmed-form-column-head,.operator-confirmed-athlete,.operator-confirmed-score-head,.operator-confirmed-form-label,.operator-confirmed-score-group,.operator-confirmed-final-score,.operator-confirmed-final-label{min-width:0;border-right:1px solid #c4c9d2;border-bottom:1px solid #c4c9d2}
.operator-confirmed-table-spacer{background:#edf1f9}
.operator-confirmed-form-column-head{display:grid;place-items:center;background:#0d192b;color:white;font-size:10px;font-weight:900;letter-spacing:.6px}
.operator-confirmed-athlete{position:relative;display:grid;place-items:center;padding:24px 10px 10px;background:#ebf1fd;text-align:center}
.operator-confirmed-athlete.hong,.operator-confirmed-score-group.hong,.operator-confirmed-final-score.hong{background:#f6f2f4}
.operator-confirmed-athlete>span{position:absolute;top:7px;left:10px;color:#2475bf;font-size:11px;font-weight:900;letter-spacing:1px}
.operator-confirmed-athlete.hong>span{color:#c44750}
.operator-confirmed-athlete h2{overflow:hidden;width:100%;margin:0;color:#0d192b;font-size:clamp(16px,1.5vw,22px);font-weight:800;text-overflow:ellipsis;white-space:nowrap}
.operator-confirmed-score-head,.operator-confirmed-score-group{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr) minmax(0,2fr);direction:ltr}
.operator-confirmed-score-head{background:#e5eaf3;color:#596678;text-align:center}
.operator-confirmed-score-head>span{display:grid;place-items:center;padding:7px 4px;font-size:10px;font-weight:900;letter-spacing:.4px}
.operator-confirmed-score-head>span+span,.operator-confirmed-score-part,.operator-confirmed-score-total{border-left:1px solid #c4c9d2}
.operator-confirmed-form-label{display:flex;min-width:0;flex-direction:column;align-items:stretch;justify-content:flex-start;background:#edf1f9}
.operator-confirmed-form-label.first-form{justify-content:center}
.operator-confirmed-form-label>span{display:grid;width:100%;min-height:30px;place-items:center;box-sizing:border-box;background:#0d192b;color:white;font-size:10px;font-weight:900;letter-spacing:.6px;white-space:nowrap}
.operator-confirmed-form-label>strong{display:grid;flex:1;place-items:center;max-width:min(20vw,240px);padding:8px;font-size:12px;font-weight:800;text-align:center;overflow-wrap:anywhere}
.operator-confirmed-score-group{background:#ebf1fd}
.operator-confirmed-score-part,.operator-confirmed-score-total{display:grid;place-items:center;min-width:0;padding:8px 4px;color:#0d192b;font-family:"OperatorMixedMono",Consolas,monospace;font-size:clamp(17px,2vw,28px);font-weight:800;white-space:nowrap}
.operator-confirmed-score-total{color:#2475bf;font-size:clamp(24px,3vw,40px);font-weight:900}
.operator-confirmed-score-group.hong .operator-confirmed-score-total{color:#c44750}
.operator-confirmed-final-label{display:grid;place-items:center;padding:10px;background:#0d192b;color:white;font-size:clamp(13px,1.4vw,18px);font-weight:900;letter-spacing:.5px;text-align:center}
.operator-confirmed-final-score{display:grid;grid-template-columns:minmax(0,1fr);place-items:stretch;background:#ebf1fd}
.operator-confirmed-table .winner-column.chung{--winner-outline-color:#2475bf;box-shadow:inset 2px 0 var(--winner-outline-color),inset -2px 0 var(--winner-outline-color)}
.operator-confirmed-table .winner-column.hong{--winner-outline-color:#c44750;box-shadow:inset 2px 0 var(--winner-outline-color),inset -2px 0 var(--winner-outline-color)}
.operator-confirmed-table .operator-confirmed-athlete.winner-column{box-shadow:inset 2px 0 var(--winner-outline-color),inset -2px 0 var(--winner-outline-color),inset 0 2px var(--winner-outline-color)}
.operator-confirmed-table .operator-confirmed-final-score.winner-column{box-shadow:inset 2px 0 var(--winner-outline-color),inset -2px 0 var(--winner-outline-color),inset 0 -2px var(--winner-outline-color)}
.operator-confirmed-final-total{display:grid;align-content:center;justify-items:center;gap:5px;width:100%;padding:8px;text-align:center}
.operator-confirmed-final-total strong{color:#2475bf;font-family:"OperatorMixedMono",Consolas,monospace;font-size:clamp(36px,5vw,62px);font-weight:900;line-height:1.05;white-space:nowrap}
.operator-confirmed-final-score.hong .operator-confirmed-final-total strong{color:#c44750}
.operator-confirmed-final-total>span{color:var(--winner-outline-color,#2475bf);font-size:clamp(14px,1.5vw,20px);font-weight:900;letter-spacing:.8px}
.operator-confirmed-empty{grid-column:1/-1;margin:0;padding:12px;color:#667184;font-size:10px}
.operator-confirmed-empty h2,.operator-confirmed-empty p{margin:4px}
@media(max-width:600px){.operator-confirmed-stage{padding-top:8px}.operator-confirmed-table{grid-template-columns:max-content repeat(2,minmax(0,1fr))}.operator-confirmed-table.single-entry{grid-template-columns:max-content minmax(0,1fr)}.operator-confirmed-form-column-head{font-size:8px}.operator-confirmed-athlete{padding:22px 6px 6px}.operator-confirmed-athlete>span{top:5px;left:6px;font-size:9px}.operator-confirmed-athlete h2{font-size:14px}.operator-confirmed-score-head>span{padding:5px 2px;font-size:8px}.operator-confirmed-form-label>span{min-height:26px;font-size:8px}.operator-confirmed-form-label>strong{max-width:min(24vw,130px);padding:5px;font-size:10px}.operator-confirmed-score-part{padding:5px 2px;font-size:clamp(13px,3.2vw,19px)}.operator-confirmed-score-total{padding:5px 2px;font-size:clamp(18px,4vw,26px)}.operator-confirmed-final-label{padding:6px;font-size:10px}.operator-confirmed-final-total{padding:5px}.operator-confirmed-final-total strong{font-size:clamp(26px,7vw,40px)}}
</style>
