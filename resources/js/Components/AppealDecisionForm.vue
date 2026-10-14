<script setup>
import { computed, reactive } from 'vue';
import { useForm } from '@inertiajs/vue3';
import FormErrors from './FormErrors.vue';
import ScoreEntryFields from './ScoreEntryFields.vue';
import { scoreComponentsComplete, scoreDetailFields, scorePayload } from '../scoring';
const props = defineProps({ appeal: Object, endpoint: String });
const rules = computed(() => props.appeal.current.rules);
const corrections = reactive(props.appeal.current.scores.map(score => ({
    selected: false, judge_assignment_id: score.judge_assignment_id, expected_revision: score.revision,
    seat: score.seat, accuracy: (score.values.accuracy / 100).toFixed(2), presentation: (score.values.presentation / 100).toFixed(2),
    ...scoreDetailFields(rules.value.input_method, score.breakdown),
})));
const form = useForm({ expected_version: props.appeal.version, decision: 'rejected', decision_reason: '' });
const selected = computed(() => corrections.filter(input => input.selected));
const ready = computed(() => form.decision_reason.trim().length >= 10 && (form.decision === 'rejected' || selected.value.length > 0 && selected.value.every(scoreComponentsComplete)));
const submit = () => form.transform(data => {
    const payload = { ...data };
    if (data.decision === 'accepted') payload.corrections = selected.value.map(input => {
        const { selected, seat, ...fields } = input;
        return scorePayload(fields, rules.value.input_method);
    });
    return payload;
}).post(props.endpoint, { preserveScroll: true });
</script>
<template>
    <form class="panel" @submit.prevent="submit">
        <h3>ثبت رأی ناظر</h3>
        <label :for="'appeal-decision-' + appeal.id">رأی</label><select :id="'appeal-decision-' + appeal.id" v-model="form.decision"><option value="rejected">رد اعتراض و حفظ نتیجه</option><option value="accepted">پذیرش اعتراض و تأیید اصلاح مشخص نمره</option></select>
        <div v-if="form.decision === 'accepted'">
            <p class="subtle">صندلی‌های نیازمند اصلاح را انتخاب و نمرهٔ صحیح هرکدام را مشخص کنید. رأی، مجوز اعمال همین مقادیر خواهد بود.</p>
            <div v-for="input in corrections" :key="input.judge_assignment_id" class="panel">
                <label class="check"><input v-model="input.selected" type="checkbox">اصلاح صندلی {{ input.seat }} · ویرایش فعلی {{ input.expected_revision }}</label>
                <ScoreEntryFields v-if="input.selected" :form="input" :rules="rules" :id-prefix="'appeal-' + appeal.id + '-seat-' + input.seat" :disabled="form.processing"/>
            </div>
        </div>
        <label :for="'appeal-decision-reason-' + appeal.id">شرح بررسی و دلیل رأی</label><textarea :id="'appeal-decision-reason-' + appeal.id" v-model="form.decision_reason" required minlength="10" maxlength="2000"></textarea>
        <FormErrors :errors="form.errors"/>
        <button class="button" :disabled="form.processing || !ready">ثبت نهایی رأی ناظر</button>
    </form>
</template>
