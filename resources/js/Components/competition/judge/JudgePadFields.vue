<script setup>
import { computed, watch } from 'vue';
import { adjustPenalties } from '../../../Shared/Services/scoring';
import { criterionScore, judgeCriteria, scoreDecimal } from '../../../Shared/Services/judgeScoring';
import JudgeScoreStepper from './JudgeScoreStepper.vue';

const props = defineProps({ form: Object, rules: Object, disabled: Boolean, paired: Boolean, inputPrefix: String });
const emit = defineEmits(['total']);
const criteria = computed(() => judgeCriteria(props.rules));
const values = computed(() => criteria.value.map(criterion => criterionScore(props.form, criterion)));
watch(values, scores => {
    criteria.value.forEach((criterion, index) => {
        if (criterion.type !== 'direct') props.form[criterion.field] = scores[index] === null ? '' : scoreDecimal(scores[index]);
    });
    emit('total', scores.some(score => score === null) ? null : scoreDecimal(scores.reduce((sum, score) => sum + score, 0)));
}, { immediate: true });
const penalties = criterion => props.form[`${criterion.field}_penalties`] ?? [];
const changePenalty = (criterion, delta) => {
    if (!props.disabled) props.form[`${criterion.field}_penalties`] = adjustPenalties(penalties(criterion), delta);
};
const undoPenalty = criterion => {
    const last = penalties(criterion).at(-1);
    if (last) changePenalty(criterion, Math.round(Number(last) * 100));
};
const fillEmptyAtMax = criterion => {
    if (props.disabled) return;
    const field = `${criterion.field}_components`;
    props.form[field] = props.form[field].map(value => value === '' || value === null ? scoreDecimal(criterion.componentMaximum) : value);
};
</script>

<template>
    <div class="score-controls" :class="{ paired, freestyle: rules.input_method === 'freestyle_components_v1', single: criteria.length === 1 }">
        <section v-for="(criterion, index) in criteria" :key="criterion.field" class="score-criterion" :class="criterion.type" :aria-label="criterion.label">
            <header class="criterion-title"><div><h2>{{ criterion.label }}</h2><span>{{ criterion.type === 'penalty' ? 'Deduct as you judge' : criterion.type === 'components' ? `${form[`${criterion.field}_components`].length} components · tap ± or enter` : 'Tap ± or enter score' }}</span></div><strong>{{ values[index] === null ? '—' : scoreDecimal(values[index]) }}<small>/ {{ scoreDecimal(criterion.maximum) }}</small></strong></header>
            <div v-if="criterion.type === 'penalty'" class="penalty-controls">
                <div class="deduction-row">
                    <button type="button" class="deduct-small" :disabled="disabled || values[index] < 10" :aria-label="`Deduct 0.1 from ${criterion.label}`" @click="changePenalty(criterion, -10)"><span>−0.1</span><small>SMALL ERROR</small></button>
                    <button type="button" class="deduct-large" :disabled="disabled || values[index] < 30" :aria-label="`Deduct 0.3 from ${criterion.label}`" @click="changePenalty(criterion, -30)"><span>−0.3</span><small>LARGE ERROR</small></button>
                    <button type="button" class="undo" :disabled="disabled || !penalties(criterion).length" :aria-label="`Undo last ${criterion.label} deduction`" @click="undoPenalty(criterion)"><span>↶</span><small>UNDO</small></button>
                </div>
                <div class="deduction-log" aria-live="polite"><span>{{ penalties(criterion).length }} deductions</span><strong>−{{ scoreDecimal(criterion.maximum - values[index]) }}</strong><span class="last-deduction">Last: {{ penalties(criterion).length ? `−${penalties(criterion).at(-1)}` : '—' }}</span></div>
            </div>
            <div v-else-if="criterion.type === 'components'" class="component-list">
                <div class="component-tools"><span>Tap ± for 0.1 steps</span><button type="button" :disabled="disabled || form[`${criterion.field}_components`].every(value => value !== '' && value !== null)" @click="fillEmptyAtMax(criterion)">Fill blanks at max</button></div>
                <div v-for="(_, part) in form[`${criterion.field}_components`]" :key="part" class="component-row">
                    <label :for="`${inputPrefix}-${criterion.field}-${part}`"><b>{{ criterion.field === 'accuracy' ? 'A' : 'P' }}{{ part + 1 }}</b><span>of {{ scoreDecimal(criterion.componentMaximum) }}</span></label>
                    <JudgeScoreStepper v-model="form[`${criterion.field}_components`][part]" :input-id="`${inputPrefix}-${criterion.field}-${part}`" :label="`${criterion.label} component ${part + 1}`" :maximum="criterion.componentMaximum" :disabled="disabled" />
                </div>
            </div>
            <div v-else class="direct-controls"><button type="button" :disabled="disabled || form[criterion.field] === scoreDecimal(criterion.maximum)" @click="form[criterion.field] = scoreDecimal(criterion.maximum)">Start at max</button><JudgeScoreStepper v-model="form[criterion.field]" :input-id="`${inputPrefix}-${criterion.field}`" :label="criterion.label" :maximum="criterion.maximum" :disabled="disabled" /></div>
        </section>
    </div>
</template>

<style scoped>
.score-controls{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;align-items:stretch}.score-controls.single{grid-template-columns:1fr}
.score-criterion{min-width:0;padding:16px;border:1px solid #293b50;border-radius:14px;background:#122033;display:flex;flex-direction:column;gap:16px}
.direct-controls{display:flex;align-items:center;gap:9px}.direct-controls>button{min-height:48px;flex:none;padding:5px 12px;border:1px solid #54718d;border-radius:9px;color:#dcecf9;font-size:11px;font-weight:700}.direct-controls>button:disabled{opacity:.35}.direct-controls :deep(.score-stepper){flex:1}
.criterion-title{display:flex;align-items:center;justify-content:space-between;gap:8px}.criterion-title h2{margin:0;font-size:15px;font-weight:700;line-height:1.3;color:#e6edf7}.criterion-title div>span{color:#8fa5bd;font-size:11px}.criterion-title strong{color:#fff;font-size:30px;line-height:1;font-variant-numeric:tabular-nums;white-space:nowrap}.criterion-title small{display:block;text-align:right;margin-top:5px;font-size:11px;font-weight:500;color:#91a6be}
.penalty-controls{flex:1;display:flex;flex-direction:column;justify-content:center;gap:16px}.deduction-row{display:grid;grid-template-columns:1fr 1fr 60px;gap:8px}.deduction-row button{min-height:100px;border:1px solid transparent;border-radius:12px;display:flex;align-items:center;justify-content:center;flex-direction:column;touch-action:manipulation;user-select:none}.deduction-row button span{font-size:38px;font-weight:750;line-height:1.3}.deduction-row button small{font-size:9px;letter-spacing:.08em;font-weight:700}.deduct-small{background:#efd08b;color:#282315}.deduct-large{background:#e99b81;color:#30201d}.deduction-row .undo{background:#27394e;color:#c5d8eb;border-color:#3b526a}.deduction-row button:active:not(:disabled){filter:brightness(.85)}.deduction-row button:disabled{opacity:.3;cursor:default}.deduction-row button:focus-visible{outline:2px solid white;outline-offset:3px}
.deduction-log{display:flex;align-items:center;gap:10px;font-size:12px;color:#a7b8ca}.deduction-log strong{color:#efd08b}.last-deduction{margin-left:auto}.component-list{display:grid;gap:8px}.component-tools{grid-column:1/-1;display:flex;align-items:center;justify-content:space-between;gap:6px;color:#8da5bb;font-size:10px}.component-tools button{min-height:44px;padding:4px 10px;border:1px solid #54718d;border-radius:7px;color:#dcecf9;font-size:10px;font-weight:700}.component-tools button:disabled{opacity:.35}.component-row{display:grid;grid-template-columns:minmax(50px,1fr) minmax(160px,2fr);gap:8px;align-items:center}.component-row label{display:flex;align-items:center;gap:8px;color:#e2ebf5}.component-row label b{font-size:14px;font-weight:700}.component-row label span{font-size:10px;color:#8fa5bd}.freestyle .component-list{grid-template-columns:repeat(2,minmax(0,1fr))}.freestyle .component-row{grid-template-columns:1fr;gap:3px}.freestyle .component-row label{justify-content:space-between}.single{max-width:720px;width:100%;margin:auto}
@media(min-width:1100px) and (min-height:600px){.paired.freestyle .component-list{grid-template-columns:1fr}.paired.freestyle .component-row{grid-template-columns:minmax(30px,1fr) minmax(152px,2fr)}.paired.freestyle .component-row label span{display:none}}
@media(min-width:1100px) and (min-height:600px){.paired .penalty-controls{gap:10px}.paired .deduction-row button{min-height:76px}}
@media(max-height:500px) and (orientation:landscape){.score-controls{gap:8px}.score-criterion{padding:9px 10px;gap:8px;border-radius:10px}.criterion-title h2{font-size:13px}.criterion-title div>span{font-size:9px}.criterion-title strong{font-size:24px}.criterion-title small{display:inline;margin-left:5px;font-size:10px}.component-list{gap:5px}.component-row{gap:6px}.component-row label{gap:5px}.deduction-row button{min-height:82px}.deduction-row button span{font-size:32px}.penalty-controls{gap:10px}.deduction-log{font-size:10px}.freestyle .component-list{gap:8px}}
@media(max-height:500px) and (orientation:landscape){.direct-controls>button{min-height:44px}}
@media(max-width:560px) and (orientation:portrait){.score-controls{grid-template-columns:1fr}.score-criterion{padding:12px}.deduction-row button{min-height:84px}}
</style>
