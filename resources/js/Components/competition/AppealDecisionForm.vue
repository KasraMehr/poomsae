<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import FormErrors from '../../Shared/Components/FormErrors.vue';
import ScoreEntryFields from './ScoreEntryFields.vue';
import { scoreComponentsComplete, scoreDetailFields, scorePayload } from '../../scoring';

const props = defineProps({
    appeal: Object,
    endpoint: String,
});
const rules = computed(() => props.appeal.current.rules);
const corrections = reactive(props.appeal.current.scores.map(score => ({
    selected: false,
    judge_assignment_id: score.judge_assignment_id,
    expected_revision: score.revision,
    seat: score.seat,
    accuracy: (score.values.accuracy / 100).toFixed(2),
    presentation: (score.values.presentation / 100).toFixed(2),
    ...scoreDetailFields(rules.value.input_method, score.breakdown),
})));
const form = useForm({
    expected_version: props.appeal.version,
    decision: 'rejected',
    decision_reason: '',
});
const selected = computed(() => corrections.filter(input => input.selected));
const ready = computed(() => form.decision_reason.trim().length >= 10 && (
    form.decision === 'rejected' || (
        selected.value.length > 0 && selected.value.every(scoreComponentsComplete)
    )
));
const submit = () => form.transform(data => {
    const payload = { ...data };
    if (data.decision === 'accepted') {
        payload.corrections = selected.value.map(input => {
            const { selected, seat, ...fields } = input;
            return scorePayload(fields, rules.value.input_method);
        });
    }
    return payload;
}).post(props.endpoint, { preserveScroll: true });
</script>
<template>
    <!-- Keep appeal scoring controls styled with Tailwind utilities. -->
    <form class="grid gap-4 rounded-xl border border-[#e2e9df] bg-white p-5 min-[761px]:p-[27px] [&_h3]:mb-3 [&_h3]:font-semibold [&_h3]:text-[#315747] [&_label]:mb-1 [&_label]:block [&_label]:text-xs [&_label]:font-semibold [&_label]:text-[#42664f] [&_select]:w-full [&_select]:rounded-lg [&_select]:border [&_select]:border-[#cfd9cc] [&_select]:bg-white [&_select]:px-3 [&_select]:py-2.5 [&_textarea]:w-full [&_textarea]:rounded-lg [&_textarea]:border [&_textarea]:border-[#cfd9cc] [&_textarea]:bg-white [&_textarea]:px-3 [&_textarea]:py-2.5 [&_button]:cursor-pointer" @submit.prevent="submit">
        <h3>ثبت رأی اعتراض</h3>
        <label :for="'appeal-decision-' + appeal.id">رأی</label><select :id="'appeal-decision-' + appeal.id" v-model="form.decision"><option value="rejected">رد اعتراض و حفظ نتیجه</option><option value="accepted">پذیرش اعتراض و تأیید اصلاح مشخص نمره</option></select>
        <div v-if="form.decision === 'accepted'">
            <p class="my-3 text-xs leading-6 text-[#8b988a]">صندلی‌های نیازمند اصلاح را انتخاب و نمرهٔ صحیح هرکدام را مشخص کنید. رأی، مجوز اعمال همین مقادیر خواهد بود.</p>
            <div v-for="input in corrections" :key="input.judge_assignment_id" class="rounded-lg border border-slate-200 p-4">
                <label class="!flex cursor-pointer items-center gap-[9px] text-xs"><input v-model="input.selected" type="checkbox">اصلاح صندلی {{ input.seat }} · ویرایش فعلی {{ input.expected_revision }}</label>
                <ScoreEntryFields v-if="input.selected" :form="input" :rules="rules" :id-prefix="'appeal-' + appeal.id + '-seat-' + input.seat" :disabled="form.processing"/>
            </div>
        </div>
        <label :for="'appeal-decision-reason-' + appeal.id">شرح بررسی و دلیل رأی</label><textarea :id="'appeal-decision-reason-' + appeal.id" v-model="form.decision_reason" required minlength="10" maxlength="2000"></textarea>
        <FormErrors :errors="form.errors"/>
        <button class="inline-flex min-h-11 items-center justify-center rounded-lg border border-transparent bg-[#286957] px-5 py-2.5 text-sm font-semibold text-white disabled:cursor-wait disabled:opacity-55" :disabled="form.processing || !ready">ثبت نهایی رأی اعتراض</button>
    </form>
</template>
