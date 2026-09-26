<script setup>
import { ref, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import FormErrors from './FormErrors.vue';
import { requestId } from '../requestId';
const props = defineProps({ performance: Object, rules: Object, endpoint: String });
const page = usePage();
const key = 'poomsae-score:' + page.props.auth.user.id + ':' + props.performance.id;
let cached;
try { cached = JSON.parse(localStorage.getItem(key) || 'null'); } catch { cached = null; }
const saved = ref(false);
let suppressDraft = false;
const initial = {
    request_id: requestId(), expected_version: props.performance.version, expected_revision: props.performance.own_score?.revision || 0,
    accuracy: props.performance.own_score ? (props.performance.own_score.values.accuracy / 100).toFixed(2) : '',
    presentation: props.performance.own_score ? (props.performance.own_score.values.presentation / 100).toFixed(2) : '', reason: '',
};
const form = useForm(cached?.expected_version === props.performance.version ? cached : initial);
const store = () => { try { localStorage.setItem(key,JSON.stringify(form.data())); saved.value = true; } catch { saved.value = false; } };
watch(() => [form.accuracy, form.presentation, form.reason], () => {
    if (!suppressDraft) { form.request_id = requestId(); store(); }
}, { flush: 'sync' });
const submit = () => {
    store();
    form.transform(data => ({ ...data, accuracy: String(data.accuracy), presentation: String(data.presentation) })).post(props.endpoint, {
        preserveScroll: true,
        onSuccess: () => {
            suppressDraft = true;
            try { localStorage.removeItem(key); } catch {}
            saved.value = false;
            form.request_id = requestId();
            form.expected_revision = props.performance.own_score?.revision || form.expected_revision + 1;
            form.expected_version = props.performance.version;
            form.reason = '';
            suppressDraft = false;
        },
    });
};
const reloadOwn = () => {
    form.accuracy = props.performance.own_score ? (props.performance.own_score.values.accuracy / 100).toFixed(2) : '';
    form.presentation = props.performance.own_score ? (props.performance.own_score.values.presentation / 100).toFixed(2) : '';
    form.expected_revision = props.performance.own_score?.revision || 0;
    form.expected_version = props.performance.version;
    form.reason = '';
    form.clearErrors();
};
</script>
<template>
    <form class="judge-form" @submit.prevent="submit">
        <p v-if="performance.own_score" class="notice">نمرهٔ شما ثبت شده است · نسخه {{ performance.own_score.revision }}. اصلاح تا پیش از تأیید اپراتور مجاز است.</p>
        <div class="score-inputs">
            <div><label :for="'accuracy-'+performance.id">دقت · از {{ rules.accuracy_max / 100 }}</label><input :id="'accuracy-'+performance.id" v-model="form.accuracy" type="number" :disabled="form.processing" inputmode="decimal" min="0" :max="rules.accuracy_max / 100" step="0.01" required dir="ltr"></div>
            <div><label :for="'presentation-'+performance.id">اجرا · از {{ rules.presentation_max / 100 }}</label><input :id="'presentation-'+performance.id" v-model="form.presentation" type="number" :disabled="form.processing" inputmode="decimal" min="0" :max="rules.presentation_max / 100" step="0.01" required dir="ltr"></div>
        </div>
        <div v-if="form.expected_revision > 0"><label :for="'correction-'+performance.id">دلیل اصلاح</label><textarea :id="'correction-'+performance.id" v-model="form.reason" :disabled="form.processing" required minlength="5" maxlength="500"></textarea></div>
        <p class="subtle">{{ saved ? 'پیش‌نویس روی همین دستگاه ذخیره شد؛ هنوز به معنای ثبت روی سرور نیست.' : 'ثبت قطعی فقط پس از پاسخ موفق سرور انجام می‌شود.' }}</p>
        <FormErrors :errors="form.errors"/>
        <div class="row-actions"><button class="button" :disabled="form.processing">{{ form.processing ? 'در حال ارسال…' : (form.expected_revision > 0 ? 'ثبت اصلاح نمره' : 'تأیید و ارسال نمره') }}</button><button v-if="(performance.own_score?.revision || 0) !== form.expected_revision" type="button" class="button secondary" @click="reloadOwn">دریافت نسخهٔ جدید نمره</button></div>
    </form>
</template>

