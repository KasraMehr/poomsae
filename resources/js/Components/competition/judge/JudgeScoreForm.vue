<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import JudgePadFields from './JudgePadFields.vue';
import { requestId } from '../../../Shared/Services/requestId';
import { scorePayload } from '../../../Shared/Services/scoring';
import { criterionScore, isLiveFinish, judgeCriteria, judgeScoreInitial, restoreJudgeDraft } from '../../../Shared/Services/judgeScoring';

const props = defineProps({ performance: Object, rules: Object, endpoint: String, paired: Boolean, offline: Boolean, allowSubmit: { type: Boolean, default: true } });
const emit = defineEmits(['summary']);
const page = usePage();
const key = `poomsae-score:${page.props.auth.user.id}:${props.performance.id}`;
let cached;
try { cached = JSON.parse(localStorage.getItem(key) || 'null'); } catch { cached = null; }
const restored = restoreJudgeDraft(cached, props.performance, props.rules);
const form = useForm(restored ?? judgeScoreInitial(props.performance, props.rules, requestId()));
const saved = ref(Boolean(restored));
const confirmed = ref(false);
const storageFailed = ref(false);
const total = ref(null);
const editing = ref(Boolean(restored));
const submitting = ref(false);
const transportError = ref('');
let suppressDraft = false;
let initialized = false;
onMounted(() => { initialized = true; });
const ownRevision = computed(() => props.performance.own_score?.revision ?? 0);
const conflict = computed(() => ownRevision.value !== form.expected_revision || props.performance.version !== form.expected_version);
const proxyLocked = computed(() => props.performance.status !== 'approved' && props.performance.own_score?.submission_mode === 'operator_proxy' && props.performance.own_score?.status === 'submitted');
const received = computed(() => (confirmed.value || Boolean(props.performance.own_score) && props.performance.own_score.status !== 'draft') && !editing.value);
const valid = computed(() => judgeCriteria(props.rules).every(criterion => criterionScore(form, criterion) !== null));
const maximumTotal = computed(() => judgeCriteria(props.rules).reduce((sum, criterion) => sum + criterion.maximum, 0));
const canSubmit = computed(() => props.allowSubmit && !props.offline && !conflict.value && !proxyLocked.value && !received.value && !submitting.value && valid.value && (!form.expected_revision || form.reason.trim().length >= 5));
const draftStatus = computed(() => conflict.value ? 'Review required' : received.value ? 'Received by server' : storageFailed.value ? 'Draft not saved on device' : saved.value ? 'Draft saved on device' : 'Enter your score');
const store = () => {
    try {
        localStorage.setItem(key, JSON.stringify({ rules: JSON.stringify(props.rules), status: props.performance.status, started_at: props.performance.started_at, data: form.data() }));
        saved.value = true;
        storageFailed.value = false;
    } catch { storageFailed.value = true; saved.value = false; }
};
watch(() => [form.accuracy, form.presentation, form.accuracy_penalties, form.presentation_components, form.presentation_penalties, form.accuracy_components, form.reason], () => {
    if (!initialized || suppressDraft || received.value) return;
    form.request_id = requestId();
    transportError.value = '';
    store();
}, { flush: 'sync', deep: true });
watch(() => ({ version: props.performance.version, status: props.performance.status, started_at: props.performance.started_at }), (next, previous) => {
    // Only the normal end-of-performance transition may carry an unsent score forward automatically.
    if (isLiveFinish(previous, next) && form.expected_version === previous.version && form.expected_revision === ownRevision.value) {
        form.expected_version = next.version;
        form.request_id = requestId();
        if (saved.value) store();
    }
});
watch(() => [total.value, received.value, saved.value, conflict.value], () => emit('summary', { total: total.value, received: received.value, saved: saved.value, conflict: conflict.value }), { immediate: true });
const submit = () => {
    if (!canSubmit.value || form.processing) return;
    submitting.value = true;
    transportError.value = '';
    store();
    form.transform(data => scorePayload(data, props.rules.input_method)).post(props.endpoint, {
        preserveScroll: true,
        onSuccess: () => {
            suppressDraft = true;
            confirmed.value = true;
            editing.value = false;
            form.expected_revision = Math.max(props.performance.own_score?.revision ?? 0, form.expected_revision + 1);
            form.expected_version = props.performance.version;
            form.reason = '';
            form.request_id = requestId();
            try { localStorage.removeItem(key); } catch {}
            saved.value = false;
            suppressDraft = false;
        },
        onError: () => { transportError.value = 'Score not accepted. Check the message and try again.'; },
        onNetworkError: () => { transportError.value = 'Connection lost. Your score is still saved on this device.'; },
        onFinish: () => {
            submitting.value = false;
            if (!received.value && !transportError.value) transportError.value = 'Receipt not confirmed. Your draft is kept; reconnect and retry.';
        },
    });
};
const reloadOwn = () => {
    suppressDraft = true;
    confirmed.value = false;
    Object.assign(form, judgeScoreInitial(props.performance, props.rules, requestId()));
    editing.value = false;
    form.clearErrors();
    transportError.value = '';
    saved.value = false;
    try { localStorage.removeItem(key); } catch {}
    suppressDraft = false;
};
const edit = () => { editing.value = true; form.request_id = requestId(); };
</script>

<template>
    <form class="judge-pad-form" :class="{ paired }" @submit.prevent="submit">
        <div class="pad-scroll">
            <div v-if="conflict" class="pad-notice" role="alert"><span>The performance or your score changed. Loading the latest score replaces this local draft.</span><button type="button" :disabled="form.processing" @click="reloadOwn">Discard draft & load latest</button></div>
            <div v-if="proxyLocked" class="pad-notice" role="status">An operator-entered score is confirmed. Contact the operator for changes.</div>
            <JudgePadFields :form="form" :rules="rules" :disabled="form.processing || received || conflict || proxyLocked" :paired="paired" :input-prefix="String(performance.id)" @total="total = $event" />
            <div v-if="form.expected_revision > 0 && !received && !proxyLocked" class="pad-reason"><label :for="'correction-' + performance.id">Reason for correction <span>At least 5 characters</span></label><textarea :id="'correction-' + performance.id" v-model="form.reason" :disabled="form.processing" required minlength="5" maxlength="500" placeholder="Explain the correction"></textarea></div>
            <div v-if="Object.keys(form.errors).length" class="pad-error" role="alert"><p v-for="(error, field) in form.errors" :key="field">{{ error }}</p></div>
            <p v-if="transportError" class="pad-error" role="alert">{{ transportError }}</p>
            <p v-if="storageFailed" class="pad-error" role="alert">Device storage is unavailable. Keep this page open until the server confirms your score.</p>
        </div>
        <footer class="pad-actions">
            <div class="pad-total"><span>YOUR TOTAL</span><strong>{{ total ?? '—' }}<small>/ {{ (maximumTotal / 100).toFixed(2) }}</small></strong></div>
            <div class="pad-status" :class="{ received }" role="status"><b>{{ received ? '✓ ' : '' }}{{ draftStatus }}</b><span>{{ offline ? 'Offline · reconnect to send' : !allowSubmit ? 'You can score now. Send after the performance.' : received ? 'Your score is safely recorded.' : 'Check the athlete before sending.' }}</span></div>
            <button v-if="received && !proxyLocked" type="button" class="pad-edit" :disabled="form.processing || conflict" @click="edit">Edit score</button>
            <button v-else type="submit" class="pad-submit" :disabled="!canSubmit || form.processing">{{ form.processing || submitting ? 'Sending…' : offline ? 'Offline' : !allowSubmit ? 'Waiting for finish' : form.expected_revision > 0 ? 'Send correction' : 'Send score' }}<span v-if="canSubmit">↗</span></button>
        </footer>
    </form>
</template>

<style scoped>
.judge-pad-form{display:flex;flex-direction:column;min-height:0;min-width:0;flex:1;gap:0}.pad-scroll{overflow:auto;overscroll-behavior:contain;min-height:0;flex:1;padding:12px;scrollbar-width:thin;scrollbar-color:#405974 transparent}.pad-actions{flex:none;display:flex;align-items:center;gap:16px;min-height:80px;padding:10px 18px;border-top:1px solid #2b3b50;background:#0f1e30}.pad-total{flex:none}.pad-total>span{display:block;font-size:9px;letter-spacing:.12em;color:#9db1c7;font-weight:700}.pad-total strong{font-size:34px;line-height:1.1;color:#f2d292;font-variant-numeric:tabular-nums}.pad-total small{font-size:12px;color:#7f96ae;margin-left:7px;font-weight:500}.pad-status{display:flex;flex-direction:column;gap:3px;flex:1;color:#b1c0d2;font-size:11px}.pad-status b{font-weight:600;color:#d9e5f3}.pad-status.received b{color:#7ae0b4}.pad-submit,.pad-edit{min-height:48px;min-width:160px;padding:8px 20px;border-radius:10px;font-weight:750;font-size:14px;touch-action:manipulation}.pad-submit{display:flex;align-items:center;justify-content:center;gap:18px;background:#e6c78d;color:#15202c;border:1px solid #f5dba9}.pad-submit span{font-size:21px}.pad-edit{background:#1f3549;border:1px solid #456780;color:#dbebfa}.pad-submit:disabled,.pad-edit:disabled{opacity:.45;cursor:default}.pad-submit:active:not(:disabled){background:#f7dfa9}.pad-reason{display:grid;gap:6px;margin-top:12px}.pad-reason label{font-size:12px;color:#e6c78d}.pad-reason label span{color:#8fa6bc;margin-left:10px;font-size:10px}.pad-reason textarea{min-height:62px;border:1px solid #415973;border-radius:9px;background:#0b1828;color:#f4f7fc;padding:10px}.pad-notice{display:flex;align-items:center;justify-content:space-between;gap:12px;background:#403421;color:#ffdfa4;border:1px solid #7d653c;padding:10px;border-radius:9px;font-size:12px;margin-bottom:10px}.pad-notice button{min-height:44px;flex:none;padding:6px 12px;border:1px solid #a58955;border-radius:6px;font-weight:700}.pad-error{padding:10px;color:#ffb7a5;font-size:12px}.judge-pad-form :is(button,textarea):focus-visible{outline:2px solid #8ed8ff;outline-offset:2px}
@media(min-width:1100px) and (min-height:600px){.paired .pad-status span{display:none}.paired .pad-actions{gap:10px;padding:10px 12px}.paired .pad-submit{min-width:130px;padding:8px 12px}}
@media(max-height:500px) and (orientation:landscape){.pad-scroll{padding:5px 8px}.pad-actions{min-height:51px;padding:4px 10px;gap:10px}.pad-total strong{font-size:26px}.pad-status{font-size:10px}.pad-status span{display:none}.pad-submit,.pad-edit{min-height:42px;min-width:135px;padding:5px 10px;font-size:12px}}
@media(max-width:560px) and (orientation:portrait){.pad-actions{flex-wrap:wrap;gap:8px;padding:10px}.pad-total{margin-right:auto}.pad-status{flex-basis:100%;order:3}.pad-submit,.pad-edit{min-width:150px}.pad-notice{flex-direction:column;align-items:stretch}}
</style>
