<script setup>
import { computed, ref, watch } from "vue";
import { useForm, usePage } from "@inertiajs/vue3";
import FormErrors from "../../../Shared/Components/FormErrors.vue";
import JudgePadFields from "./JudgePadFields.vue";
import { requestId } from "../../../Shared/Services/requestId";
import {
    hasScoreDetails,
    scoreComponentsComplete,
    scoreDetailFields,
    scorePayload,
} from "../../../Shared/Services/scoring";
const props = defineProps({
    performance: Object,
    rules: Object,
    endpoint: String,
    paired: Boolean,
    allowSubmit: { type: Boolean, default: true },
});
const page = usePage();
const key =
    "poomsae-score:" + page.props.auth.user.id + ":" + props.performance.id;
let cached;
try {
    cached = JSON.parse(localStorage.getItem(key) || "null");
} catch {
    cached = null;
}
const saved = ref(false);
const submitted = ref(false);
let suppressDraft = false;
// Resume a draft only when its scoring method and performance version still match.
const previousBreakdown = props.performance.own_score?.breakdown;
const inputMethod =
    !props.performance.own_score ||
    previousBreakdown?.method === props.rules.input_method
        ? props.rules.input_method
        : null;
const initial = {
    request_id: requestId(),
    expected_version: props.performance.version,
    expected_revision: props.performance.own_score?.revision || 0,
    accuracy: props.performance.own_score
        ? ((props.performance.own_score.values.accuracy ?? props.performance.own_score.values.score ?? 0) / 100).toFixed(2)
        : "",
    presentation: props.performance.own_score
        ? ((props.performance.own_score.values.presentation ?? 0) / 100).toFixed(2)
        : "",
    ...scoreDetailFields(inputMethod, previousBreakdown),
    reason: "",
};
const usableDraft =
    cached?.expected_version === props.performance.version &&
    hasScoreDetails(cached, inputMethod);
const form = useForm(usableDraft ? cached : initial);
const canSubmit = computed(() => props.allowSubmit && scoreComponentsComplete(form) && form.accuracy !== '' && (props.rules.input_method === 'single_score_v1' || form.presentation !== ''));
const store = () => {
    try {
        localStorage.setItem(key, JSON.stringify(form.data()));
        saved.value = true;
    } catch {
        saved.value = false;
    }
};
watch(
    () => [
        form.accuracy,
        form.presentation,
        form.accuracy_penalties,
        form.presentation_components,
        form.presentation_penalties,
        form.accuracy_components,
        form.reason,
    ],
    () => {
        if (!suppressDraft) {
            form.request_id = requestId();
            store();
        }
    },
    { flush: "sync", deep: true },
);
const submit = () => {
    store();
    form.transform((data) => scorePayload(data, props.rules.input_method)).post(
        props.endpoint,
        {
            preserveScroll: true,
            onSuccess: () => {
                submitted.value = true;
                suppressDraft = true;
                try {
                    localStorage.removeItem(key);
                } catch {}
                saved.value = false;
                form.request_id = requestId();
                form.expected_revision =
                    props.performance.own_score?.revision ||
                    form.expected_revision + 1;
                form.expected_version = props.performance.version;
                form.reason = "";
                suppressDraft = false;
            },
        },
    );
};
const reloadOwn = () => {
    submitted.value = false;
    form.accuracy = props.performance.own_score
        ? ((props.performance.own_score.values.accuracy ?? props.performance.own_score.values.score ?? 0) / 100).toFixed(2)
        : "";
    form.presentation = props.performance.own_score
        ? ((props.performance.own_score.values.presentation ?? 0) / 100).toFixed(2)
        : "";
    const breakdown = props.performance.own_score?.breakdown;
    Object.assign(
        form,
        scoreDetailFields(
            breakdown?.method ?? props.rules.input_method,
            breakdown,
        ),
    );
    form.expected_revision = props.performance.own_score?.revision || 0;
    form.expected_version = props.performance.version;
    form.reason = "";
    form.clearErrors();
};
</script>
<template>
    <form class="judge-pad-form" :class="{ paired }" @submit.prevent="submit">
        <JudgePadFields :form="form" :rules="rules" :disabled="form.processing" :paired="paired" :input-prefix="String(performance.id)" />
        <div v-if="form.expected_revision > 0" class="pad-reason"><label :for="'correction-' + performance.id">CORRECTION REASON</label><textarea :id="'correction-' + performance.id" v-model="form.reason" :disabled="form.processing" required minlength="5" maxlength="500" placeholder="Reason"></textarea></div>
        <FormErrors :errors="form.errors" />
        <div class="pad-actions"><span class="pad-state" :class="{ received: performance.own_score || submitted }" role="status">{{ performance.own_score || submitted ? '✓ RECEIVED' : allowSubmit ? saved ? '● DRAFT' : '● READY' : '● LIVE' }}</span><button v-if="(performance.own_score?.revision || 0) !== form.expected_revision" type="button" class="pad-reload" @click="reloadOwn">RELOAD</button><button type="submit" class="pad-submit" :disabled="form.processing || !canSubmit">{{ form.processing ? 'SENDING…' : !allowSubmit ? 'WAITING' : form.expected_revision > 0 ? 'SEND CORRECTION' : 'SEND SCORE' }}</button></div>
    </form>
</template>

<style scoped>
.judge-pad-form{display:flex;flex-direction:column;gap:9px;min-height:0;min-width:0;flex:1}.pad-reason{display:grid;gap:5px}.pad-reason label{color:#f4cf74;font-size:10px;font-weight:900;letter-spacing:.1em}.pad-reason textarea{height:54px;min-height:54px;border:1px solid #6a8195;border-radius:8px;background:#11283c;padding:8px;color:#fff}.pad-actions{display:flex;align-items:center;gap:8px;min-height:72px;margin-top:auto;padding:8px;border:1px solid #354d65;border-radius:12px;background:#101d30}.pad-state{flex:1;min-width:0;padding-left:12px;color:#f1cb60;font-size:12px;font-weight:900;letter-spacing:.1em;white-space:nowrap}.pad-state.received{color:#77e6b1}.pad-submit{min-height:54px;min-width:190px;padding:8px 16px;border:0;border-radius:9px;background:#f2c94c;color:#13202c;font-size:16px;font-weight:950;letter-spacing:.04em;touch-action:manipulation}.pad-submit:disabled{opacity:.42}.pad-reload{min-height:42px;padding:6px 10px;border:1px solid #7194af;border-radius:7px;color:white;font-size:11px;font-weight:800}.judge-pad-form.paired .pad-actions{min-height:55px;padding:5px}.paired .pad-state{font-size:10px;padding-left:5px}.paired .pad-submit{min-height:43px;min-width:145px;font-size:12px}.paired .pad-reason textarea{height:42px;min-height:42px}@media(max-width:700px) and (orientation:landscape){.pad-actions{min-height:50px}.pad-submit{min-height:40px;min-width:130px;font-size:12px}.paired .pad-actions{min-height:44px}.paired .pad-submit{min-height:35px;min-width:108px;font-size:10px}.paired .pad-state{font-size:9px}}@media(max-width:650px) and (orientation:portrait){.pad-actions{flex-wrap:wrap}.pad-submit{min-width:0;flex:1}}
@media(max-width:900px) and (orientation:landscape) and (max-height:500px){.judge-pad-form{gap:4px}.pad-actions,.judge-pad-form.paired .pad-actions{min-height:38px;padding:3px 5px}.pad-submit,.paired .pad-submit{min-height:32px;min-width:105px;padding:3px 7px;font-size:10px}.pad-state,.paired .pad-state{font-size:9px;padding-left:2px}}
</style>
