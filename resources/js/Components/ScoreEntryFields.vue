<script setup>
import { computed, ref, watch } from 'vue';
import { adjustPenalties, hasScoreDetails } from '../scoring';

const props = defineProps({ form: Object, rules: Object, idPrefix: String, disabled: Boolean, resetKey: String });
const step = ref('accuracy');
const freestyle = computed(() => props.rules.input_method === 'components_and_deductions_v1');
const detailed = computed(() => ['deductions_and_components_v1', 'components_and_deductions_v1'].includes(props.rules.input_method) && hasScoreDetails(props.form, props.rules.input_method));
const penaltyField = computed(() => freestyle.value ? 'presentation_penalties' : 'accuracy_penalties');
const componentField = computed(() => freestyle.value ? 'accuracy_components' : 'presentation_components');
const penaltyCriterion = computed(() => freestyle.value ? 'presentation' : 'accuracy');
const componentCriterion = computed(() => freestyle.value ? 'accuracy' : 'presentation');
const criterionLabels = { accuracy: 'دقت', presentation: 'اجرا' };
const deductedHundredths = computed(() => 400 - (props.form[penaltyField.value] || []).reduce((total, penalty) => total + Math.round(Number(penalty) * 100), 0));
const componentsHundredths = computed(() => (props.form[componentField.value] || []).length === 3 && props.form[componentField.value].every(value => value !== '' && value !== null)
    ? props.form[componentField.value].reduce((total, value) => total + Math.round(Number(value) * 100), 0)
    : null);
const accuracyHundredths = computed(() => freestyle.value ? componentsHundredths.value : deductedHundredths.value);
const presentationHundredths = computed(() => freestyle.value ? deductedHundredths.value : componentsHundredths.value);
const decimal = value => (value / 100).toFixed(2);
watch(() => [props.form[penaltyField.value], props.form[componentField.value]], () => {
    if (detailed.value) {
        props.form.accuracy = accuracyHundredths.value === null ? '' : decimal(accuracyHundredths.value);
        props.form.presentation = presentationHundredths.value === null ? '' : decimal(presentationHundredths.value);
    }
}, { deep: true, immediate: true });
watch(() => props.resetKey, () => step.value = 'accuracy');
const changeDeductedScore = amount => {
    if (props.disabled) return;
    props.form[penaltyField.value] = adjustPenalties(props.form[penaltyField.value], amount);
};
const removePenalty = index => {
    if (props.disabled) return;
    props.form[penaltyField.value] = props.form[penaltyField.value].filter((_, position) => position !== index);
};
const changeComponent = (index, amount) => {
    if (props.disabled) return;
    const current = Math.round(Number(props.form[componentField.value][index]) * 100);
    const next = current + amount;
    if (!Number.isFinite(next) || next < 0 || next > 200) return;
    props.form[componentField.value][index] = decimal(next);
};
</script>

<template>
    <div>
    <div v-if="rules.input_method === 'single_score_v1'" class="score-inputs"><div><label :for="`${idPrefix}-score`">نمرهٔ کل · از ۱۰</label><input :id="`${idPrefix}-score`" v-model="form.accuracy" type="number" :disabled="disabled" inputmode="decimal" min="0" max="10" step="0.01" required dir="ltr"></div></div>
    <div v-else-if="detailed" class="score-detail">
        <div class="score-detail-heading"><b>{{ step === 'accuracy' ? '۱. دقت' : '۲. اجرا' }}</b><span>دقت {{ accuracyHundredths === null ? '—' : decimal(accuracyHundredths) }} از {{ rules.accuracy_max / 100 }} · اجرا {{ presentationHundredths === null ? '—' : decimal(presentationHundredths) }} از {{ rules.presentation_max / 100 }}</span></div>
        <div v-if="step === penaltyCriterion">
            <p>امتیاز پایهٔ {{ criterionLabels[penaltyCriterion] }} ۴٫۰۰ است؛ با دکمه‌ها بین صفر و چهار تغییر دهید.</p>
            <div class="row-actions"><button v-for="amount in [-10, -30, 10, 30]" :key="amount" type="button" class="button secondary" :disabled="disabled || deductedHundredths + amount < 0 || deductedHundredths + amount > 400" @click="changeDeductedScore(amount)">{{ amount > 0 ? 'افزایش' : 'کاهش' }} {{ Math.abs(amount) === 10 ? '۰٫۱' : '۰٫۳' }}</button></div>
            <div v-if="form[penaltyField].length" class="score-penalties"><button v-for="(penalty,index) in form[penaltyField]" :key="index" type="button" :disabled="disabled" :aria-label="`برگرداندن کسر ${penalty}`" @click="removePenalty(index)">−{{ penalty }} ×</button></div>
            <p>نمرهٔ {{ criterionLabels[penaltyCriterion] }}: <strong>{{ decimal(deductedHundredths) }}</strong></p>
        </div>
        <div v-else>
            <p>سه مؤلفهٔ {{ criterionLabels[componentCriterion] }} را جداگانه از ۰ تا ۲ وارد کنید.</p>
            <div class="score-inputs compact"><div v-for="index in 3" :key="index">
                <label :for="`${idPrefix}-${componentCriterion}-${index}`">مؤلفهٔ {{ index }} · از ۲</label><input :id="`${idPrefix}-${componentCriterion}-${index}`" v-model="form[componentField][index - 1]" type="number" inputmode="decimal" min="0" max="2" step="0.01" :disabled="disabled" required dir="ltr">
                <div class="row-actions"><button v-for="amount in [-10, -30, 10, 30]" :key="amount" type="button" class="button secondary small" :disabled="disabled || Number(form[componentField][index - 1]) * 100 + amount < 0 || Number(form[componentField][index - 1]) * 100 + amount > 200" :aria-label="`${amount > 0 ? 'افزایش' : 'کاهش'} ${Math.abs(amount) / 100} مؤلفهٔ ${index}`" @click="changeComponent(index - 1, amount)">{{ amount > 0 ? '+' : '−' }}{{ Math.abs(amount) === 10 ? '۰٫۱' : '۰٫۳' }}</button></div>
            </div></div>
            <p>جمع {{ criterionLabels[componentCriterion] }}: <strong>{{ componentsHundredths === null ? '—' : decimal(componentsHundredths) }}</strong></p>
        </div>
        <button v-if="step === 'accuracy'" type="button" class="button" :disabled="disabled" @click="step = 'presentation'">ثبت دقت و ورود به اجرا</button>
        <button v-else type="button" class="button secondary" :disabled="disabled" @click="step = 'accuracy'">بازگشت به دقت</button>
    </div>
    <div v-else class="score-inputs">
        <div><label :for="`${idPrefix}-accuracy`">دقت · از {{ rules.accuracy_max / 100 }}</label><input :id="`${idPrefix}-accuracy`" v-model="form.accuracy" type="number" :disabled="disabled" inputmode="decimal" min="0" :max="rules.accuracy_max / 100" step="0.01" required dir="ltr"></div>
        <div><label :for="`${idPrefix}-presentation`">اجرا · از {{ rules.presentation_max / 100 }}</label><input :id="`${idPrefix}-presentation`" v-model="form.presentation" type="number" :disabled="disabled" inputmode="decimal" min="0" :max="rules.presentation_max / 100" step="0.01" required dir="ltr"></div>
    </div>
    </div>
</template>
