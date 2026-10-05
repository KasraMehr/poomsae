<script setup>
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import FormErrors from './FormErrors.vue';
import ScoreEntryFields from './ScoreEntryFields.vue';
import { requestId } from '../requestId';
import { scoreComponentsComplete, scoreDetailFields, scorePayload } from '../scoring';

const props = defineProps({ performance: Object, score: Object, rules: Object, endpoint: String });
const form = useForm({
    request_id: requestId(), judge_assignment_id: props.score.judge_assignment_id,
    expected_version: props.performance.version, expected_revision: props.score.revision,
    accuracy: (props.score.values.accuracy / 100).toFixed(2),
    presentation: (props.score.values.presentation / 100).toFixed(2),
    ...scoreDetailFields(props.rules.input_method, props.score.breakdown), reason: '',
});
const canSubmit = computed(() => scoreComponentsComplete(form) && form.reason.trim().length >= 10);
watch(() => props.performance.version, version => form.expected_version = version);
const submit = () => form.transform(data => scorePayload(data, props.rules.input_method)).post(props.endpoint, {
    preserveScroll: true,
    onSuccess: () => {
        form.request_id = requestId();
        form.expected_revision = props.score.revision;
        form.expected_version = props.performance.version;
        form.reason = '';
    },
});
const reloadScore = () => {
    form.accuracy = (props.score.values.accuracy / 100).toFixed(2);
    form.presentation = (props.score.values.presentation / 100).toFixed(2);
    Object.assign(form, scoreDetailFields(props.rules.input_method, props.score.breakdown));
    form.expected_revision = props.score.revision;
    form.expected_version = props.performance.version;
    form.request_id = requestId();
    form.reason = '';
    form.clearErrors();
};
</script>

<template>
    <details class="proxy-score">
        <summary>اصلاح نمرهٔ صندلی {{ score.seat }} با دلیل</summary>
        <form @submit.prevent="submit">
            <p class="subtle">نمرهٔ قبلی، نام اپراتور و دلیل در تاریخچه حفظ می‌شوند. نتیجهٔ تأییدشده پس از اصلاح دوباره محاسبه و منتشر می‌شود.</p>
            <ScoreEntryFields :form="form" :rules="rules" :id-prefix="`correction-${performance.id}-${score.id}`" :disabled="form.processing" :reset-key="form.request_id" />
            <label :for="`correction-reason-${score.id}`">دلیل اصلاح نمره</label>
            <textarea :id="`correction-reason-${score.id}`" v-model="form.reason" required minlength="10" maxlength="500"></textarea>
            <FormErrors :errors="form.errors" />
            <div class="row-actions">
                <button class="button" :disabled="form.processing || !canSubmit">ثبت نهایی اصلاح و دلیل</button>
                <button v-if="score.revision !== form.expected_revision" type="button" class="button secondary" @click="reloadScore">دریافت نسخهٔ جدید نمره</button>
            </div>
        </form>
    </details>
</template>
