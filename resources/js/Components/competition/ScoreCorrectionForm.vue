<script setup>
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import FormErrors from '../../Shared/Components/FormErrors.vue';
import ScoreEntryFields from './ScoreEntryFields.vue';
import { requestId } from '../../Shared/Services/requestId';
import { scoreComponentsComplete, scoreDetailFields, scorePayload } from '../../scoring';

const props = defineProps({
    performance: Object,
    score: Object,
    rules: Object,
    endpoint: String,
});
const form = useForm({
    request_id: requestId(),
    judge_assignment_id: props.score.judge_assignment_id,
    expected_version: props.performance.version,
    expected_revision: props.score.revision,
    accuracy: (props.score.values.accuracy / 100).toFixed(2),
    presentation: (props.score.values.presentation / 100).toFixed(2),
    ...scoreDetailFields(props.rules.input_method, props.score.breakdown),
    reason: '',
});
const canSubmit = computed(() => scoreComponentsComplete(form) && form.reason.trim().length >= 10);
watch(() => props.performance.version, version => {
    form.expected_version = version;
});
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
    <!-- Keep score corrections styled with Tailwind utilities. -->
    <details
        class="my-3 rounded-lg border border-slate-200 p-4 [&_summary]:cursor-pointer [&_summary]:font-semibold [&_label]:mb-1 [&_label]:block [&_label]:text-xs [&_label]:font-semibold [&_label]:text-slate-700 [&_textarea]:mb-3 [&_textarea]:w-full [&_textarea]:rounded-lg [&_textarea]:border [&_textarea]:border-slate-300 [&_textarea]:bg-white [&_textarea]:px-3 [&_textarea]:py-2.5"
    >
        <summary>اصلاح نمرهٔ صندلی {{ score.seat }} با دلیل</summary>
        <form @submit.prevent="submit">
            <p class="my-3 text-xs leading-6 text-[#8b988a]">نمرهٔ قبلی، نام اپراتور و دلیل در تاریخچه حفظ می‌شوند. نتیجهٔ تأییدشده پس از اصلاح دوباره محاسبه و منتشر می‌شود.</p>
            <ScoreEntryFields :form="form" :rules="rules" :id-prefix="`correction-${performance.id}-${score.id}`" :disabled="form.processing" :reset-key="form.request_id" />
            <label :for="`correction-reason-${score.id}`">دلیل اصلاح نمره</label>
            <textarea :id="`correction-reason-${score.id}`" v-model="form.reason" required minlength="10" maxlength="500"></textarea>
            <FormErrors :errors="form.errors" />
            <div class="flex flex-wrap items-center gap-2.5">
                <button class="inline-flex min-h-11 items-center justify-center rounded-lg border border-transparent bg-[#286957] px-5 py-2.5 text-sm font-semibold text-white disabled:cursor-wait disabled:opacity-55" :disabled="form.processing || !canSubmit">ثبت نهایی اصلاح و دلیل</button>
                <button v-if="score.revision !== form.expected_revision" type="button" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-transparent bg-[#286957] px-5 py-2.5 text-sm font-semibold text-white disabled:cursor-wait disabled:opacity-55 !bg-[#e8eee7] !text-[#42664f]" @click="reloadScore">دریافت نسخهٔ جدید نمره</button>
            </div>
        </form>
    </details>
</template>
