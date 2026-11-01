<script setup>
import { computed } from 'vue';

const props = defineProps({
    match: { type: Object, default: null },
    bout: { type: Object, default: null },
});

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
            entries: (props.match?.entries ?? []).map((entry, index) => ({
                ...entry,
                sideClass: entry.side?.toLowerCase() === 'hong' || index === 1 ? 'hong' : 'chung',
                performance: form.performances.find(item => item.entry_id === entry.id) ?? null,
            })),
        }));
});
const finalResults = computed(() => (props.match?.entries ?? []).map((entry, index) => ({
    ...entry,
    sideClass: entry.side?.toLowerCase() === 'hong' || index === 1 ? 'hong' : 'chung',
    total: props.match?.totals?.[entry.id] ?? null,
    winner: props.match?.winner_entry_id === entry.id,
})));
const finalStatus = computed(() => props.match?.winner_entry_id
    ? 'FINAL RESULT CONFIRMED'
    : props.match?.status === 'completed'
        ? 'ROUND COMPLETE'
        : 'FINAL RESULT PENDING');
const scoreFor = performance => performance?.result ?? performance?.preview_result ?? performance?.detailed_scores?.total ?? null;
const stateFor = performance => {
    if (!performance) {
        return 'NOT PERFORMED';
    }

    if (performance.result != null || ['approved', 'completed'].includes(performance.status)) {
        return 'CONFIRMED';
    }

    if (performance.status === 'scoring') {
        return 'AWAITING CONFIRMATION';
    }

    if (performance.status === 'cancelled') {
        return 'CANCELLED';
    }

    return 'UP NEXT';
};
</script>

<template>
    <section v-if="match" class="operator-confirmed-stage" aria-label="Confirmed form results and final result">
        <header class="operator-confirmed-context">
            <strong>No.{{ match.sequence }}</strong>
            <span>{{ match.category_name }} · {{ match.round_name }}</span>
            <span class="operator-confirmed-status" :class="{ complete: match.status === 'completed' }">{{ finalStatus }}</span>
        </header>

        <section v-if="forms.length" class="operator-confirmed-forms" aria-label="Results by form">
            <article v-for="form in forms" :key="form.number" class="operator-confirmed-form">
                <header class="operator-confirmed-form-head">
                    <span>FORM {{ form.number }}</span>
                    <strong>{{ form.name }}</strong>
                </header>
                <div class="operator-confirmed-form-entries" :class="{ 'single-entry': form.entries.length === 1 }">
                    <article v-for="entry in form.entries" :key="entry.id" class="operator-confirmed-entry" :class="[entry.sideClass, { pending: !entry.performance || entry.performance.status === 'pending' }]">
                        <div class="operator-confirmed-entry-head">
                            <span>{{ entry.side?.toUpperCase() ?? (entry.sideClass === 'chung' ? 'CHUNG / BLUE' : 'HONG / RED') }}</span>
                            <small>{{ stateFor(entry.performance) }}</small>
                        </div>
                        <h3>{{ entry.short_name || entry.name }}</h3>
                        <div class="operator-confirmed-form-score">
                            <small>FORM RESULT</small>
                            <strong>{{ number(scoreFor(entry.performance)) }}</strong>
                        </div>
                    </article>
                </div>
            </article>
        </section>
        <p v-else class="operator-confirmed-empty">No form results are available for this match.</p>

        <section class="operator-confirmed-final" aria-label="Final round result">
            <header>
                <div><span>ROUND SUMMARY</span><h2>FINAL RESULT</h2></div>
                <strong :class="{ complete: match.status === 'completed' }">{{ finalStatus }}</strong>
            </header>
            <div class="operator-confirmed-final-entries" :class="{ 'single-entry': finalResults.length === 1 }">
                <article v-for="entry in finalResults" :key="entry.id" class="operator-confirmed-final-entry" :class="[entry.sideClass, { winner: entry.winner }]">
                    <div><span>{{ entry.side?.toUpperCase() ?? (entry.sideClass === 'chung' ? 'CHUNG / BLUE' : 'HONG / RED') }}</span><b v-if="entry.winner">★ FINAL WINNER</b></div>
                    <h3>{{ entry.short_name || entry.name }}</h3>
                    <small>{{ match.totals?.[entry.id] != null ? 'FINAL TOTAL' : 'FINAL TOTAL PENDING' }}</small>
                    <strong class="operator-confirmed-total">{{ number(entry.total) }}</strong>
                </article>
            </div>
            <p v-if="!match.winner_entry_id && match.status !== 'completed'" class="operator-confirmed-pending">The final winner will appear here when all form results are confirmed.</p>
        </section>
    </section>

    <section v-else class="operator-confirmed-empty" aria-label="Confirmed results unavailable">
        <h2>No match selected</h2>
        <p>Select a match to review confirmed form results.</p>
    </section>
</template>

<style scoped>
.operator-confirmed-stage{display:grid;gap:12px;min-height:100%;padding:12px;box-sizing:border-box;color:#0d192b}
.operator-confirmed-context{display:grid;grid-template-columns:auto minmax(0,1fr) auto;align-items:center;gap:12px;padding-bottom:10px;border-bottom:1px solid #c4c9d2}
.operator-confirmed-context>strong{font-size:24px;font-weight:900}
.operator-confirmed-context>span{justify-self:center;color:#586478;font-size:13px;font-weight:700;text-align:center}
.operator-confirmed-status{padding:5px 8px;border:1px solid #e0cf9f;background:#fff5df;color:#805c31!important;font-size:9px!important;font-weight:900!important;letter-spacing:.5px}
.operator-confirmed-status.complete,.operator-confirmed-final header>strong.complete{border-color:#a8d5b5;background:#e4f3e9;color:#167651!important}
.operator-confirmed-forms{display:grid;gap:10px}
.operator-confirmed-form{border:1px solid #c4c9d2;background:#f7f8fc}
.operator-confirmed-form-head{display:flex;align-items:center;gap:10px;padding:8px 10px;border-bottom:1px solid #c4c9d2;background:#edf1f9}
.operator-confirmed-form-head span{padding:3px 7px;background:#0d192b;color:white;font-size:9px;font-weight:900;letter-spacing:.6px}
.operator-confirmed-form-head strong{font-size:12px}
.operator-confirmed-form-entries,.operator-confirmed-final-entries{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;padding:8px}
.operator-confirmed-form-entries.single-entry,.operator-confirmed-final-entries.single-entry{grid-template-columns:minmax(0,1fr)}
.operator-confirmed-entry{min-width:0;padding:9px;border:1px solid #c4c9d2;background:#ebf1fd}
.operator-confirmed-entry.hong,.operator-confirmed-final-entry.hong{background:#f6f2f4}
.operator-confirmed-entry.pending{opacity:.7}
.operator-confirmed-entry-head,.operator-confirmed-final-entry>div{display:flex;align-items:center;justify-content:space-between;gap:8px}
.operator-confirmed-entry-head>span,.operator-confirmed-final-entry>div>span{color:#2475bf;font-size:9px;font-weight:900;letter-spacing:.6px}
.operator-confirmed-entry.hong .operator-confirmed-entry-head>span,.operator-confirmed-final-entry.hong>div>span{color:#c44750}
.operator-confirmed-entry-head small{color:#667184;font-size:8px;font-weight:900}
.operator-confirmed-entry h3,.operator-confirmed-final-entry h3{overflow:hidden;margin:5px 0;text-overflow:ellipsis;white-space:nowrap;font-size:13px;font-weight:800}
.operator-confirmed-form-score{display:flex;align-items:center;justify-content:space-between;padding-top:5px;border-top:1px solid #c4c9d280}
.operator-confirmed-form-score small,.operator-confirmed-final-entry>small{color:#667184;font-size:8px;font-weight:900;letter-spacing:.4px}
.operator-confirmed-form-score strong{font-family:"OperatorMixedMono",Consolas,monospace;font-size:21px;font-weight:900}
.operator-confirmed-entry.chung .operator-confirmed-form-score strong,.operator-confirmed-final-entry.chung .operator-confirmed-total{color:#2475bf}
.operator-confirmed-entry.hong .operator-confirmed-form-score strong,.operator-confirmed-final-entry.hong .operator-confirmed-total{color:#c44750}
.operator-confirmed-final{border:1px solid #8e9bad;background:#f7f8fc}
.operator-confirmed-final>header{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 12px;border-bottom:1px solid #c4c9d2;background:#e5eaf3}
.operator-confirmed-final>header span{color:#667184;font-size:8px;font-weight:900;letter-spacing:1px}
.operator-confirmed-final h2{margin:2px 0 0;font-size:15px;font-weight:900}
.operator-confirmed-final header>strong{padding:4px 7px;border:1px solid #e0cf9f;background:#fff5df;color:#805c31;font-size:9px;letter-spacing:.5px}
.operator-confirmed-final-entry{min-width:0;padding:10px;border:1px solid #c4c9d2;background:#ebf1fd}
.operator-confirmed-final-entry.winner{border:2px solid #0d192b;padding:9px;box-shadow:0 4px 14px #0d192b18}
.operator-confirmed-final-entry>div>b{padding:3px 6px;background:#0d192b;color:white;font-size:8px}
.operator-confirmed-final-entry>small{display:block}
.operator-confirmed-total{display:block;font-family:"OperatorMixedMono",Consolas,monospace;font-size:clamp(26px,4vw,40px);font-weight:900;line-height:1.1}
.operator-confirmed-pending,.operator-confirmed-empty{margin:0;padding:8px 12px;color:#667184;font-size:10px}
.operator-confirmed-empty{display:grid;min-height:260px;align-content:center;justify-items:center}
.operator-confirmed-empty h2,.operator-confirmed-empty p{margin:4px}
@media(max-width:600px){.operator-confirmed-context{grid-template-columns:auto minmax(0,1fr);gap:6px 10px}.operator-confirmed-context>span:nth-child(2){justify-self:end}.operator-confirmed-status{grid-column:1/-1;justify-self:end}.operator-confirmed-form-entries,.operator-confirmed-final-entries{gap:6px;padding:6px}.operator-confirmed-entry,.operator-confirmed-final-entry{padding:7px}.operator-confirmed-final-entry.winner{padding:6px}.operator-confirmed-total{font-size:27px}}
</style>
