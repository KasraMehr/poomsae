<script setup>
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { requestId } from '../requestId';
import FormErrors from './FormErrors.vue';
import ScoreEntryFields from './ScoreEntryFields.vue';
import { scoreComponentsComplete, scoreDetailFields, scorePayload } from '../scoring';

const props = defineProps({ performance: Object, rules: Object, endpoint: String });
const missingJudges = computed(() => props.performance.judges.filter(judge => props.performance.missing_seats.includes(judge.seat)));
const form = useForm({
    request_id: requestId(), judge_assignment_id: '', expected_version: props.performance.version,
    expected_revision: 0, accuracy: '', presentation: '',
    ...scoreDetailFields(props.rules.input_method),
    reason: '',
});
const canSubmit = computed(() => scoreComponentsComplete(form));
watch(() => props.performance.version, version => form.expected_version = version);
const submit = () => form.transform(data => scorePayload(data, props.rules.input_method)).post(props.endpoint, {
    preserveScroll: true,
    onSuccess: () => {
        form.reset('judge_assignment_id', 'accuracy', 'presentation', 'accuracy_penalties', 'presentation_components', 'presentation_penalties', 'accuracy_components', 'reason');
        form.request_id = requestId();
        form.expected_revision = 0;
        form.expected_version = props.performance.version;
    },
});
</script>

<template>
    <details v-if="missingJudges.length" class="proxy-score">
        <summary>ثبت اضطراری به‌جای داور قطع‌شده</summary>
        <form @submit.prevent="submit">
            <div class="proxy-warning"><b>ثبت با مسئولیت اپراتور</b><span>نام اپراتور، صندلی داور، دلیل و نمره در سابقهٔ غیرقابل حذف مسابقه ذخیره می‌شود.</span></div>
            <label :for="`proxy-seat-${performance.id}`">صندلی قطع‌شده</label>
            <select :id="`proxy-seat-${performance.id}`" v-model.number="form.judge_assignment_id" required>
                <option value="" disabled>انتخاب صندلی داور</option>
                <option v-for="judge in missingJudges" :key="judge.id" :value="judge.id">صندلی {{ judge.seat }} · {{ judge.name }}</option>
            </select>
            <ScoreEntryFields :form="form" :rules="rules" :id-prefix="`proxy-${performance.id}`" :disabled="form.processing" :reset-key="form.request_id" />
            <label :for="`proxy-reason-${performance.id}`">دلیل و شرح قطعی ارتباط</label>
            <textarea :id="`proxy-reason-${performance.id}`" v-model="form.reason" required minlength="10" maxlength="500" placeholder="مثال: تبلت صندلی ۳ از شبکه خارج شد و سرداور ثبت جایگزین را تأیید کرد."></textarea>
            <FormErrors :errors="form.errors" />
            <button class="button danger" :disabled="form.processing || !canSubmit">ثبت نمرهٔ جایگزین</button>
        </form>
    </details>
</template>
