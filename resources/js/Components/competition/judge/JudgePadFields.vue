<script setup>
import { computed, ref, watch } from 'vue';
import { adjustPenalties } from '../../../Shared/Services/scoring';

const props = defineProps({ form: Object, rules: Object, disabled: Boolean, paired: Boolean, inputPrefix: String });
const method = computed(() => props.rules.input_method);
const accuracyPenalty = computed(() => method.value === 'deductions_and_components_v1');
const presentationPenalty = computed(() => method.value === 'components_and_deductions_v1');
const accuracyParts = computed(() => Array.isArray(props.form.accuracy_components));
const presentationParts = computed(() => Array.isArray(props.form.presentation_components));
const splitPresentation = computed(() => !presentationPenalty.value && !presentationParts.value && method.value !== 'single_score_v1' && props.rules.presentation_max >= 600);
const decimal = value => (value / 100).toFixed(2);
const sumParts = parts => parts?.every(value => value !== '' && value !== null)
    ? parts.reduce((sum, value) => sum + Math.round(Number(value) * 100), 0) : null;
const penaltyScore = parts => 400 - (parts ?? []).reduce((sum, value) => sum + Math.round(Number(value) * 100), 0);
const accuracyValue = computed(() => accuracyPenalty.value ? penaltyScore(props.form.accuracy_penalties) : accuracyParts.value ? sumParts(props.form.accuracy_components) : props.form.accuracy === '' ? null : Math.round(Number(props.form.accuracy) * 100));
const presentationValue = computed(() => presentationPenalty.value ? penaltyScore(props.form.presentation_penalties) : presentationParts.value ? sumParts(props.form.presentation_components) : method.value === 'single_score_v1' ? 0 : props.form.presentation === '' ? null : Math.round(Number(props.form.presentation) * 100));
const total = computed(() => accuracyValue.value === null || presentationValue.value === null ? '—' : decimal(accuracyValue.value + presentationValue.value));
const initialPresentation = props.form.presentation === '' ? null : Math.round(Number(props.form.presentation) * 100);
const directPresentationParts = ref(initialPresentation === null
    ? ['', '', '']
    : [
        decimal(Math.min(200, initialPresentation)),
        decimal(Math.min(200, Math.max(0, initialPresentation - 200))),
        decimal(Math.min(200, Math.max(0, initialPresentation - 400))),
    ]);

watch([accuracyValue, presentationValue], ([accuracy, presentation]) => {
    if (accuracyPenalty.value || accuracyParts.value) props.form.accuracy = accuracy === null ? '' : decimal(accuracy);
    if (presentationPenalty.value || presentationParts.value) props.form.presentation = presentation === null ? '' : decimal(presentation);
}, { immediate: true });
watch(directPresentationParts, parts => {
    if (!splitPresentation.value) return;
    props.form.presentation = parts.every(value => value !== '' && value !== null)
        ? decimal(parts.reduce((sum, value) => sum + Math.round(Number(value) * 100), 0))
        : '';
}, { deep: true, immediate: true });

const changePenalty = (field, delta) => {
    if (props.disabled) return;
    props.form[field] = adjustPenalties(props.form[field] ?? [], delta);
};
const undoPenalty = field => {
    const last = props.form[field]?.at(-1);
    if (last) changePenalty(field, Math.round(Number(last) * 100));
};
const changePart = (field, index, delta, maximum) => {
    if (props.disabled) return;
    const current = Math.round(Number(props.form[field][index] || 0) * 100);
    props.form[field][index] = decimal(Math.max(0, Math.min(maximum, current + delta)));
};
const changeDirectScore = (field, delta, maximum) => {
    if (props.disabled) return;
    const current = props.form[field] === '' ? 0 : Math.round(Number(props.form[field]) * 100);
    if (!Number.isFinite(current)) return;
    props.form[field] = decimal(Math.max(0, Math.min(maximum, current + delta)));
};
const changeDirectPresentationPart = (index, delta) => {
    if (props.disabled) return;
    const current = directPresentationParts.value[index] === '' ? 0 : Math.round(Number(directPresentationParts.value[index]) * 100);
    directPresentationParts.value[index] = decimal(Math.max(0, Math.min(200, current + delta)));
};
</script>

<template>
    <div class="score-controls" :class="{ paired }">
        <section class="score-criterion">
            <div class="criterion-title"><span>{{ method === 'single_score_v1' ? 'SCORE' : 'ACCURACY' }}</span><strong>{{ accuracyValue === null ? '—' : decimal(accuracyValue) }}</strong></div>
            <div v-if="accuracyPenalty" class="deduction-row">
                <button type="button" class="deduct-small" :disabled="disabled || accuracyValue < 10" aria-label="Deduct 0.1 point from accuracy" @click="changePenalty('accuracy_penalties', -10)">−0.1</button>
                <button type="button" class="deduct-large" :disabled="disabled || accuracyValue < 30" aria-label="Deduct 0.3 points from accuracy" @click="changePenalty('accuracy_penalties', -30)">−0.3</button>
                <button type="button" class="undo" :disabled="disabled || !form.accuracy_penalties?.length" aria-label="Undo last accuracy deduction" @click="undoPenalty('accuracy_penalties')">↶<small>{{ form.accuracy_penalties?.length ?? 0 }}</small></button>
            </div>
            <div v-else-if="accuracyParts" class="components">
                <label v-for="(_, index) in form.accuracy_components" :key="index" class="component" :for="`${inputPrefix}-accuracy-${index}`"><span>A{{ index + 1 }}</span><button type="button" :disabled="disabled" :aria-label="`Decrease accuracy component ${index + 1}`" @click.prevent="changePart('accuracy_components', index, -10, rules.accuracy_component_max ?? 200)">−</button><input :id="`${inputPrefix}-accuracy-${index}`" v-model="form.accuracy_components[index]" type="number" min="0" :max="(rules.accuracy_component_max ?? 200) / 100" step="0.01" inputmode="decimal" :disabled="disabled" required><button type="button" :disabled="disabled" :aria-label="`Increase accuracy component ${index + 1}`" @click.prevent="changePart('accuracy_components', index, 10, rules.accuracy_component_max ?? 200)">+</button></label>
            </div>
            <div v-else class="direct-stepper">
                <button type="button" class="step-down" :disabled="disabled || accuracyValue === 0" :aria-label="method === 'single_score_v1' ? 'Decrease total score by 0.1' : 'Decrease accuracy by 0.1'" @click="changeDirectScore('accuracy', -10, method === 'single_score_v1' ? 1000 : rules.accuracy_max)">−</button>
                <input v-model="form.accuracy" class="direct-score" type="number" min="0" :max="method === 'single_score_v1' ? 10 : rules.accuracy_max / 100" step="0.01" inputmode="decimal" :aria-label="method === 'single_score_v1' ? 'Total score' : 'Accuracy score'" :disabled="disabled" required>
                <button type="button" class="step-up" :disabled="disabled || accuracyValue >= (method === 'single_score_v1' ? 1000 : rules.accuracy_max)" :aria-label="method === 'single_score_v1' ? 'Increase total score by 0.1' : 'Increase accuracy by 0.1'" @click="changeDirectScore('accuracy', 10, method === 'single_score_v1' ? 1000 : rules.accuracy_max)">+</button>
            </div>
        </section>
        <section v-if="method !== 'single_score_v1'" class="score-criterion">
            <div class="criterion-title"><span>PRESENTATION</span><strong>{{ presentationValue === null ? '—' : decimal(presentationValue) }}</strong></div>
            <div v-if="presentationPenalty" class="deduction-row">
                <button type="button" class="deduct-small" :disabled="disabled || presentationValue < 10" aria-label="Deduct 0.1 point from presentation" @click="changePenalty('presentation_penalties', -10)">−0.1</button>
                <button type="button" class="deduct-large" :disabled="disabled || presentationValue < 30" aria-label="Deduct 0.3 points from presentation" @click="changePenalty('presentation_penalties', -30)">−0.3</button>
                <button type="button" class="undo" :disabled="disabled || !form.presentation_penalties?.length" aria-label="Undo last presentation deduction" @click="undoPenalty('presentation_penalties')">↶<small>{{ form.presentation_penalties?.length ?? 0 }}</small></button>
            </div>
            <div v-else-if="presentationParts" class="components">
                <label v-for="(_, index) in form.presentation_components" :key="index" class="component" :for="`${inputPrefix}-presentation-${index}`"><span>P{{ index + 1 }}</span><button type="button" :disabled="disabled" :aria-label="`Decrease presentation component ${index + 1}`" @click.prevent="changePart('presentation_components', index, -10, rules.presentation_component_max ?? 200)">−</button><input :id="`${inputPrefix}-presentation-${index}`" v-model="form.presentation_components[index]" type="number" min="0" :max="(rules.presentation_component_max ?? 200) / 100" step="0.01" inputmode="decimal" :disabled="disabled" required><button type="button" :disabled="disabled" :aria-label="`Increase presentation component ${index + 1}`" @click.prevent="changePart('presentation_components', index, 10, rules.presentation_component_max ?? 200)">+</button></label>
            </div>
            <div v-else-if="splitPresentation" class="components direct-presentation-parts">
                <label v-for="(_, index) in directPresentationParts" :key="index" class="component" :for="`${inputPrefix}-execution-${index}`">
                    <span>EXEC {{ index + 1 }}</span>
                    <button type="button" :disabled="disabled || Number(directPresentationParts[index]) <= 0" :aria-label="`Decrease execution component ${index + 1}`" @click.prevent="changeDirectPresentationPart(index, -10)">−</button>
                    <input :id="`${inputPrefix}-execution-${index}`" v-model="directPresentationParts[index]" type="number" min="0" max="2" step="0.01" inputmode="decimal" :disabled="disabled" required>
                    <button type="button" :disabled="disabled || Number(directPresentationParts[index]) >= 2" :aria-label="`Increase execution component ${index + 1}`" @click.prevent="changeDirectPresentationPart(index, 10)">+</button>
                </label>
            </div>
            <div v-else class="direct-stepper">
                <button type="button" class="step-down" :disabled="disabled || presentationValue === 0" aria-label="Decrease presentation by 0.1" @click="changeDirectScore('presentation', -10, rules.presentation_max)">−</button>
                <input v-model="form.presentation" class="direct-score" type="number" min="0" :max="rules.presentation_max / 100" step="0.01" inputmode="decimal" aria-label="Presentation score" :disabled="disabled" required>
                <button type="button" class="step-up" :disabled="disabled || presentationValue >= rules.presentation_max" aria-label="Increase presentation by 0.1" @click="changeDirectScore('presentation', 10, rules.presentation_max)">+</button>
            </div>
        </section>
        <div class="total-strip"><span>TOTAL</span><strong>{{ total }}</strong></div>
    </div>
</template>

<style scoped>
.score-controls{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;min-height:0;flex:1}.score-criterion{min-width:0;display:flex;flex-direction:column;gap:10px;padding:14px;border:1px solid #354862;border-radius:15px;background:#101d30}.criterion-title{display:flex;align-items:center;justify-content:space-between;gap:6px}.criterion-title span,.total-strip span{font-size:11px;font-weight:900;letter-spacing:.14em;color:#aab9ca}.criterion-title strong{font-size:clamp(28px,3.5vw,50px);line-height:1;font-variant-numeric:tabular-nums;color:#f8faff}.deduction-row{display:grid;grid-template-columns:1fr 1fr 58px;gap:8px;flex:1;min-height:0}.deduction-row button{min-height:68px;border:0;border-radius:12px;font-weight:950;touch-action:manipulation}.deduct-small{background:#f2c653;color:#17202b;font-size:clamp(25px,3vw,43px)}.deduct-large{background:#ef8068;color:#1b1d23;font-size:clamp(25px,3vw,43px)}.deduction-row .undo{background:#25364d;color:#eaf1fa;font-size:28px;display:flex;flex-direction:column;align-items:center;justify-content:center}.undo small{font-size:13px;line-height:1}.deduction-row button:active,.component button:active{transform:scale(.97)}button:disabled{opacity:.37}.components{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:7px;align-content:start;overflow:auto;min-height:0}.component{min-width:0;display:grid;grid-template-columns:1fr 40px minmax(48px,1.2fr) 40px;align-items:center;gap:4px;border:1px solid #34465d;border-radius:9px;background:#18283d;padding:5px}.component span{font-weight:900;font-size:11px;color:#adc4d6}.component button{height:45px;border:0;border-radius:7px;background:#2d425d;color:white;font-size:23px;touch-action:manipulation}.component input,.direct-score{min-width:0;width:100%;height:45px;text-align:center;border:1px solid #57728e;border-radius:7px;background:#081522;color:#fff;font-size:20px;font-weight:850;font-variant-numeric:tabular-nums}.direct-score{height:76px;font-size:38px}.total-strip{grid-column:1/-1;min-height:65px;display:flex;align-items:center;justify-content:space-between;padding:0 20px;border:1px solid #38566a;border-radius:12px;background:#102b3c}.total-strip strong{color:#f8cf61;font-size:clamp(30px,3.8vw,54px);font-variant-numeric:tabular-nums}.score-controls.paired{grid-template-columns:1fr;gap:7px}.paired .score-criterion{padding:9px 11px;gap:7px}.paired .criterion-title strong{font-size:clamp(25px,2.8vw,38px)}.paired .deduction-row{min-height:64px}.paired .deduction-row button{min-height:64px}.paired .components{grid-template-columns:repeat(3,minmax(0,1fr))}.paired .component{grid-template-columns:1fr 30px minmax(43px,1.2fr) 30px;gap:2px;padding:3px}.paired .component button,.paired .component input{height:40px}.paired .total-strip{grid-column:auto;min-height:46px;padding:0 12px}.paired .total-strip strong{font-size:31px}@media(max-width:900px){.components{grid-template-columns:repeat(2,minmax(0,1fr))}.paired .components{grid-template-columns:repeat(3,minmax(0,1fr))}.paired .component{grid-template-columns:1fr 28px minmax(35px,1fr) 28px}.paired .component span{font-size:9px}.paired .component input{font-size:16px}}@media(max-width:700px) and (orientation:landscape){.score-controls:not(.paired){grid-template-columns:repeat(2,minmax(0,1fr))}.paired .components{grid-template-columns:repeat(3,minmax(0,1fr))}.paired .component{grid-template-columns:1fr 25px minmax(30px,1fr) 25px}.paired .component button,.paired .component input{height:34px}.paired .deduction-row button{min-height:50px}.paired .score-criterion{padding:6px}.paired .total-strip{min-height:36px}}@media(max-width:650px) and (orientation:portrait){.score-controls{grid-template-columns:1fr}.total-strip{grid-column:auto}.components,.paired .components{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:900px) and (orientation:landscape) and (max-height:500px){.score-controls{gap:4px}.score-criterion{padding:5px 7px;gap:4px;border-radius:8px}.criterion-title span,.total-strip span{font-size:9px}.criterion-title strong{font-size:24px}.deduction-row{grid-template-columns:1fr 1fr 40px;gap:4px}.deduction-row button{min-height:54px;border-radius:7px;font-size:19px}.deduction-row .undo{font-size:20px}.undo small{font-size:9px}.components{grid-template-columns:repeat(3,minmax(0,1fr));gap:3px;overflow:visible}.component{display:flex;flex-direction:column;justify-content:center;gap:2px;padding:3px}.component span{align-self:flex-start;font-size:9px}.component button{display:none}.component input{height:33px;font-size:16px}.total-strip{min-height:36px;padding:0 9px}.total-strip strong{font-size:25px}.score-controls.paired{grid-template-columns:repeat(2,minmax(0,1fr));gap:4px}.paired .score-criterion{padding:4px 5px;gap:3px}.paired .criterion-title strong{font-size:19px}.paired .criterion-title span{font-size:8px;letter-spacing:.04em}.paired .deduction-row{grid-template-columns:repeat(3,minmax(0,1fr));gap:3px;min-height:50px}.paired .deduction-row button{min-height:50px;font-size:16px}.paired .components{gap:2px}.paired .component{padding:2px}.paired .component input{height:32px;font-size:14px}.paired .total-strip{grid-column:1/-1;min-height:30px}.paired .total-strip strong{font-size:21px}}
.direct-stepper{display:grid;grid-template-columns:minmax(68px,.65fr) minmax(100px,1.4fr) minmax(68px,.65fr);gap:8px;flex:1;align-items:stretch}.direct-stepper .direct-score{height:auto;min-height:76px}.direct-stepper button{min-height:76px;border:0;border-radius:12px;color:#fff;font-size:42px;font-weight:950;touch-action:manipulation}.direct-stepper .step-down{background:#344962}.direct-stepper .step-up{background:#168766}.direct-stepper button:active{transform:scale(.97)}.paired .direct-stepper{grid-template-columns:58px minmax(80px,1fr) 58px;gap:5px}.paired .direct-stepper button,.paired .direct-stepper .direct-score{min-height:56px}.score-criterion:focus-within{border-color:#6f91ad;box-shadow:0 0 0 3px #4bb5da18}.component:focus-within{border-color:#5f91b2}.direct-score:focus,.component input:focus{outline:2px solid #e9c95d;outline-offset:1px}@media(max-width:700px) and (orientation:landscape){.paired .direct-stepper{grid-template-columns:44px minmax(64px,1fr) 44px}.paired .direct-stepper button,.paired .direct-stepper .direct-score{min-height:44px;font-size:24px}}@media(max-width:900px) and (orientation:landscape) and (max-height:500px){.component{display:grid;grid-template-columns:1fr 28px minmax(38px,1fr) 28px}.component button{display:block;height:32px}.direct-stepper button,.direct-stepper .direct-score{min-height:52px}.paired .direct-stepper button,.paired .direct-stepper .direct-score{min-height:42px}}
.direct-presentation-parts,.paired .direct-presentation-parts{grid-template-columns:repeat(3,minmax(0,1fr));gap:5px;overflow:visible}.direct-presentation-parts .component,.paired .direct-presentation-parts .component{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:4px;padding:5px}.direct-presentation-parts .component span{grid-column:1/-1}.direct-presentation-parts .component input,.paired .direct-presentation-parts .component input{grid-column:1/-1;grid-row:2;width:100%;height:42px;font-size:18px}.direct-presentation-parts .component button,.paired .direct-presentation-parts .component button{display:block;width:100%;height:34px;padding:0;font-size:20px}.direct-presentation-parts .component button:first-of-type{grid-column:1;grid-row:3}.direct-presentation-parts .component button:last-of-type{grid-column:2;grid-row:3;background:#168766}@media(max-width:700px) and (orientation:landscape){.direct-presentation-parts .component,.paired .direct-presentation-parts .component{padding:3px}.direct-presentation-parts .component input,.paired .direct-presentation-parts .component input{height:34px}.direct-presentation-parts .component button,.paired .direct-presentation-parts .component button{height:29px}}
@media(max-width:900px) and (orientation:landscape) and (max-height:500px){.score-controls.paired{grid-template-columns:1fr;gap:3px}.paired .total-strip{grid-column:auto}.paired .score-criterion{min-height:0}.paired .direct-presentation-parts{grid-template-columns:repeat(3,minmax(0,1fr));gap:3px}.paired .direct-presentation-parts .component{grid-template-columns:repeat(2,minmax(0,1fr));gap:2px;padding:2px}.paired .direct-presentation-parts .component span{font-size:8px}.paired .direct-presentation-parts .component input{grid-column:1/-1;grid-row:2;height:28px;font-size:13px}.paired .direct-presentation-parts .component button{height:25px;font-size:15px}.paired .direct-presentation-parts .component button:first-of-type{grid-column:1;grid-row:3}.paired .direct-presentation-parts .component button:last-of-type{grid-column:2;grid-row:3}}
.score-controls.paired{overflow-y:auto;overflow-x:hidden;overscroll-behavior:contain;scrollbar-width:thin;scrollbar-color:#526b83 transparent;padding-right:2px}
</style>
