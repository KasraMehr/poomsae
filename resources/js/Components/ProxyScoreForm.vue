<script setup>
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { requestId } from '../requestId';
import FormErrors from './FormErrors.vue';

const props = defineProps({ performance: Object, rules: Object, endpoint: String });
const missingJudges = computed(() => props.performance.judges.filter(judge => props.performance.missing_seats.includes(judge.seat)));
const form = useForm({
    request_id: requestId(), judge_assignment_id: '', expected_version: props.performance.version,
    expected_revision: 0, accuracy: '', presentation: '', reason: '',
});
watch(() => props.performance.version, version => form.expected_version = version);
const submit = () => form.transform(data => ({ ...data, accuracy: String(data.accuracy), presentation: String(data.presentation) })).post(props.endpoint, {
    preserveScroll: true,
    onSuccess: () => {
        form.reset('judge_assignment_id', 'accuracy', 'presentation', 'reason');
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
            <div class="score-inputs compact">
                <div><label :for="`proxy-accuracy-${performance.id}`">دقت · از {{ rules.accuracy_max / 100 }}</label><input :id="`proxy-accuracy-${performance.id}`" v-model="form.accuracy" type="number" min="0" :max="rules.accuracy_max / 100" step="0.01" inputmode="decimal" required dir="ltr"></div>
                <div><label :for="`proxy-presentation-${performance.id}`">اجرا · از {{ rules.presentation_max / 100 }}</label><input :id="`proxy-presentation-${performance.id}`" v-model="form.presentation" type="number" min="0" :max="rules.presentation_max / 100" step="0.01" inputmode="decimal" required dir="ltr"></div>
            </div>
            <label :for="`proxy-reason-${performance.id}`">دلیل و شرح قطعی ارتباط</label>
            <textarea :id="`proxy-reason-${performance.id}`" v-model="form.reason" required minlength="10" maxlength="500" placeholder="مثال: تبلت صندلی ۳ از شبکه خارج شد و سرداور ثبت جایگزین را تأیید کرد."></textarea>
            <FormErrors :errors="form.errors" />
            <button class="button danger" :disabled="form.processing">ثبت نمرهٔ جایگزین</button>
        </form>
    </details>
</template>
