<script setup>
import { computed, ref, watch } from 'vue';

const props = defineProps({ form: Object, rules: Object, idPrefix: String, disabled: Boolean, resetKey: String });
const step = ref('accuracy');
const detailed = computed(() => props.rules.input_method === 'deductions_and_components_v1' && Array.isArray(props.form.accuracy_penalties) && Array.isArray(props.form.presentation_components));
const accuracyHundredths = computed(() => props.rules.accuracy_max - (props.form.accuracy_penalties || []).reduce((total, penalty) => total + Math.round(Number(penalty) * 100), 0));
const presentationHundredths = computed(() => (props.form.presentation_components || []).length === 3 && props.form.presentation_components.every(value => value !== '' && value !== null)
    ? props.form.presentation_components.reduce((total, value) => total + Math.round(Number(value) * 100), 0)
    : null);
const decimal = value => (value / 100).toFixed(2);
watch(() => [props.form.accuracy_penalties, props.form.presentation_components], () => {
    if (detailed.value) {
        props.form.accuracy = decimal(accuracyHundredths.value);
        props.form.presentation = presentationHundredths.value === null ? '' : decimal(presentationHundredths.value);
    }
}, { deep: true, immediate: true });
watch(() => props.resetKey, () => step.value = 'accuracy');
const addPenalty = penalty => {
    if (props.disabled || accuracyHundredths.value < Math.round(Number(penalty) * 100)) return;
    props.form.accuracy_penalties = [...props.form.accuracy_penalties, penalty];
};
const removePenalty = index => {
    if (props.disabled) return;
    props.form.accuracy_penalties = props.form.accuracy_penalties.filter((_, position) => position !== index);
};
</script>

<template>
    <div>
    <div v-if="rules.input_method === 'single_score_v1'" class="score-inputs"><div><label :for="`${idPrefix}-score`">نمرهٔ کل · از ۱۰</label><input :id="`${idPrefix}-score`" v-model="form.accuracy" type="number" :disabled="disabled" inputmode="decimal" min="0" max="10" step="0.01" required dir="ltr"></div></div>
    <div v-else-if="detailed" class="score-detail">
        <div class="score-detail-heading"><b>{{ step === 'accuracy' ? '۱. دقت' : '۲. اجرا' }}</b><span>دقت {{ decimal(accuracyHundredths) }} از ۴ · اجرا {{ presentationHundredths === null ? '—' : decimal(presentationHundredths) }} از ۶</span></div>
        <div v-if="step === 'accuracy'">
            <p>امتیاز پایه ۴٫۰۰ است. هر خطا را با دکمهٔ کسر ثبت کنید.</p>
            <div class="row-actions"><button type="button" class="button secondary" :disabled="disabled || accuracyHundredths < 10" @click="addPenalty('0.10')">کسر ۰٫۱</button><button type="button" class="button secondary" :disabled="disabled || accuracyHundredths < 30" @click="addPenalty('0.30')">کسر ۰٫۳</button></div>
            <div v-if="form.accuracy_penalties.length" class="score-penalties"><button v-for="(penalty,index) in form.accuracy_penalties" :key="index" type="button" :disabled="disabled" :aria-label="`حذف کسر ${penalty}`" @click="removePenalty(index)">−{{ penalty }} ×</button></div>
            <p>نمرهٔ دقت: <strong>{{ decimal(accuracyHundredths) }}</strong></p>
            <button type="button" class="button" :disabled="disabled" @click="step = 'presentation'">ثبت دقت و ورود به اجرا</button>
        </div>
        <div v-else>
            <p>سه مؤلفهٔ اجرا را جداگانه از ۰ تا ۲ وارد کنید.</p>
            <div class="score-inputs compact"><div v-for="index in 3" :key="index"><label :for="`${idPrefix}-presentation-${index}`">مؤلفهٔ {{ index }} · از ۲</label><input :id="`${idPrefix}-presentation-${index}`" v-model="form.presentation_components[index - 1]" type="number" inputmode="decimal" min="0" max="2" step="0.01" :disabled="disabled" required dir="ltr"></div></div>
            <p>جمع اجرا: <strong>{{ presentationHundredths === null ? '—' : decimal(presentationHundredths) }}</strong></p>
            <button type="button" class="button secondary" :disabled="disabled" @click="step = 'accuracy'">بازگشت به دقت</button>
        </div>
    </div>
    <div v-else class="score-inputs">
        <div><label :for="`${idPrefix}-accuracy`">دقت · از {{ rules.accuracy_max / 100 }}</label><input :id="`${idPrefix}-accuracy`" v-model="form.accuracy" type="number" :disabled="disabled" inputmode="decimal" min="0" :max="rules.accuracy_max / 100" step="0.01" required dir="ltr"></div>
        <div><label :for="`${idPrefix}-presentation`">اجرا · از {{ rules.presentation_max / 100 }}</label><input :id="`${idPrefix}-presentation`" v-model="form.presentation" type="number" :disabled="disabled" inputmode="decimal" min="0" :max="rules.presentation_max / 100" step="0.01" required dir="ltr"></div>
    </div>
    </div>
</template>
