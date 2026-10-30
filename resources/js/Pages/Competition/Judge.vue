<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { Head, Link, usePage, usePoll } from '@inertiajs/vue3';
import { useCompetitionUpdates } from '../../Shared/Composables/useCompetitionUpdates';
import { useJudgePresence } from '../../Shared/Composables/useJudgePresence';
import { usePerformanceClock } from '../../Shared/Composables/usePerformanceTiming';
import { elapsed } from '../../Shared/Services/display';
import JudgeScoreForm from '../../Components/competition/judge/JudgeScoreForm.vue';

const props = defineProps({ tournament: Object });
const page = usePage();
const connection = useCompetitionUpdates(props.tournament.id);
useJudgePresence(props.tournament.id);
const historyDialog = ref(null);
const selectedId = ref(null);
const focused = ref(false);
const summaries = ref({});
const lastSync = ref(Date.now());
const online = ref(true);
const heartbeat = ref(Date.now());
let heartbeatTimer;
const base = computed(() => `${page.props.urls.tournaments}/${props.tournament.id}`);
const active = computed(() => props.tournament.categories.flatMap(category => category.rounds.flatMap(round => round.bouts.flatMap(bout => bout.performances.filter(performance => performance.is_assigned && ['running', 'scoring'].includes(performance.status)).map(performance => ({ category, round, bout, performance }))))));
const current = computed(() => active.value[0] ?? null);
const sideOf = item => item.bout.entries.find(entry => entry.id === item.performance.entry_id)?.side ?? null;
const athlete = item => item.bout.entries.find(entry => entry.id === item.performance.entry_id)?.name ?? 'Athlete';
const court = item => props.tournament.courts.find(value => value.id === item.bout.court_id)?.name ?? 'Court';
const sides = computed(() => {
    if (!current.value) return [];
    const item = current.value;
    if (item.category.execution_mode !== 'simultaneous' || item.bout.entries.length !== 2) return [item];
    return item.bout.performances
        .filter(performance => performance.form_number === item.performance.form_number && performance.is_assigned)
        .map(performance => ({ category: item.category, round: item.round, bout: item.bout, performance }))
        .sort((left, right) => (sideOf(left) === 'chung' ? 0 : 1) - (sideOf(right) === 'chung' ? 0 : 1));
});
const paired = computed(() => sides.value.length === 2);
watch(() => sides.value.map(item => item.performance.id).join(','), () => {
    if (!sides.value.some(item => item.performance.id === selectedId.value)) selectedId.value = sides.value[0]?.performance.id ?? null;
}, { immediate: true });
watch(() => sides.value.map(item => item.performance.status).join(','), () => {
    if (sides.value.find(item => item.performance.id === selectedId.value)?.performance.status === 'approved') {
        selectedId.value = sides.value.find(item => item.performance.status === 'scoring')?.performance.id ?? selectedId.value;
    }
});
const clockPerformance = computed(() => sides.value.find(item => item.performance.status === 'running')?.performance ?? sides.value.find(item => item.performance.started_at)?.performance ?? null);
const serverNow = usePerformanceClock(() => clockPerformance.value?.server_now);
const clock = computed(() => clockPerformance.value?.started_at ? elapsed(clockPerformance.value, serverNow.value) : '00:00');
const clockRunning = computed(() => sides.value.some(item => item.performance.status === 'running'));
const approved = computed(() => props.tournament.categories.flatMap(category => category.rounds.filter(round => round.score_correction_open).flatMap(round => round.bouts.flatMap(bout => bout.performances.filter(performance => performance.is_assigned && performance.status === 'approved' && performance.own_score).map(performance => ({ category, round, bout, performance }))))));
const offline = computed(() => !online.value || heartbeat.value - lastSync.value > 10000);
const syncState = computed(() => offline.value ? 'offline' : connection.value === 'connected' ? 'connected' : 'polling');
const setOnline = () => { online.value = navigator.onLine; };
onMounted(() => {
    setOnline();
    window.addEventListener('online', setOnline);
    window.addEventListener('offline', setOnline);
    heartbeatTimer = window.setInterval(() => { heartbeat.value = Date.now(); }, 1000);
});
onUnmounted(() => {
    window.removeEventListener('online', setOnline);
    window.removeEventListener('offline', setOnline);
    window.clearInterval(heartbeatTimer);
});
usePoll(2000, { only: ['tournament'], onSuccess: () => { lastSync.value = Date.now(); setOnline(); } });
</script>

<template>
    <div class="judge-station" dir="ltr">
        <Head :title="`Judge · ${props.tournament.name}`" />
        <header class="station-top">
            <div class="station-identity"><span class="station-mark" aria-hidden="true">P</span><div><small>JUDGE CONSOLE</small><strong>{{ page.props.auth.user.name }}</strong></div></div>
            <div class="station-clock" :class="{ ticking: clockRunning }" role="timer" :aria-label="`Performance time ${clock}`"><span class="clock-dot"></span><strong>{{ clock }}</strong></div>
            <div class="station-meta"><span v-if="current">{{ court(current) }} <b>·</b> MATCH {{ current.bout.sequence }}</span><span v-else>STANDBY</span><Link :href="page.props.urls.logout" method="post" as="button" class="exit" aria-label="Sign out">⏻</Link></div>
        </header>

        <div v-if="offline" class="offline-banner" role="alert">Connection lost. Your unsent scores stay on this device. Reconnect before sending.</div>
        <template v-if="current">
            <div class="match-bar"><div class="match-context"><span class="eyebrow">{{ current.category.discipline === 'freestyle' ? 'FREESTYLE' : paired ? 'HEAD TO HEAD' : 'INDIVIDUAL' }} <i>·</i> {{ current.category.display_label || current.category.name }}</span><strong>{{ current.performance.form_name }}</strong><span class="round-name">{{ current.round.name }}</span></div><span class="match-stage" :class="clockRunning ? 'live' : 'scoring'"><i></i>{{ clockRunning ? 'LIVE PERFORMANCE' : 'SCORING OPEN' }}</span></div>
            <nav v-if="paired" class="side-tabs" aria-label="Select athlete to score"><button v-for="(item, index) in sides" :key="item.performance.id" type="button" class="side-tab" :class="[sideOf(item) || (index === 0 ? 'chung' : 'hong'), { selected: selectedId === item.performance.id }]" :aria-pressed="selectedId === item.performance.id" @click="selectedId = item.performance.id"><span class="side-tag">{{ sideOf(item) === 'hong' ? 'HONG · RED' : 'CHUNG · BLUE' }}</span><strong>{{ athlete(item) }}</strong><span class="side-summary">{{ item.performance.status === 'approved' ? '✓ Done' : summaries[item.performance.id]?.received ? '✓ Sent' : summaries[item.performance.id]?.total ?? '—' }}</span></button><button type="button" class="focus-toggle" :aria-pressed="focused" @click="focused = !focused">{{ focused ? 'Split view' : 'Focus view' }}</button></nav>
            <main class="station-main" :class="{ dual: paired, focused }">
                <article v-for="(item, index) in sides" :key="item.performance.id" class="athlete-card" :class="[paired ? sideOf(item) || (index === 0 ? 'chung' : 'hong') : 'solo', { selected: selectedId === item.performance.id }]">
                    <header class="athlete-header"><div class="athlete-details"><span class="athlete-side">{{ paired ? sideOf(item) === 'hong' ? 'HONG / RED' : 'CHUNG / BLUE' : item.category.discipline === 'freestyle' ? 'FREESTYLE' : 'ATHLETE' }}</span><h1>{{ athlete(item) }}</h1></div><span class="performance-state" :class="item.performance.status">{{ item.performance.status === 'running' ? 'LIVE' : item.performance.status === 'approved' ? 'CONFIRMED' : 'READY TO SEND' }}</span></header>
                    <JudgeScoreForm v-if="['running', 'scoring'].includes(item.performance.status)" :performance="item.performance" :rules="item.category.rules" :endpoint="`${base}/performances/${item.performance.id}/scores`" :paired="paired" :offline="offline" :allow-submit="item.performance.status === 'scoring'" @summary="summaries[item.performance.id] = $event" />
                    <div v-else class="side-wait"><strong>✓ Confirmed</strong><span>This athlete's score is complete. Continue with the other athlete.</span></div>
                </article>
            </main>
        </template>
        <main v-else class="station-idle"><span class="idle-mark">P</span><strong>Ready for the next performance</strong><p>Your assigned athlete appears here when the operator starts the match.</p></main>
        <footer class="station-bottom"><span class="connection" :class="syncState"><i></i>{{ syncState === 'offline' ? 'OFFLINE' : syncState === 'connected' ? 'LIVE SYNC' : 'SYNCING' }}</span><span class="tournament-name">{{ props.tournament.name }}</span><button v-if="approved.length" type="button" @click="historyDialog?.showModal()">Previous scores <b>{{ approved.length }}</b></button></footer>
        <dialog ref="historyDialog" class="history-dialog" @click="event => { if (event.target === historyDialog) historyDialog.close(); }"><header><strong>PREVIOUS SCORES</strong><button type="button" aria-label="Close previous scores" @click="historyDialog.close()">×</button></header><div class="history-body"><details v-for="item in approved" :key="item.performance.id"><summary>{{ athlete(item) }} · {{ item.performance.form_name }}</summary><p v-if="item.performance.appeal_pending">APPEAL PENDING</p><JudgeScoreForm v-else :performance="item.performance" :rules="item.category.rules" :endpoint="`${base}/performances/${item.performance.id}/scores`" :offline="offline" /></details></div></dialog>
    </div>
</template>

<style scoped>
.judge-station{--blue:#53b9ff;--red:#fa8e99;min-height:0;height:100dvh;overflow:hidden;display:flex;flex-direction:column;background:#091522;color:#f2f6fb;font-family:Inter,Arial,Tahoma,sans-serif;line-height:1.4}
.station-top{height:62px;flex:none;display:grid;grid-template-columns:1fr auto 1fr;align-items:center;gap:12px;padding:0 16px;border-bottom:1px solid #26384d;background:#0b1929}.station-identity{display:flex;align-items:center;gap:10px;min-width:0}.station-mark{width:36px;height:36px;display:grid;place-items:center;border-radius:10px;background:#e8cd96;color:#122032;font-size:23px;font-weight:900}.station-identity div{min-width:0;display:flex;flex-direction:column}.station-identity small{color:#8fa9c2;font-size:9px;font-weight:750;letter-spacing:.14em}.station-identity strong{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:15px}.station-clock{min-width:112px;display:flex;align-items:center;justify-content:center;gap:8px;padding:5px 12px;border:1px solid #38526d;border-radius:10px;background:#14273c}.station-clock strong{font-size:27px;font-weight:750;font-variant-numeric:tabular-nums;line-height:1}.clock-dot{width:8px;height:8px;border-radius:50%;background:#778ba0}.ticking .clock-dot{background:#72e0ae;box-shadow:0 0 0 4px #72e0ae22}.station-meta{display:flex;align-items:center;justify-content:flex-end;gap:12px;min-width:0;font-size:11px;font-weight:700;color:#a9bed0}.station-meta b{color:#e8cd96}.exit{width:34px;height:34px;display:grid;place-items:center;border:1px solid #405b75;border-radius:8px;color:#c5d5e5;font-size:19px}.exit:focus-visible{outline:2px solid #8ed8ff}.offline-banner{padding:7px 16px;background:#603728;border-bottom:1px solid #a8644e;color:#ffe8dc;font-size:11px;text-align:center}
.match-bar{flex:none;min-height:66px;display:flex;align-items:center;justify-content:space-between;gap:12px;padding:8px 18px;border-bottom:1px solid #223447;background:#101f31}.match-context{display:flex;flex-direction:column;min-width:0}.eyebrow{font-size:10px;font-weight:800;letter-spacing:.1em;color:#e7c88e}.eyebrow i{font-style:normal;margin:0 4px;color:#647e99}.match-context strong{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:17px;font-weight:700}.round-name{font-size:10px;color:#8ea5bb}.match-stage{flex:none;display:flex;align-items:center;gap:7px;padding:6px 10px;border:1px solid #987742;border-radius:7px;background:#3d331f;color:#f1d394;font-size:10px;font-weight:800;letter-spacing:.04em}.match-stage.live{border-color:#3d816b;background:#153d34;color:#8be8bd}.match-stage i,.connection i{width:7px;height:7px;border-radius:50%;background:currentColor}.side-tabs{display:flex;align-items:stretch;gap:6px;padding:7px 10px 0;background:#091522}.side-tab{flex:1;min-width:0;min-height:55px;padding:6px 12px;display:flex;align-items:center;gap:10px;text-align:left;border:1px solid #324459;border-radius:9px 9px 0 0;background:#122237;color:#d5e4f2}.side-tab.selected{background:#1d3248;border-bottom-color:#1d3248}.side-tab.chung.selected{border-color:#4baeff;border-bottom-color:#1d3248}.side-tab.hong.selected{border-color:#f28e98;border-bottom-color:#1d3248}.side-tag{font-size:9px;font-weight:800;letter-spacing:.08em;white-space:nowrap}.chung .side-tag{color:var(--blue)}.hong .side-tag{color:var(--red)}.side-tab strong{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:13px}.side-summary{margin-left:auto;color:#e8cd96;font-weight:750;white-space:nowrap}.focus-toggle{padding:4px 12px;border:1px solid #3a526a;border-radius:8px;background:#13273c;color:#c4d6e7;font-size:11px;font-weight:700;white-space:nowrap}
.station-main{flex:1;min-height:0;display:grid;grid-template-columns:1fr;padding:10px;gap:10px}.station-main.dual{grid-template-columns:repeat(2,minmax(0,1fr))}.athlete-card{min-width:0;min-height:0;display:flex;flex-direction:column;overflow:hidden;border:1px solid #30465f;border-radius:13px;background:#0e1d30;box-shadow:0 9px 25px #030a1460}.athlete-card.chung{border-top:3px solid var(--blue)}.athlete-card.hong{border-top:3px solid var(--red)}.athlete-card.solo{border-top:3px solid #e5c78e}.athlete-header{flex:none;min-height:63px;display:flex;align-items:center;justify-content:space-between;gap:10px;padding:8px 15px;border-bottom:1px solid #27394e}.athlete-details{min-width:0}.athlete-side{font-size:9px;font-weight:800;letter-spacing:.1em;color:#a9bbcb}.chung .athlete-side{color:var(--blue)}.hong .athlete-side{color:var(--red)}.athlete-header h1{margin:0;font-size:22px;font-weight:750;line-height:1.2;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.performance-state{flex:none;padding:5px 9px;border:1px solid #50705e;border-radius:6px;color:#96dfb5;font-size:9px;font-weight:800;letter-spacing:.07em}.performance-state.scoring{border-color:#9c7e49;color:#f1d399}.station-idle{flex:1;min-height:0;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:25px;background:radial-gradient(circle at center,#193550,#091522 70%)}.idle-mark{width:84px;height:84px;display:grid;place-items:center;border:1px solid #a88b5f;border-radius:20px;color:#e5c78e;font-size:56px;font-weight:800;margin-bottom:20px}.station-idle strong{font-size:22px}.station-idle p{font-size:13px;color:#a3b6c8}
.side-wait{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;text-align:center;color:#8ed8b4;padding:18px}.side-wait strong{font-size:25px}.side-wait span{color:#a7bbcf;font-size:12px}
.station-bottom{height:30px;flex:none;display:flex;align-items:center;justify-content:space-between;gap:8px;padding:0 16px;border-top:1px solid #23354a;background:#0b1929;color:#8ea5bd;font-size:10px;font-weight:700}.connection{display:flex;align-items:center;gap:6px;color:#e4c685}.connection.connected{color:#84e0ae}.connection.offline{color:#f0a18e}.station-bottom button{padding:3px 9px;border:1px solid #3e5872;border-radius:5px;color:#d8e6f3;font-size:10px}.station-bottom button b{margin-left:5px;color:#e6c98e}.history-dialog{width:min(980px,96vw);max-height:90dvh;padding:0;border:1px solid #607e98;border-radius:16px;background:#0b1829;color:#fff}.history-dialog::backdrop{background:#030913d9}.history-dialog>header{display:flex;justify-content:space-between;align-items:center;padding:12px 18px;border-bottom:1px solid #354a60}.history-dialog>header button{font-size:29px}.history-body{max-height:calc(90dvh - 56px);overflow:auto;padding:14px}.history-body details{margin-bottom:9px;padding:12px;border:1px solid #41566e;border-radius:10px}.history-body summary{cursor:pointer;font-weight:700}.history-body details :deep(form){margin-top:12px;min-height:350px}
@media(max-width:1099px), (max-height:599px){.station-main.dual,.station-main.dual.focused{grid-template-columns:1fr}.station-main.dual .athlete-card:not(.selected){display:none}.focus-toggle{display:none}}
@media(min-width:1100px) and (min-height:600px){.station-main.dual.focused{grid-template-columns:1fr}.station-main.dual.focused .athlete-card:not(.selected){display:none}}
@media(max-width:700px) and (orientation:landscape){.station-top{height:43px;padding:0 8px;gap:5px}.station-mark{width:29px;height:29px;font-size:19px}.station-identity{gap:6px}.station-identity small{font-size:8px}.station-identity strong{font-size:12px}.station-clock{min-width:78px;padding:3px 6px}.station-clock strong{font-size:19px}.station-meta{gap:5px;font-size:9px}.station-meta>span{display:none}.exit{width:29px;height:29px}.match-bar{min-height:44px;padding:4px 10px}.eyebrow{font-size:8px}.match-context strong{font-size:13px}.round-name{display:none}.match-stage{font-size:8px;padding:4px 6px}.side-tabs{padding:4px 5px 0;gap:4px}.side-tab{min-height:43px;padding:4px 8px;gap:5px}.side-tag{font-size:8px}.side-tab strong{font-size:11px}.side-summary{font-size:10px}.station-main{padding:5px;gap:5px}.athlete-card{border-radius:8px}.athlete-header{min-height:39px;padding:4px 10px}.athlete-side{font-size:8px}.athlete-header h1{font-size:15px}.performance-state{font-size:8px}.station-bottom{height:24px;padding:0 8px;font-size:9px}.tournament-name{display:none}}
@media(max-width:560px) and (orientation:portrait){.station-top{padding:0 9px}.station-meta>span{display:none}.match-bar{padding:7px 10px}.match-stage{font-size:8px}.station-main{padding:5px}.side-tabs{padding:5px 5px 0}.side-tab{flex-direction:column;align-items:flex-start;gap:2px;min-height:63px}.side-summary{position:absolute;right:14px}.athlete-header h1{font-size:18px}}
@media(max-height:500px) and (orientation:landscape){.station-top{height:36px}.match-bar{min-height:34px;padding-top:2px;padding-bottom:2px}.side-tab{min-height:44px}.side-tabs{padding-top:2px}.station-main{padding:3px}.station-main.dual .athlete-header{display:none}.athlete-header{min-height:32px;padding:2px 9px}.station-bottom{height:20px}}
</style>
