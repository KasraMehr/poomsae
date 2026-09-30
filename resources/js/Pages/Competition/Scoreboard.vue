<script setup>
import { useCompetitionUpdates } from '../../useCompetitionUpdates';
import { computed, ref, watch, onMounted, onUnmounted } from 'vue';
import { Head, Link, usePoll, usePage } from '@inertiajs/vue3';
const props = defineProps({ tournament: Object });
useCompetitionUpdates(props.tournament.id);
const page = usePage();
const courtId = ref('');
const now = ref(Date.now());
const refreshed = ref(Date.now());
watch(() => props.tournament, () => refreshed.value = Date.now());
let clock;
onMounted(() => clock = setInterval(() => now.value = Date.now(),1000));
onUnmounted(() => clearInterval(clock));
usePoll(2000,{only:['tournament']});
const bouts = computed(() => props.tournament.categories.flatMap(c => c.rounds.flatMap(r => r.bouts.filter(b => !courtId.value || b.court_id === Number(courtId.value)).map(b => ({...b,category:c.name,format:c.format,aggregation:c.rules?.aggregation,round:r.name})))));
const active = computed(() => bouts.value.filter(b => b.status === 'running'));
const recent = computed(() => bouts.value.filter(b => b.status === 'completed').slice(-6).reverse());
const elapsed = p => p.started_at ? Math.max(0,Math.floor(((p.ended_at ? new Date(p.ended_at).getTime() : now.value)-new Date(p.started_at).getTime())/1000)) : 0;
const score = value => value == null ? '—' : Number(value).toLocaleString('fa-IR',{minimumFractionDigits:3,maximumFractionDigits:6});
</script>
<template>
    <div class="scoreboard"><Head title="نمایشگر سالن"/>
        <header class="scoreboard-header"><div><span class="eyebrow">POOMSAE · LIVE RESULTS</span><h1>{{ tournament.name }}</h1></div><div class="row-actions"><label for="display-court" class="sr-only">زمین نمایشگر</label><select id="display-court" v-model="courtId"><option value="">همهٔ زمین‌ها</option><option v-for="court in tournament.courts" :key="court.id" :value="court.id">{{ court.name }}</option></select><Link :href="page.props.urls.tournaments + '/' + tournament.id">بازگشت</Link></div></header>
        <div v-if="now - refreshed > 12000" class="connection-alert" role="alert">به‌روزرسانی متوقف شده؛ نتایج زیر ممکن است قدیمی باشند. اتصال شبکه را بررسی کنید.</div>
        <section v-if="!active.length" class="scoreboard-wait"><span>{{ tournament.status === 'completed' ? 'مسابقه پایان یافت' : 'آمادهٔ اجرای بعدی' }}</span><p>نتایج فقط پس از تأیید اپراتور منتشر می‌شوند.</p></section>
        <section v-for="bout in active" :key="bout.id" class="live-bout"><div class="section-heading"><h2>{{ bout.category }} · {{ bout.round }} · رقابت {{ bout.sequence }}</h2><span>{{ tournament.courts.find(c=>c.id === bout.court_id)?.name }}</span></div>
            <div class="contestants" :class="{ solo: bout.entries.length === 1 }"><article v-for="entry in bout.entries" :key="entry.id" :class="entry.side"><span class="eyebrow">{{ bout.format === 'round_robin' ? 'دورهای' : (entry.side === 'chung' ? 'چونگ' : 'هونگ') }}</span><h2>{{ entry.name }}</h2><div v-for="performance in bout.performances.filter(p=>p.entry_id === entry.id)" :key="performance.id" class="display-form"><span>{{ performance.form_name }}</span><strong>{{ score(performance.result) }}</strong><small v-if="performance.status === 'running'">در حال اجرا · {{ Math.floor(elapsed(performance)/60) }}:{{ String(elapsed(performance)%60).padStart(2,'0') }}</small><small v-else-if="performance.status === 'scoring'">در انتظار تأیید نتیجه</small><small v-else-if="performance.status === 'pending'">در انتظار اجرا</small></div><div class="display-total">{{ bout.aggregation === 'sum_two_forms' ? 'مجموع دو فرم' : 'میانگین دو فرم' }} <b>{{ score(bout.totals[entry.id]) }}</b></div></article></div>
            <p v-if="Object.keys(bout.totals).length === 2 && !bout.winner_entry_id" class="display-tie">تساوی · منتظر تصمیم سرداور</p>
        </section>
        <section v-if="recent.length" class="recent-results"><h2>آخرین اجراهای پایان‌یافته</h2><article v-for="bout in recent" :key="bout.id"><div><span>{{ bout.category }} · {{ bout.round }} · رقابت {{ bout.sequence }}</span><h3>{{ bout.format === 'round_robin' ? bout.entries[0]?.name : bout.entries.find(e=>e.id === bout.winner_entry_id)?.name }}</h3></div><b>{{ bout.performances.length ? score(bout.totals[bout.format === 'round_robin' ? bout.entries[0]?.id : bout.winner_entry_id]) : 'استراحت' }}</b></article></section>
        <footer>نمایش نتایج تأییدشده <span>به‌روزرسانی هر ۲ ثانیه</span></footer>
    </div>
</template>
