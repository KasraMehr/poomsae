<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import ProxyScoreForm from './ProxyScoreForm.vue';
import FormErrors from '../../../Shared/Components/FormErrors.vue';

const props = defineProps({ performance: Object, rules: Object, base: String });
const confirmation = useForm({ expected_revision: 1 });
const drafts = computed(() => (props.performance.scores ?? [])
    .filter(score => score.submission_mode === 'operator_proxy' && score.status === 'draft')
    .sort((a, b) => a.seat - b.seat));
const confirm = score => {
    confirmation.expected_revision = score.revision;
    confirmation.post(`${props.base}/performances/${props.performance.id}/proxy-scores/${score.id}/confirm`, { preserveScroll: true });
};
const judgeName = seat => props.performance.judges.find(judge => judge.seat === seat)?.name ?? `Judge ${seat}`;
const total = score => (props.rules.input_method === 'single_score_v1'
    ? Number(score.values?.score ?? 0) / 100
    : (Number(score.values?.accuracy ?? 0) + Number(score.values?.presentation ?? 0)) / 100).toFixed(2);
</script>

<template>
    <div class="manual-panel">
        <p class="manual-intro">Use this only when a judge cannot submit from their tablet. Record the score given by that judge and explain the device issue. The entry is logged and must be confirmed before the result can be published.</p>
        <div class="manual-stage"><strong>PERFORMANCE {{ performance.id }}</strong><span>{{ performance.entry_name }} · {{ performance.form_name }}</span><span>{{ performance.submitted_count }} / {{ performance.judge_count }} confirmed scores</span></div>
        <div v-if="drafts.length" class="manual-review"><h3>Scores awaiting confirmation</h3><div v-for="score in drafts" :key="score.id" class="manual-review-row"><div><strong>J{{ score.seat }} · {{ judgeName(score.seat) }}</strong><span><template v-if="rules.input_method !== 'single_score_v1'">Accuracy {{ (score.values.accuracy / 100).toFixed(2) }} · Presentation {{ (score.values.presentation / 100).toFixed(2) }} · </template>Total {{ total(score) }}</span><small>Review this score against the judge's record before confirming.</small></div><button type="button" :disabled="confirmation.processing" @click="confirm(score)">Confirm score</button></div><FormErrors :errors="confirmation.errors" /></div>
        <ProxyScoreForm v-if="performance.missing_seats?.length" :key="performance.id" :performance="performance" :rules="rules" :endpoint="`${base}/performances/${performance.id}/proxy-scores`" expanded />
        <p v-else-if="!drafts.length" class="manual-complete">All judge seats have confirmed scores.</p>
    </div>
</template>

<style scoped>
.manual-panel{padding:18px}.manual-intro{margin:0 0 14px;max-width:750px;line-height:1.5;color:#48576c}.manual-stage{display:flex;flex-wrap:wrap;gap:8px 20px;padding:12px;border:1px solid #cbd2dd;background:#e9eff8}.manual-stage strong{font-size:11px;letter-spacing:.6px}.manual-review{margin-top:15px;padding:14px;border:1px solid #c68d47;background:#fff7eb}.manual-review h3{margin:0 0 10px;font-size:15px}.manual-review-row{display:flex;align-items:center;justify-content:space-between;gap:15px;padding:10px 0;border-top:1px solid #e4cba8}.manual-review-row div{display:grid;gap:3px}.manual-review-row span,.manual-review-row small{font-size:12px;color:#536074}.manual-review-row button{min-height:38px;padding:7px 13px;border:1px solid #0d192b;background:#0d192b;color:white;font-weight:700;white-space:nowrap}.manual-review-row button:disabled{opacity:.5}.manual-complete{padding:20px;color:#24744f;font-weight:700}@media(max-width:600px){.manual-review-row{align-items:stretch;flex-direction:column}}
</style>
