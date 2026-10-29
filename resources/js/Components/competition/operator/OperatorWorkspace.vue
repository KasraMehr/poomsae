<script setup>
import { computed, ref, watch } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import CourtConsole from './CourtConsole.vue';
import ManualScorePanel from './ManualScorePanel.vue';
import BoutPanel from '../BoutPanel.vue';
import FormErrors from '../../../Shared/Components/FormErrors.vue';
import SetupStage from './SetupStage.vue';
import LiveStage from './LiveStage.vue';
import ResultStage from './ResultStage.vue';
import ConfirmedStage from './ConfirmedStage.vue';

const props = defineProps({ console: Object, can: Object, urls: Object, onlineJudgeIds: Array, connection: String });
const courtId = ref(props.console.courts[0]?.id ?? null);
const matchesOpen = ref(true);
const eventsOpen = ref(false);
const matchId = ref(null);
const activeStage = ref('setup');
const stageByMatch = ref({});
const stages = [
    { id: 'setup', label: 'SETUP' },
    { id: 'live', label: 'LIVE' },
    { id: 'result', label: 'RESULT' },
    { id: 'confirmed', label: 'CONFIRMED' },
];
const stageComponents = { setup: SetupStage, live: LiveStage, result: ResultStage, confirmed: ConfirmedStage };
const selectedStageComponent = computed(() => stageComponents[activeStage.value]);
const court = computed(() => props.console.courts.find(item => item.id === courtId.value) ?? props.console.courts[0]);
const matches = computed(() => court.value?.matches ?? []);
const match = computed(() => matches.value.find(item => item.id === matchId.value) ?? matches.value.find(item => item.id === court.value?.active?.bout_id) ?? matches.value.find(item => item.id === court.value?.queue?.[0]?.bout_id) ?? matches.value[0]);
watch(courtId, () => { matchId.value = null; });
const category = computed(() => props.console.tournament.categories.find(item => item.id === match.value?.category_id));
const round = computed(() => category.value?.rounds.find(item => item.id === match.value?.round_id));
const bout = computed(() => round.value?.bouts.find(item => item.id === match.value?.id));
const performance = computed(() => court.value?.active?.bout_id === match.value?.id ? court.value.active : court.value?.queue?.find(item => item.bout_id === match.value?.id) ?? bout.value?.performances.find(item => item.status === 'scoring' || item.status === 'running') ?? bout.value?.performances[0]);
const stageForStatus = status => ({ pending: 'setup', running: 'live', scoring: 'result', approved: 'confirmed', completed: 'confirmed', cancelled: 'confirmed' })[status] ?? 'setup';
const currentStage = computed(() => stageForStatus(performance.value?.status));
const saveStageForMatch = (matchKey, selected, lifecycle) => {
    if (!matchKey) return;
    stageByMatch.value = { ...stageByMatch.value, [matchKey]: { selected, lifecycle } };
};
const selectStage = stage => {
    activeStage.value = stage;
    saveStageForMatch(match.value?.id, stage, currentStage.value);
};
let observedMatchId = null;
watch([() => match.value?.id, currentStage], ([matchKey, lifecycle]) => {
    if (!matchKey) return;

    const remembered = stageByMatch.value[matchKey];
    if (matchKey !== observedMatchId) {
        activeStage.value = remembered
            ? remembered.selected === remembered.lifecycle && remembered.lifecycle !== lifecycle ? lifecycle : remembered.selected
            : lifecycle;
    } else if (remembered && remembered.selected === remembered.lifecycle && remembered.lifecycle !== lifecycle) {
        activeStage.value = lifecycle;
    }

    saveStageForMatch(matchKey, activeStage.value, lifecycle);
    observedMatchId = matchKey;
}, { immediate: true });
const action = useForm({ command: '', expected_version: 1 });
const completion = useForm({});
const detailsDialog = ref(null);
const manualDialog = ref(null);
watch([courtId, matchId], () => manualDialog.value?.close());
const manualPerformance = computed(() => court.value?.active?.id === performance.value?.id && performance.value?.status === 'scoring' ? performance.value : null);
const judges = computed(() => (bout.value?.judges ?? []).map(judge => ({
    ...judge,
    connected: props.connection === 'connected' ? props.onlineJudgeIds.includes(judge.user_id) : null,
    submitted: performance.value?.submitted_seats?.includes(judge.seat) ?? false,
    pendingReview: performance.value?.pending_review_seats?.includes(judge.seat) ?? false,
})));
const judgeDevicePresence = computed(() => {
    const states = judges.value.map(judge => judge.connected);

    if (states.length > 0 && states.every(state => state === true)) {
        return 'online';
    }

    if (states.length > 0 && states.every(state => state === false)) {
        return 'offline';
    }

    return 'partial';
});
const command = value => {
    if (!performance.value) return;
    action.command = value;
    action.expected_version = performance.value.version;
    action.post(`${props.urls.base}/performances/${performance.value.id}/command`, { preserveScroll: true });
};
const scoreFor = entry => match.value?.totals?.[entry.id] ?? [...(bout.value?.performances ?? [])].reverse().find(item => item.entry_id === entry.id && (item.result || item.preview_result))?.result ?? [...(bout.value?.performances ?? [])].reverse().find(item => item.entry_id === entry.id)?.preview_result ?? null;
const detailedScoresFor = entry => [...(bout.value?.performances ?? [])].reverse().find(item => item.entry_id === entry.id && item.detailed_scores)?.detailed_scores ?? null;
const scoreRows = computed(() => [...(performance.value?.scores ?? [])].sort((a, b) => a.seat - b.seat));
const number = value => value == null ? '—' : Number(value).toFixed(2);
const scorePart = (score, part) => score?.values?.[part] == null ? '—' : number(score.values[part] / 100);
const statusLabels = { pending: 'READY', running: 'LIVE', scoring: 'SCORING', approved: 'APPROVED', completed: 'COMPLETED', cancelled: 'CANCELLED' };
const eventLabels = { 'performance.start': 'Performance started', 'performance.finish': 'Performance finished', 'performance.approve': 'Result approved and published', 'score.submitted': 'Judge score submitted', 'score.proxy_submitted': 'Manual score submitted for review', 'score.proxy_confirmed': 'Manual score confirmed', 'bout.completed': 'Match completed', 'round.completed': 'Round completed', 'round.scheduled': 'Round scheduled', 'entry.created': 'Athlete registered', 'entry.status': 'Athlete status changed', 'category.saved': 'Division configured', 'court.created': 'Court created', 'member.added': 'Judge assigned', 'tournament.created': 'Competition created' };
const time = value => value ? new Date(value).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit', second: '2-digit' }) : '—';
</script>

<template>
    <div class="operator-workspace" dir="ltr">
        <header class="operator-topbar">
            <strong class="operator-brand">POOMSAE<span>CONTROL</span></strong>
            <nav aria-label="Competition sections"><a href="#matches">MATCH</a><span class="operator-tab active">SCORING</span><Link :href="urls.reports">HISTORY</Link></nav>
            <div class="operator-top-actions"><label for="operator-court">COURT</label><select id="operator-court" v-model="courtId"><option v-for="item in props.console.courts" :key="item.id" :value="item.id">{{ item.name }}</option></select><span class="operator-live" :class="connection">● {{ connection === 'connected' ? 'LIVE' : 'POLLING' }}</span><button type="button" class="operator-events-toggle" :aria-expanded="eventsOpen" aria-controls="operator-events-panel" :aria-label="eventsOpen ? 'Close recent events panel' : 'Open recent events panel'" :title="eventsOpen ? 'Close recent events' : 'Open recent events'" @click="eventsOpen = !eventsOpen"><svg aria-hidden="true" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" :class="{ reversed: !eventsOpen }"><path d="m7 4 6 6-6 6" stroke-linecap="round" stroke-linejoin="round" /></svg><span>EVENTS</span></button></div>
        </header>

        <main class="operator-columns" :class="{ 'matches-collapsed': !matchesOpen, 'events-collapsed': !eventsOpen }" v-if="court">
            <aside id="matches" class="operator-panel operator-match-list" :class="{ 'match-list-collapsed': !matchesOpen }" aria-label="Match list">
                <button class="operator-panel-head operator-match-head" type="button" :aria-expanded="matchesOpen" aria-controls="operator-match-list-content" :aria-label="matchesOpen ? 'Collapse matches' : 'Expand matches'" @click="matchesOpen = !matchesOpen"><strong>MATCHES</strong><span class="operator-match-head-actions"><span>{{ matches.length }}</span><svg aria-hidden="true" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" :class="{ reversed: matchesOpen }"><path d="m7 4 6 6-6 6" stroke-linecap="round" stroke-linejoin="round" /></svg></span></button>
                <div id="operator-match-list-content" class="operator-match-content"><div class="operator-match-scroll"><button v-for="item in matches" :key="item.id" type="button" class="operator-match" :class="{ selected: match?.id === item.id, ready: item.status === 'pending', live: item.status === 'running', completed: item.status === 'completed', cancelled: item.status === 'cancelled' }" @click="matchId = item.id">
                    <span class="operator-match-number">No.{{ item.sequence }}</span><span class="operator-match-status" :class="{ ready: item.status === 'pending', live: item.status === 'running', completed: item.status === 'completed', cancelled: item.status === 'cancelled' }"><span v-if="item.status === 'running'" class="operator-match-live-dot" aria-hidden="true"></span>{{ statusLabels[item.status] ?? item.status }}</span>
                    <strong>{{ item.entries.map(entry => entry.short_name || entry.name).join('  /  ') || 'No athletes assigned' }}</strong>
                    <small>{{ item.category_name }} · {{ item.round_name }}</small>
                </button><p v-if="!matches.length" class="operator-empty">No matches are scheduled on this court.</p></div>
                <div class="operator-hardware"><button v-if="!matchesOpen" type="button" class="operator-judge-devices-toggle" :aria-expanded="matchesOpen" aria-controls="operator-match-list-content" @click="matchesOpen = true"><span>JUDGE DEVICES</span><span class="operator-judge-devices-indicator" :class="judgeDevicePresence" :title="judgeDevicePresence === 'online' ? 'All judges online' : judgeDevicePresence === 'offline' ? 'All judges offline' : 'Some judges offline or presence unknown'"></span><svg aria-hidden="true" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m7 4 6 6-6 6" stroke-linecap="round" stroke-linejoin="round" /></svg></button><template v-else><div><span>JUDGE DEVICES</span><b>{{ connection === 'connected' ? `${judges.filter(judge => judge.connected).length} / ${judges.length} CONNECTED` : 'PRESENCE UNAVAILABLE' }}</b><button class="operator-match-toggle" type="button" :aria-expanded="matchesOpen" aria-controls="operator-match-list-content" aria-label="Collapse matches" title="Collapse matches" @click="matchesOpen = false"><svg aria-hidden="true" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" class="reversed"><path d="m7 4 6 6-6 6" stroke-linecap="round" stroke-linejoin="round" /></svg></button></div><div class="operator-judge-pills"><span v-for="judge in judges" :key="judge.id" :title="`${judge.name}: ${judge.connected === null ? 'Presence unavailable' : judge.connected ? 'Connected' : 'Not connected'}`" :class="{ online: judge.connected }">J{{ judge.seat }}</span></div></template></div></div>
            </aside>

            <section class="operator-center" aria-label="Match control">
                <div class="operator-stage-head"><div><h1>{{ activeStage === 'live' ? 'SCORING' : activeStage.toUpperCase() }} <span>/ {{ performance?.status === 'scoring' ? 'RESULT READY' : (statusLabels[performance?.status] ?? 'MATCH CONTROL') }}</span></h1></div><div class="operator-steps" role="group" aria-label="Performance stages"><button v-for="stage in stages" :key="stage.id" type="button" :aria-pressed="activeStage === stage.id" :aria-current="currentStage === stage.id ? 'step' : undefined" :class="{ active: activeStage === stage.id, current: currentStage === stage.id }" @click="selectStage(stage.id)">{{ stage.label }}</button></div></div>
                <div class="operator-stage-content">
                <SetupStage v-if="activeStage === 'setup'" :match="match" :category="category" :round="round" :bout="bout" :performance="performance" :judges="judges" :connection="connection" @continue="selectStage" />
                <LiveStage v-else-if="activeStage === 'live'">
                <template v-if="match">
                    <div class="operator-context"><strong class="operator-context-number">No.{{ match.sequence }}</strong><span class="operator-context-event">{{ match.category_name }} · {{ match.round_name }}</span><span v-if="performance" class="operator-context-form">{{ performance.form_name }} · FORM {{ performance.form_number }}</span></div>
                    <div class="operator-score-cards"><article v-for="(entry, index) in match.entries" :key="entry.id" class="operator-score-card" :class="[index === 0 ? 'chung' : 'hong', { winner: match.winner_entry_id === entry.id }]"><div class="operator-card-header"><span>{{ entry.side?.toUpperCase() ?? (index === 0 ? 'CHUNG / BLUE' : 'HONG / RED') }}</span><b v-if="match.winner_entry_id === entry.id">★ WINNER</b></div><h2>{{ entry.short_name || entry.name }}</h2><div class="operator-card-scores"><div class="operator-card-components"><div><small>ACCURACY</small><strong>{{ number(detailedScoresFor(entry)?.accuracy) }}</strong></div><div><small>PRESENTATION</small><strong>{{ number(detailedScoresFor(entry)?.presentation) }}</strong></div></div><div class="operator-card-total"><small>{{ match.totals?.[entry.id] != null ? 'OFFICIAL TOTAL' : 'TOTAL' }}</small><strong class="operator-big-score">{{ number(scoreFor(entry)) }}</strong></div></div></article><article v-if="!match.entries.length" class="operator-score-card">No athletes assigned.</article></div>
                    <div class="operator-judge-status"><div v-for="judge in judges" :key="judge.id" class="operator-judge" :class="{ submitted: judge.submitted, 'review-required': !judge.submitted && judge.pendingReview, pending: !judge.submitted && !judge.pendingReview }"><strong>J{{ judge.seat }} · {{ judge.name }}</strong><small>{{ judge.submitted ? '✓ SCORE SUBMITTED' : judge.pendingReview ? 'REVIEW REQUIRED' : 'SCORE PENDING' }}</small></div></div>
                    <div class="operator-matrix"><div class="operator-matrix-head"><strong>JUDGE SCORE MATRIX</strong><span>{{ performance?.result != null ? `FINAL AVG: ${number(performance.result)}` : performance?.preview_result != null ? `PREVIEW: ${number(performance.preview_result)}` : `${performance?.submitted_count ?? 0} / ${performance?.judge_count ?? 0} SCORES` }}</span></div><div class="operator-table-wrap"><table><thead><tr><th>JUDGE</th><th>ACCURACY</th><th>PRESENTATION</th><th>TOTAL</th><th>STATUS</th></tr></thead><tbody><tr v-for="score in scoreRows" :key="score.id"><td>J{{ score.seat }}</td><td>{{ scorePart(score, 'accuracy') }}</td><td>{{ scorePart(score, 'presentation') }}</td><td>{{ score.values ? number((score.values.score ?? ((score.values.accuracy ?? 0) + (score.values.presentation ?? 0))) / 100) : '—' }}</td><td>{{ score.status === 'draft' ? 'MANUAL · REVIEW' : score.submission_mode === 'operator_proxy' ? 'MANUAL · CONFIRMED' : 'RECEIVED' }}</td></tr><tr v-if="!scoreRows.length"><td colspan="5">No scores received for this performance.</td></tr></tbody></table></div></div>
                    <div class="operator-actions"><button v-if="performance?.status === 'pending' && court.queue.some(item => item.id === performance.id)" class="operator-primary" :disabled="action.processing" @click="command('start')">Start performance</button><button v-if="performance?.status === 'running'" class="operator-primary" :disabled="action.processing" @click="command('finish')">Finish and collect scores</button><button v-if="performance?.status === 'scoring'" class="operator-primary" :disabled="action.processing || performance.submitted_count !== performance.judge_count || performance.pending_review_seats?.length" @click="command('approve')">Confirm and publish result</button><span v-if="performance?.status === 'scoring' && performance.submitted_count !== performance.judge_count" class="operator-action-note">Waiting for all judge scores</span><button v-if="manualPerformance && (manualPerformance.missing_seats?.length || manualPerformance.pending_review_seats?.length)" type="button" class="operator-manual" @click="manualDialog?.showModal()">{{ manualPerformance.pending_review_seats?.length ? 'Review manual score' : 'Manual score / tablet issue' }}</button></div><FormErrors :errors="action.errors" />
                    <button class="operator-details-button" type="button" @click="detailsDialog?.showModal()">Match controls and decisions ↗</button>
                </template><div v-else class="operator-empty operator-center-empty">No matches are scheduled on this court.</div>
                </LiveStage>
                <ResultStage v-else-if="activeStage === 'result'" />
                <ConfirmedStage v-else />
                </div>
            </section>

            <aside id="operator-events-panel" class="operator-panel operator-events" :class="{ 'events-hidden': !eventsOpen }" :aria-hidden="!eventsOpen" :inert="!eventsOpen" aria-label="System events"><div class="operator-panel-head"><strong>SYSTEM EVENT LOG</strong><span>▤</span></div><ol><li v-for="event in props.console.recent_events" :key="event.id"><time :datetime="event.occurred_at">{{ time(event.occurred_at) }}</time><div><strong>{{ eventLabels[event.action] ?? event.action }}</strong><small>{{ event.actor_name ?? 'System' }}</small></div></li></ol><p v-if="!props.console.recent_events?.length" class="operator-empty">No events recorded.</p></aside>
        </main><div v-else class="operator-empty operator-no-court">No court has been configured. <Link v-if="can.manage" :href="urls.setup">Open setup</Link></div>
        <footer class="operator-footer"><span>SYSTEM STATUS: {{ connection === 'connected' ? 'LIVE' : 'POLLING' }} &nbsp;|&nbsp; {{ props.console.tournament.name }}</span><span><a :href="urls.excel">EXCEL</a> &nbsp;·&nbsp; {{ props.console.attention.running }} LIVE &nbsp;·&nbsp; {{ props.console.attention.waiting_for_scores }} WAITING &nbsp;·&nbsp; {{ props.console.attention.ready }} READY <button v-if="can.manage && props.console.tournament.status === 'running' && props.console.tournament.categories.length && props.console.tournament.categories.every(item => item.completed)" :disabled="completion.processing" @click="completion.post(urls.base + '/complete')">Complete competition</button></span></footer>
        <dialog ref="detailsDialog" class="operator-modal" @click="event => { if (event.target === detailsDialog) detailsDialog.close(); }"><div class="operator-modal-head"><div><strong>MATCH {{ match?.sequence }} · ADVANCED CONTROLS</strong><small>Review scores, resolve ties, and manage exceptions</small></div><button type="button" aria-label="Close dialog" @click="detailsDialog.close()">×</button></div><div class="operator-modal-body"><CourtConsole v-if="match && (court?.active?.bout_id === match.id || court?.queue?.[0]?.bout_id === match.id)" :court="court" :rules="category?.rules ?? {}" :online-judge-ids="onlineJudgeIds" :base="urls.base" /><BoutPanel v-if="bout && round && category" :bout="bout" :round="round" :category="category" :tournament="props.console.tournament" :base="urls.base" :can-operate="can.operate" /></div></dialog>
        <dialog ref="manualDialog" class="operator-modal operator-manual-modal" @click="event => { if (event.target === manualDialog) manualDialog.close(); }"><div class="operator-modal-head"><div><strong>MANUAL JUDGE SCORE</strong><small>Tablet issue · recorded with operator and reason</small></div><button type="button" aria-label="Close dialog" @click="manualDialog.close()">×</button></div><div class="operator-modal-body"><ManualScorePanel v-if="manualPerformance" :performance="manualPerformance" :rules="category?.rules ?? {}" :base="urls.base" /></div></dialog>

    </div>
</template>

<style scoped>
@font-face{font-family:"OperatorMixed";src:local("Arial");font-style:normal;font-weight:400;unicode-range:U+0000-024F}@font-face{font-family:"OperatorMixed";src:local("Arial Bold");font-style:normal;font-weight:500 900;unicode-range:U+0000-024F}@font-face{font-family:"OperatorMixed";src:url("../../../../fonts/Vazirmatn-Variable.woff2") format("woff2");font-style:normal;font-weight:100 900;unicode-range:U+0600-06FF,U+0750-077F,U+08A0-08FF,U+FB50-FDFF,U+FE70-FEFF}@font-face{font-family:"OperatorMixedMono";src:local("Consolas"),local("Courier New");font-style:normal;font-weight:100 900;unicode-range:U+0000-024F}@font-face{font-family:"OperatorMixedMono";src:url("../../../../fonts/Vazirmatn-Variable.woff2") format("woff2");font-style:normal;font-weight:100 900;unicode-range:U+0600-06FF,U+0750-077F,U+08A0-08FF,U+FB50-FDFF,U+FE70-FEFF}.operator-workspace{min-height:100vh;background:#f5f6fb;color:#0d192b;font-family:"OperatorMixed",Arial,Tahoma,sans-serif;font-size:13px}.operator-topbar{height:64px;display:flex;align-items:center;justify-content:space-between;gap:20px;padding:0 24px;border-bottom:1px solid #c7ccd5;background:#f7f8fc}.operator-brand{font-size:18px;font-weight:900;letter-spacing:-1px;white-space:nowrap}.operator-brand span{letter-spacing:-1.2px}.operator-topbar nav{height:100%;display:flex;align-items:stretch;gap:8px;overflow:auto}.operator-topbar nav a,.operator-tab{display:grid;place-items:center;padding:0 16px;color:#566174;font-size:11px;letter-spacing:1.4px;font-weight:700;white-space:nowrap}.operator-tab.active{border-bottom:2px solid #0d192b;color:#0d192b}.operator-top-actions{display:flex;align-items:center;gap:10px;white-space:nowrap}.operator-top-actions label{margin:0;color:#667184;font-size:11px}.operator-top-actions select{width:auto;min-height:30px;padding:3px 8px;border:1px solid #c8cfda;background:#e2e7f2;color:#0d192b}.operator-live{font-size:10px;color:#a16a22}.operator-live.connected{color:#197451}.operator-columns{display:grid;grid-template-columns:minmax(240px,340px) minmax(420px,1fr) minmax(250px,340px);gap:16px;max-width:1920px;min-height:calc(100vh - 104px);margin:auto;padding:16px}.operator-panel{border:1px solid #c4c9d2;background:#f7f8fc;min-width:0}.operator-panel-head{height:38px;padding:0 9px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #c4c9d2;background:#edf1f9;font-size:12px;letter-spacing:.3px}.operator-match-list{display:flex;flex-direction:column}.operator-match-scroll{overflow:auto;max-height:calc(100vh - 225px);flex:1}.operator-match{display:grid;grid-template-columns:1fr auto;width:100%;padding:15px 12px;gap:6px;text-align:left;border:0;border-bottom:1px solid #d8dce5;background:transparent;color:#0d192b}.operator-match:hover,.operator-match.selected{background:#e9effb}.operator-match.selected{box-shadow:inset 4px 0 #0d192b}.operator-match-number{font-weight:800}.operator-match-status{font-size:10px;color:#54657b}.operator-match strong,.operator-match small{grid-column:1/-1}.operator-match strong{font-size:15px}.operator-match small{color:#667184}.operator-hardware{margin-top:auto;padding:12px;border-top:1px solid #c4c9d2}.operator-hardware>div:first-child{display:flex;justify-content:space-between;font-size:10px;color:#5c687a}.operator-judge-pills{display:flex;gap:5px;margin-top:10px}.operator-judge-pills span{border:1px solid #758195;background:#e1e6f0;padding:2px 9px;font-size:10px;font-weight:700}.operator-judge-pills .online{border-color:#19815d}.operator-judge-pills .submitted{background:#d4eadf}.operator-center{min-width:0}.operator-stage-head{min-height:83px;border:1px solid #c4c9d2;background:#f7f8fc;padding:12px 14px;display:flex;justify-content:space-between;gap:12px;align-items:center}.operator-overline{font-size:10px;color:#667184;letter-spacing:.5px}.operator-stage-head h1{font-size:23px;line-height:1.15;margin:4px 0;font-weight:500}.operator-stage-head h1 span{font-weight:400}.operator-steps{display:flex;gap:4px}.operator-steps span{font-size:10px;padding:3px 7px;background:#e1e6f0;color:#798291;font-weight:700}.operator-steps .active{background:#0d192b;color:white}.operator-context{display:flex;gap:16px;align-items:center;padding:12px 0;flex-wrap:wrap}.operator-context strong{font-size:18px}.operator-context span{color:#586478}.operator-score-cards{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.operator-score-card{min-height:205px;padding:16px;border:1px solid #c4c9d2;background:#f2f5fd}.operator-score-card.chung{background:#ebf1fd}.operator-score-card.hong{background:#f6f5f8}.operator-score-card.winner{border:3px solid #0d192b;padding:14px}.operator-score-card>div{display:flex;justify-content:space-between;gap:5px}.operator-score-card>div span{color:#5e697a;font-size:11px;letter-spacing:1px}.operator-score-card>div b{background:#0d192b;color:white;padding:3px 7px;font-size:10px}.operator-score-card h2{font-size:27px;line-height:1.2;margin:10px 0 6px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.operator-big-score{display:block;font-size:clamp(48px,5vw,76px);font-weight:400;line-height:1.1;letter-spacing:2px;font-variant-numeric:tabular-nums}.operator-score-card small{font-size:10px;color:#657083;letter-spacing:1px}.operator-matrix{margin-top:16px;border:1px solid #c4c9d2}.operator-matrix-head{padding:9px;display:flex;justify-content:space-between;background:#edf1f9;border-bottom:1px solid #c4c9d2;font-size:11px}.operator-matrix-head span{background:#dce4f3;padding:1px 7px;font-family:monospace}.operator-table-wrap{overflow:auto}.operator-matrix table{width:100%;border-collapse:collapse;min-width:430px;text-align:left}.operator-matrix th{background:#dae1ee;color:#596579;font-size:11px;letter-spacing:.4px}.operator-matrix th,.operator-matrix td{padding:10px 12px;border-bottom:1px solid #e0e3ea}.operator-matrix td{font-family:monospace;font-size:13px}.operator-actions{display:flex;align-items:center;gap:8px;padding:16px 8px;border:1px solid #c4c9d2;border-top:0;flex-wrap:wrap}.operator-primary,.operator-secondary{min-height:44px;padding:10px 18px;display:inline-flex;align-items:center;justify-content:center;font-size:14px;font-weight:700}.operator-primary{border:1px solid #0d192b;background:#0d192b;color:white}.operator-secondary{border:1px solid #bfc6d2;background:#e4eaf5;color:#0d192b}.operator-action-note{font-size:11px;color:#81541e}.operator-details{margin-top:16px;border:1px solid #c4c9d2;background:#f7f8fc;padding:12px}.operator-details summary{font-weight:700;cursor:pointer}.operator-details:deep(article){margin-top:14px}.operator-events ol{list-style:none;padding:8px;margin:0;max-height:calc(100vh - 150px);overflow:auto}.operator-events li{display:grid;grid-template-columns:75px 1fr;gap:8px;padding:8px 2px;color:#334157}.operator-events time{color:#8b97a8;font-family:monospace}.operator-events li div{border-left:1px solid #cbd1db;padding-left:10px}.operator-events li strong{display:block;font-size:12px}.operator-events li small{color:#7c8798;font-size:10px}.operator-empty{padding:18px;color:#667184}.operator-center-empty,.operator-no-court{min-height:300px;display:grid;place-items:center}.operator-footer{height:40px;border-top:1px solid #c7ccd5;padding:0 16px;display:flex;justify-content:space-between;align-items:center;gap:16px;color:#5b6678;font:11px monospace;letter-spacing:.4px;font-family:"OperatorMixedMono",monospace,Tahoma,Arial,sans-serif}@media(max-width:1200px){.operator-columns{grid-template-columns:260px minmax(0,1fr)}.operator-events{grid-column:1/-1}.operator-events ol{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));max-height:220px}}@media(max-width:740px){.operator-topbar{height:auto;min-height:60px;flex-wrap:wrap;padding:10px}.operator-topbar nav{order:3;width:100%;height:36px}.operator-columns{display:flex;flex-direction:column;padding:10px}.operator-match-scroll{display:flex;max-height:180px}.operator-match{min-width:210px;border-right:1px solid #d8dce5}.operator-hardware{display:none}.operator-events ol{display:block}.operator-score-cards{gap:8px}.operator-score-card{min-height:160px;padding:9px}.operator-score-card.winner{padding:7px}.operator-score-card h2{font-size:18px}.operator-stage-head{flex-wrap:wrap}.operator-footer{height:auto;min-height:40px;flex-wrap:wrap;padding:8px;font-size:9px}}
@font-face{font-family:"OperatorMixed";src:local("Arial");font-style:normal;font-weight:400;unicode-range:U+0000-024F}@font-face{font-family:"OperatorMixed";src:local("Arial Bold");font-style:normal;font-weight:500 900;unicode-range:U+0000-024F}@font-face{font-family:"OperatorMixed";src:url("../../../../fonts/Vazirmatn-Variable.woff2") format("woff2");font-style:normal;font-weight:100 900;unicode-range:U+0600-06FF,U+0750-077F,U+08A0-08FF,U+FB50-FDFF,U+FE70-FEFF}@font-face{font-family:"OperatorMixedMono";src:local("Consolas"),local("Courier New");font-style:normal;font-weight:100 900;unicode-range:U+0000-024F}@font-face{font-family:"OperatorMixedMono";src:url("../../../../fonts/Vazirmatn-Variable.woff2") format("woff2");font-style:normal;font-weight:100 900;unicode-range:U+0600-06FF,U+0750-077F,U+08A0-08FF,U+FB50-FDFF,U+FE70-FEFF}.operator-workspace{height:100dvh;min-height:0;overflow:hidden;display:flex;flex-direction:column}.operator-topbar,.operator-footer{flex:none}.operator-topbar nav button{padding:0 16px;border:0;background:transparent;color:#566174;font-size:11px;letter-spacing:1.4px;font-weight:700;white-space:nowrap}.operator-columns{width:100%;flex:1;min-height:0;height:auto;overflow:hidden}.operator-match-list,.operator-events{min-height:0;display:flex;flex-direction:column}.operator-match-scroll,.operator-events ol{min-height:0;max-height:none;overflow:auto}.operator-center{min-height:0;overflow:auto;scrollbar-width:thin}.operator-judge-status{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:6px;margin-top:12px}.operator-judge{min-width:0;padding:8px;border:1px solid #c7ccd5;background:#f8f9fc;font-size:10px}.operator-judge strong,.operator-judge span,.operator-judge small{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.operator-judge strong{font-size:10px}.operator-judge span{margin:4px 0;color:#9b4f42}.operator-judge.connected span{color:#167651}.operator-judge small{color:#805c31}.operator-judge.submitted small{color:#167651}.operator-manual{min-height:44px;padding:10px 16px;border:1px solid #b7752b;background:#fff1da;color:#6c3f13;font-size:13px;font-weight:700}.operator-manual:hover{background:#ffe5bd}.operator-details-button{margin-top:12px;padding:10px 14px;border:1px solid #bfc6d2;background:#e6ebf4;font-weight:700}.operator-modal{width:min(1000px,94vw);max-height:90dvh;padding:0;border:1px solid #8e9bad;background:#f7f8fc;color:#0d192b;box-shadow:0 25px 80px #0d192b55}.operator-modal::backdrop{background:#091528b8}.operator-modal-head{display:flex;justify-content:space-between;align-items:center;gap:20px;padding:14px 18px;border-bottom:1px solid #c4c9d2}.operator-modal-head strong,.operator-modal-head small{display:block}.operator-modal-head small{color:#6b7585}.operator-modal-head button{border:0;background:transparent;font-size:26px;line-height:1}.operator-modal-body{max-height:calc(90dvh - 68px);overflow:auto;padding:16px}.operator-modal-body:deep(article){margin-bottom:14px}.operator-pages-modal{width:min(520px,94vw)}.operator-pages-list{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;padding:18px}.operator-pages-list a{padding:14px;border:1px solid #c7ccd5;background:#e9eef7;font-weight:700}.operator-pages-list a:hover{background:#dbe5f5}@media(max-width:1200px){.operator-events{grid-column:auto}.operator-columns{grid-template-columns:minmax(220px,280px) minmax(0,1fr) minmax(210px,260px)}.operator-events ol{display:block}.operator-score-card{min-height:170px}.operator-big-score{font-size:clamp(42px,4vw,62px)}}@media(max-width:850px){.operator-columns{grid-template-columns:minmax(190px,230px) minmax(0,1fr)}.operator-events{display:none}.operator-judge-status{grid-template-columns:repeat(3,minmax(0,1fr))}}@media(max-width:740px){.operator-columns{display:flex;flex-direction:column;overflow:hidden}.operator-match-list{flex:none;max-height:145px}.operator-match-scroll{max-height:none}.operator-center{flex:1}.operator-judge-status{grid-template-columns:repeat(2,minmax(0,1fr))}}
.operator-columns{transition:grid-template-columns 360ms cubic-bezier(.22,.8,.2,1)}
.operator-columns.events-collapsed:not(.matches-collapsed){grid-template-columns:minmax(240px,340px) minmax(420px,1fr) minmax(0,0fr)}
.operator-columns.matches-collapsed:not(.events-collapsed){grid-template-columns:minmax(170px,170px) minmax(420px,1fr) minmax(250px,340px)}
.operator-columns.matches-collapsed.events-collapsed{grid-template-columns:minmax(170px,170px) minmax(420px,1fr) minmax(0,0fr)}
.operator-match-head-actions{display:flex;align-items:center;gap:5px}
.operator-context{display:grid;grid-template-columns:minmax(0,1fr) auto minmax(0,1fr);align-items:center;gap:12px}
.operator-context .operator-context-number{justify-self:start;font-size:24px;font-weight:900}
.operator-context-event,.operator-context-form{min-width:0;font-size:14px;font-weight:700}
.operator-context-event{justify-self:center;text-align:center}
.operator-context-form{justify-self:end;text-align:right}
.operator-score-card.chung>div span{color:#2475bf}
.operator-score-card.hong>div span{color:#c44750}
.operator-score-card.winner>div span{font-size:15px;font-weight:900;letter-spacing:.6px}
.operator-match{transition:grid-template-columns 320ms cubic-bezier(.22,.8,.2,1),gap 320ms cubic-bezier(.22,.8,.2,1),padding 320ms cubic-bezier(.22,.8,.2,1),background-color 180ms ease,box-shadow 180ms ease}
.operator-match-number,.operator-match-status{transition:font-size 320ms ease,padding 320ms ease}
.operator-match strong{max-height:42px;font-size:16px;line-height:1.3;transition:max-height 320ms ease,font-size 320ms ease,line-height 320ms ease,opacity 180ms ease}
.operator-match small{max-height:24px;font-size:12px;font-weight:600;line-height:1.3;transition:max-height 320ms ease,font-size 320ms ease,line-height 320ms ease,opacity 180ms ease}
.operator-match.ready:hover,.operator-match.selected.ready{background:#fff9e7}
.operator-match.completed:hover,.operator-match.selected.completed{background:#edf7ef}
.operator-match.cancelled:hover,.operator-match.selected.cancelled{background:#fdf0f0}
.operator-match.live:hover,.operator-match.selected.live{background:#edf0f4}
.operator-match.selected.ready{box-shadow:inset 4px 0 #d6aa2f}
.operator-match.selected.completed{box-shadow:inset 4px 0 #54a16b}
.operator-match.selected.cancelled{box-shadow:inset 4px 0 #cf5a5a}
.operator-match.selected.live{box-shadow:inset 4px 0 #8490a0}
.operator-match.live .operator-match-number{font-size:16px;font-weight:900}
.operator-match.live .operator-match-status{font-size:12px;font-weight:900}
.operator-match.live strong{font-size:19px;font-weight:800}
.operator-match.live small{font-size:14px;font-weight:700}
.operator-panel-head.operator-match-head{width:100%;flex:none;color:inherit;text-align:left;cursor:pointer}
.operator-panel-head.operator-match-head:hover{background:#e3e9f4}
.operator-match-head-actions svg{width:13px;height:13px;transition:transform 160ms ease}
.operator-match-head-actions svg.reversed{transform:rotate(180deg)}
.operator-match-toggle{width:25px;height:25px;padding:0;display:grid;place-items:center;border:1px solid #c8cfda;background:#e2e7f2;color:#566174;cursor:pointer}
.operator-match-toggle:hover{background:#d6deeb;color:#0d192b}
.operator-match-toggle svg{width:13px;height:13px;transition:transform 160ms ease}
.operator-match-toggle svg.reversed{transform:rotate(180deg)}
.operator-match-content{min-width:0;min-height:0;display:flex;flex:1;flex-direction:column;opacity:1;transition:opacity 180ms ease,visibility 180ms ease}
.operator-match-list.match-list-collapsed{min-width:0;overflow:hidden}
.operator-match-list.match-list-collapsed .operator-match-content{overflow:hidden}
.operator-match-list.match-list-collapsed .operator-match-scroll{max-height:none}
.operator-match-list.match-list-collapsed .operator-match{grid-template-columns:minmax(0,1fr) auto;gap:3px 6px;padding:7px 6px}
.operator-match-list.match-list-collapsed .operator-match-number{font-size:10px;white-space:nowrap}
.operator-match-list.match-list-collapsed .operator-match small,.operator-match-list.match-list-collapsed .operator-match strong{max-height:0;margin:0;padding:0;overflow:hidden;opacity:0;font-size:0;line-height:0}
.operator-match-list.match-list-collapsed .operator-match-status{grid-column:2;justify-self:end;gap:3px;font-size:9px;padding:2px 5px;white-space:nowrap}
.operator-match-list.match-list-collapsed .operator-match.live .operator-match-number{font-size:14px;font-weight:900}
.operator-match-list.match-list-collapsed .operator-match.live .operator-match-status{font-size:11px;font-weight:900}
.operator-match-list.match-list-collapsed .operator-empty{padding:8px;font-size:10px}
.operator-judge-devices-toggle{width:100%;min-height:34px;padding:0 8px;display:flex;align-items:center;justify-content:space-between;border:1px solid #c4c9d2;background:#edf1f9;color:#5c687a;font-size:10px;font-weight:800;letter-spacing:.5px;text-align:left;cursor:pointer}
.operator-judge-devices-toggle:hover{background:#e3e9f4;color:#0d192b}
.operator-judge-pills span{border-width:2px;border-color:#586779}
.operator-judge-pills .online{border-color:#087548;background:#d4ecdf}
.operator-judge-devices-indicator{width:8px;height:8px;flex:none;border-radius:50%}
.operator-judge-devices-indicator.online{background:#19815d;box-shadow:0 0 0 2px #19815d26}
.operator-judge-devices-indicator.partial{background:#d3a000;box-shadow:0 0 0 2px #d3a00030}
.operator-judge-devices-indicator.offline{background:#d94747;box-shadow:0 0 0 2px #d9474726}
.operator-judge-devices-toggle svg{width:13px;height:13px;transition:transform 160ms ease}
.operator-judge-devices-toggle svg.reversed{transform:rotate(90deg)}
.operator-events{opacity:1;transition:opacity 180ms ease,visibility 180ms ease}
.operator-events.events-hidden{overflow:hidden;min-width:0;border-width:0;opacity:0;visibility:hidden;pointer-events:none}
.operator-events-toggle{display:inline-flex;align-items:center;gap:5px;min-height:30px;padding:0 8px;border:1px solid #c8cfda;background:#e2e7f2;color:#566174;font-size:9px;font-weight:700;letter-spacing:.5px;cursor:pointer}
.operator-events-toggle:hover{background:#d6deeb;color:#0d192b}
.operator-events-toggle svg{width:13px;height:13px;transition:transform 160ms ease}
.operator-events-toggle svg.reversed{transform:rotate(180deg)}
.operator-match-status{display:inline-flex;align-items:center;justify-self:end;gap:5px;padding:3px 8px;border:1px solid transparent;border-radius:999px;font-weight:800;letter-spacing:.35px}
.operator-match-status.ready{color:#916400;background:#fff3c4;border-color:#f1d779}
.operator-match-status.completed{color:#167651;background:#e4f3e9;border-color:#a8d5b5}
.operator-match-status.cancelled{color:#ad3636;background:#fde8e8;border-color:#edb5b5}
.operator-match-status.live{color:#54657b;background:#e8ebf1;border-color:#ccd3df}
.operator-match-live-dot{width:7px;height:7px;flex:none;border-radius:50%;background:#19a66a;box-shadow:0 0 0 2px #19a66a2e;animation:operator-live-pulse 1.6s ease-out infinite}
@keyframes operator-live-pulse{50%{box-shadow:0 0 0 4px #19a66a12}}
.operator-judge-status .operator-judge small{margin-top:6px;font-size:11px;font-weight:800;letter-spacing:.35px}
.operator-judge-status .operator-judge.submitted small{color:#167651}
.operator-judge-status .operator-judge.review-required small{color:#a16a22}
.operator-judge-status .operator-judge.pending small{color:#667184}
@media(max-width:850px){.operator-events-toggle{display:none}}
@media(max-width:740px){.operator-match-list{transition:max-height 280ms cubic-bezier(.2,.7,.2,1)}.operator-match-list.match-list-collapsed{max-height:145px}.operator-match-list.match-list-collapsed .operator-match-scroll{display:block}.operator-hardware{display:block}}
@media(max-width:740px){.operator-context{grid-template-columns:auto minmax(0,1fr);gap:6px 10px}.operator-context .operator-context-number{grid-column:1;grid-row:1}.operator-context-event{grid-column:2;grid-row:1;justify-self:end;text-align:right}.operator-context-form{grid-column:1/-1;grid-row:2;justify-self:end}}
.operator-center{display:flex;flex-direction:column;overflow:hidden;scrollbar-width:none}
.operator-stage-head{position:sticky;top:0;z-index:3;flex:none}
.operator-stage-content{flex:1;min-height:0;overflow-x:hidden;overflow-y:auto;scrollbar-width:thin}
.operator-score-card{position:relative}
.operator-score-card h2{margin:0 0 4px;text-align:center;font-size:clamp(25px,2.5vw,34px);font-weight:800;line-height:1.15}
.operator-card-header{display:flex;position:relative;justify-content:center;align-items:center;min-height:22px}
.operator-card-header span{font-size:13px;font-weight:900;letter-spacing:1px}
.operator-card-header b{position:absolute;right:0;background:#0d192b;color:white;padding:3px 7px;font-size:10px}
.operator-card-scores{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);align-items:stretch;gap:14px;margin-top:8px}
.operator-card-components{display:grid;grid-template-rows:1fr 1fr;gap:8px;min-width:0;text-align:center}
.operator-card-components>div,.operator-card-total{display:flex;flex-direction:column;align-items:center;justify-content:center;min-width:0}
.operator-card-components>div{padding:5px 9px;background:#ffffff75}
.operator-card-components small,.operator-card-total small{color:#657083;font-size:10px;font-weight:800;letter-spacing:.5px}
.operator-card-components strong{font-family:"OperatorMixedMono",monospace;font-size:clamp(27px,3vw,38px);font-weight:900;line-height:1.1;font-variant-numeric:tabular-nums}
.operator-card-total{border-left:0;padding-left:0;text-align:center}
.operator-card-total .operator-big-score{font-size:clamp(52px,5.5vw,78px);font-weight:800;letter-spacing:0}
.operator-score-card.chung .operator-card-components>div{color:#2475bf}
.operator-score-card.hong .operator-card-components>div{color:#c44750}
.operator-steps{gap:22px}
.operator-steps button{position:relative;min-width:78px;padding:9px 14px;border:0;background:#e1e6f0;color:#798291;font:inherit;font-size:10px;font-weight:800;text-align:center;cursor:pointer}
.operator-steps button:not(:first-child)::before{position:absolute;top:50%;left:-20px;transform:translateY(-50%);color:#7b8799;font-size:15px;font-weight:600;content:"→";pointer-events:none}
.operator-steps button:hover{background:#d2dbea;color:#0d192b}
.operator-steps button:focus-visible{outline:2px solid #2475bf;outline-offset:2px}
.operator-steps button.current:not(.active){box-shadow:inset 0 -2px #19a66a;color:#167651}
.operator-steps button.current::after{position:absolute;top:2px;right:2px;width:5px;height:5px;border-radius:50%;background:#19a66a;content:""}
.operator-steps button.active{background:#0d192b;color:#fff}
.operator-steps button.active.current::after{background:#52db9d}
@media(max-width:740px){.operator-score-card h2{font-size:clamp(21px,6vw,27px)}.operator-card-scores{gap:8px;margin-top:8px}.operator-card-components>div{padding:4px 6px}.operator-card-components strong{font-size:clamp(23px,6vw,30px)}.operator-card-total{padding-left:0}.operator-card-total .operator-big-score{font-size:clamp(42px,11vw,58px)}.operator-steps{width:100%;justify-content:space-between;gap:14px}.operator-steps button{min-width:0;padding:8px 9px;font-size:9px}.operator-steps button:not(:first-child)::before{left:-13px;font-size:12px}}
@media(prefers-reduced-motion:reduce){.operator-columns,.operator-events,.operator-match-content,.operator-match-list,.operator-match,.operator-match-number,.operator-match-status,.operator-match strong,.operator-match small,.operator-events-toggle svg,.operator-match-toggle svg,.operator-match-head-actions svg,.operator-judge-devices-toggle svg{transition:none}}
</style>
