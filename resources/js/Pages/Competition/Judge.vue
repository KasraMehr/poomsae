<script setup>
import { computed, ref } from 'vue';
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
const base = computed(() => page.props.urls.tournaments + '/' + props.tournament.id);
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
const clockPerformance = computed(() => sides.value.find(item => item.performance.status === 'running')?.performance ?? sides.value.find(item => item.performance.started_at)?.performance ?? null);
const serverNow = usePerformanceClock(() => clockPerformance.value?.server_now);
const clock = computed(() => clockPerformance.value?.started_at ? elapsed(clockPerformance.value, serverNow.value) : '00:00');
const clockRunning = computed(() => sides.value.some(item => item.performance.status === 'running'));
const approved = computed(() => props.tournament.categories.flatMap(category => category.rounds.filter(round => round.score_correction_open).flatMap(round => round.bouts.flatMap(bout => bout.performances.filter(performance => performance.is_assigned && performance.status === 'approved' && performance.own_score).map(performance => ({ category, round, bout, performance }))))));
usePoll(2000, { only: ['tournament'] });
</script>

<template>
    <div class="judge-station" dir="ltr">
        <Head :title="`Judge · ${props.tournament.name}`" />
        <header class="station-top">
            <div class="station-identity"><span class="station-mark">P</span><div><small>JUDGE</small><strong>{{ page.props.auth.user.name }}</strong></div></div>
            <div class="station-clock" :class="{ ticking: clockRunning }" role="timer" :aria-label="`Performance time ${clock}`"><span class="clock-dot"></span><strong>{{ clock }}</strong></div>
            <div class="station-meta"><span v-if="current">{{ court(current) }} <b>·</b> MATCH {{ current.bout.sequence }}</span><span v-else>STANDBY</span><Link :href="page.props.urls.logout" method="post" as="button" class="exit" aria-label="Sign out">⏻</Link></div>
        </header>

        <main v-if="current" class="station-main" :class="{ dual: paired }">
            <article v-for="(item, index) in sides" :key="item.performance.id" class="athlete-card" :class="[paired ? sideOf(item) || (index === 0 ? 'chung' : 'hong') : 'solo']">
                <div class="athlete-header"><div class="athlete-details"><span class="athlete-side">{{ paired ? sideOf(item) === 'hong' ? 'HONG / RED' : 'CHUNG / BLUE' : item.category.display_label || item.category.name }}</span><h1>{{ athlete(item) }}</h1><span class="athlete-form">{{ item.performance.form_name }} <b>·</b> {{ item.round.name }}</span></div><span class="performance-state" :class="item.performance.status">{{ item.performance.status === 'running' ? 'LIVE' : item.performance.status === 'scoring' ? 'SCORE' : item.performance.status === 'approved' ? 'DONE' : 'WAIT' }}</span></div>
                <JudgeScoreForm v-if="['running', 'scoring'].includes(item.performance.status)" :performance="item.performance" :rules="item.category.rules" :endpoint="`${base}/performances/${item.performance.id}/scores`" :paired="paired" :allow-submit="item.performance.status === 'scoring'" />
                <div v-else class="side-wait">{{ item.performance.status === 'approved' ? '✓' : '◷' }}</div>
            </article>
        </main>
        <main v-else class="station-idle"><span>◷</span><strong>STANDBY</strong></main>

        <footer class="station-bottom"><span class="connection" :class="connection">● {{ connection === 'connected' ? 'LIVE' : connection === 'stale' ? 'OFFLINE' : 'SYNC' }}</span><span>{{ current?.performance.form_name || props.tournament.name }}</span><button v-if="approved.length" type="button" @click="historyDialog?.showModal()">HISTORY <b>{{ approved.length }}</b></button></footer>
        <dialog ref="historyDialog" class="history-dialog" @click="event => { if (event.target === historyDialog) historyDialog.close(); }"><header><strong>PREVIOUS SCORES</strong><button type="button" aria-label="Close previous scores" @click="historyDialog.close()">×</button></header><div class="history-body"><details v-for="item in approved" :key="item.performance.id"><summary>{{ athlete(item) }} · {{ item.performance.form_name }}</summary><p v-if="item.performance.appeal_pending">APPEAL PENDING</p><JudgeScoreForm v-else :performance="item.performance" :rules="item.category.rules" :endpoint="`${base}/performances/${item.performance.id}/scores`" /></details></div></dialog>
    </div>
</template>

<style scoped>
.judge-station{--blue:#2498f4;--red:#f16b74;height:100dvh;overflow:hidden;display:flex;flex-direction:column;background:#080f1b;color:#f7f9fd;font-family:Inter,Arial,Tahoma,sans-serif}.station-top{height:75px;flex:none;display:grid;grid-template-columns:1fr auto 1fr;align-items:center;gap:12px;padding:0 18px;border-bottom:1px solid #29364b;background:#0c1727}.station-identity{display:flex;align-items:center;gap:12px;min-width:0}.station-mark{width:40px;height:40px;display:grid;place-items:center;border:2px solid #f2c958;border-radius:10px;color:#f2c958;font-size:27px;font-weight:950}.station-identity div{min-width:0;display:flex;flex-direction:column}.station-identity small{color:#9caec4;font-size:10px;font-weight:900;letter-spacing:.18em}.station-identity strong{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:20px}.station-clock{min-width:158px;display:flex;align-items:center;justify-content:center;gap:11px;padding:5px 17px;border:1px solid #3a4a60;border-radius:12px;background:#142338}.station-clock strong{font-size:34px;font-weight:850;font-variant-numeric:tabular-nums;letter-spacing:.05em;line-height:1}.clock-dot{width:9px;height:9px;border-radius:50%;background:#697c91}.ticking .clock-dot{background:#66e0aa;box-shadow:0 0 0 5px #66e0aa22}.station-meta{display:flex;align-items:center;justify-content:flex-end;gap:15px;min-width:0;font-size:12px;font-weight:900;letter-spacing:.06em;color:#b7c8d8}.station-meta b{color:#f2c958}.exit{width:38px;height:38px;display:grid;place-items:center;border:1px solid #465a70;border-radius:9px;color:#b9c8d9;font-size:22px}.station-main{flex:1;min-height:0;display:grid;grid-template-columns:1fr;padding:12px;gap:12px}.station-main.dual{grid-template-columns:repeat(2,minmax(0,1fr))}.athlete-card{min-width:0;min-height:0;display:flex;flex-direction:column;gap:10px;overflow:auto;padding:12px;border:2px solid #46607e;border-radius:20px;background:linear-gradient(145deg,#14253c,#0a1728 60%)}.athlete-card.chung{border-color:var(--blue);box-shadow:inset 0 4px #2498f455}.athlete-card.hong{border-color:var(--red);box-shadow:inset 0 4px #f16b7455}.athlete-card.solo{border-color:#6587a6}.athlete-header{min-height:70px;display:flex;align-items:center;justify-content:space-between;gap:10px;padding:3px 6px}.athlete-details{min-width:0}.athlete-side{font-size:11px;font-weight:950;letter-spacing:.15em;color:#b4c5d6}.chung .athlete-side{color:#6cbcff}.hong .athlete-side{color:#ff9fa6}.athlete-header h1{margin:2px 0;font-size:clamp(23px,3vw,43px);line-height:1.06;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.athlete-form{font-size:12px;font-weight:700;color:#a8b9ca}.athlete-form b{color:#f2c958}.performance-state{padding:8px 13px;border:1px solid #4a6279;border-radius:9px;font-size:11px;font-weight:950;letter-spacing:.12em}.performance-state.running{border-color:#318b6b;color:#80efbf;background:#133e32}.performance-state.scoring{border-color:#e6b653;color:#f3d075;background:#40341c}.side-wait{flex:1;display:grid;place-items:center;font-size:70px;color:#85a5bd}.station-idle{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;background:radial-gradient(circle at center,#172d47,#080f1b 70%)}.station-idle span{font-size:90px;color:#f0c858}.station-idle strong{font-size:25px;letter-spacing:.25em}.station-bottom{height:41px;flex:none;display:flex;align-items:center;justify-content:space-between;gap:8px;padding:0 18px;border-top:1px solid #29364b;background:#0c1727;color:#91a9be;font-size:10px;font-weight:900;letter-spacing:.08em}.connection{color:#e9c361}.connection.connected{color:#75e1ae}.connection.stale{color:#f58978}.station-bottom button{padding:5px 10px;border:1px solid #50667b;border-radius:6px;color:#eaf0f6;font-size:10px;font-weight:900}.station-bottom button b{margin-left:5px;color:#f3cc64}.history-dialog{width:min(980px,96vw);max-height:90dvh;padding:0;border:1px solid #607e98;border-radius:16px;background:#0b1829;color:#fff}.history-dialog::backdrop{background:#030913d9}.history-dialog>header{display:flex;justify-content:space-between;align-items:center;padding:12px 18px;border-bottom:1px solid #354a60}.history-dialog>header button{font-size:29px}.history-body{max-height:calc(90dvh - 56px);overflow:auto;padding:14px}.history-body details{margin-bottom:9px;padding:12px;border:1px solid #41566e;border-radius:10px}.history-body summary{cursor:pointer;font-weight:900}.history-body details :deep(form){margin-top:12px}@media(max-width:850px) and (orientation:landscape){.station-top{height:52px;padding:0 8px;gap:5px}.station-mark{width:31px;height:31px;font-size:21px}.station-identity{gap:6px}.station-identity small{font-size:8px}.station-identity strong{font-size:13px}.station-clock{min-width:100px;padding:3px 7px;gap:5px}.station-clock strong{font-size:22px}.station-meta{gap:6px;font-size:9px}.exit{width:31px;height:31px;font-size:18px}.station-main{padding:5px;gap:5px}.athlete-card{padding:5px;gap:5px;border-radius:11px}.athlete-header{min-height:39px;padding:0 4px}.athlete-side{font-size:8px}.athlete-header h1{font-size:18px;margin:1px 0}.athlete-form{font-size:9px}.performance-state{padding:5px;font-size:8px}.station-bottom{height:28px;padding:0 8px;font-size:8px}}@media(max-width:650px) and (orientation:portrait){.judge-station{height:100dvh}.station-main{overflow:auto;display:flex;flex-direction:column}.station-main.dual{display:flex}.athlete-card{min-height:500px;overflow:visible}.station-meta>span{display:none}.station-clock{min-width:108px}.station-clock strong{font-size:24px}.station-identity strong{font-size:15px}}
@media(max-width:900px) and (orientation:landscape) and (max-height:500px){.athlete-details{display:flex;flex-direction:column;gap:0}.athlete-header{min-height:39px}.athlete-card{gap:4px}.station-main{padding:4px;gap:4px}.station-bottom{height:25px}}
</style>
