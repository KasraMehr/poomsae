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
const bouts = computed(() => props.tournament.categories.flatMap(c => c.rounds.flatMap(r => r.bouts.filter(b => !courtId.value || b.court_id === Number(courtId.value)).map(b => ({...b,category:c.name,round:r.name})))));
const active = computed(() => bouts.value.filter(b => b.status === 'running'));
const recent = computed(() => bouts.value.filter(b => b.status === 'completed').slice(-6).reverse());
const elapsed = p => p.started_at ? Math.max(0,Math.floor(((p.ended_at ? new Date(p.ended_at).getTime() : now.value)-new Date(p.started_at).getTime())/1000)) : 0;
const score = value => value == null ? '—' : Number(value).toLocaleString('fa-IR',{minimumFractionDigits:3,maximumFractionDigits:6});
const sideClasses = { chung: 'border-[#3a9fff] bg-[linear-gradient(145deg,#102b47,#0b1d30)]', hong: 'border-[#ff5d69] bg-[linear-gradient(145deg,#3b1921,#25131a)]' };
</script>
<template>
    <div class="min-h-screen bg-[radial-gradient(circle_at_50%_-20%,#19364a_0,#0a1723_38%,#050b12_75%)] px-[5vw] py-[35px] text-[#eaf2f8] max-[850px]:px-5 max-[850px]:py-[25px]"><Head title="نمایشگر سالن"/>
        <header class="mb-[30px] flex items-center justify-between border-b border-[#284055] pb-[30px] max-[850px]:flex-col max-[850px]:items-start max-[850px]:gap-5"><div><span class="mb-2 block text-[11px] tracking-[1px] text-[#839eac]">POOMSAE · LIVE RESULTS</span><h1 class="text-[29px] max-[500px]:text-[25px]">{{ tournament.name }}</h1></div><div class="flex flex-wrap items-center gap-[10px]"><label for="display-court" class="sr-only">زمین نمایشگر</label><select id="display-court" v-model="courtId" class="w-[180px] border-[#3a4b56] bg-[#223443] text-[#e7f1f4]"><option value="">همهٔ زمین‌ها</option><option v-for="court in tournament.courts" :key="court.id" :value="court.id">{{ court.name }}</option></select><Link :href="page.props.urls.tournaments + '/' + tournament.id" class="text-xs text-[#8da1ac]">بازگشت</Link></div></header>
        <div v-if="now - refreshed > 12000" class="rounded-lg bg-[#994839] px-5 py-[15px] text-[#fff3ee]" role="alert">به‌روزرسانی متوقف شده؛ نتایج زیر ممکن است قدیمی باشند. اتصال شبکه را بررسی کنید.</div>
        <section v-if="!active.length" class="my-[25px] rounded-[15px] border border-[#243b4d] bg-[#0d1c29] px-[25px] py-[50px] text-center"><span class="text-[35px] max-[500px]:text-[26px]">{{ tournament.status === 'completed' ? 'مسابقه پایان یافت' : 'آمادهٔ اجرای بعدی' }}</span><p class="mt-[15px] text-[#87a0b0]">نتایج فقط پس از تأیید اپراتور منتشر می‌شوند.</p></section>
        <section v-for="bout in active" :key="bout.id" class="mb-[30px]"><div class="mb-[18px] mt-[26px] flex items-center justify-between"><h2>{{ bout.category }} · {{ bout.round }} · رقابت {{ bout.sequence }}</h2><span>{{ tournament.courts.find(c=>c.id === bout.court_id)?.name }}</span></div>
            <div class="grid grid-cols-2 gap-[25px] max-[850px]:gap-3 max-[500px]:grid-cols-1"><article v-for="entry in bout.entries" :key="entry.id" class="rounded-[15px] border-t-[5px] p-[30px] text-[#f0f7fa] shadow-[0_22px_65px_#0005] max-[850px]:p-[18px]" :class="sideClasses[entry.side]"><span class="mb-2 block text-[11px] tracking-[1px] text-[#839eac]">{{ entry.side === 'chung' ? 'چونگ' : 'هونگ' }}</span><h2 class="mb-5 text-[32px] max-[850px]:text-[23px]">{{ entry.name }}</h2><div v-for="performance in bout.performances.filter(p=>p.entry_id === entry.id)" :key="performance.id" class="grid grid-cols-[1fr_auto] items-center border-t border-[#ffffff19] py-[18px]"><span class="text-[15px] text-[#a9bac6]">{{ performance.form_name }}</span><strong class="text-[38px] leading-[1.5] tabular-nums max-[850px]:text-[27px]">{{ score(performance.result) }}</strong><small v-if="performance.status === 'running'" class="col-span-full text-xs text-[#d2b980]">در حال اجرا · {{ Math.floor(elapsed(performance)/60) }}:{{ String(elapsed(performance)%60).padStart(2,'0') }}</small><small v-else-if="performance.status === 'scoring'" class="col-span-full text-xs text-[#d2b980]">در انتظار تأیید نتیجه</small><small v-else-if="performance.status === 'pending'" class="col-span-full text-xs text-[#d2b980]">در انتظار اجرا</small></div><div class="mt-[15px] flex items-center justify-between rounded-[10px] bg-[#ffffff09] p-[17px] text-[#adbcca]">میانگین دو فرم <b class="text-[42px] text-[#f5f8ec] max-[850px]:text-[29px]">{{ score(bout.totals[entry.id]) }}</b></div></article></div>
            <p v-if="Object.keys(bout.totals).length === 2 && !bout.winner_entry_id" class="mt-[15px] rounded-[10px] bg-[#635329] p-5 text-center text-[#fff2c9]">تساوی · منتظر تصمیم سرداور</p>
        </section>
        <section v-if="recent.length" class="mt-10"><h2>آخرین رقابت‌های پایان‌یافته</h2><article v-for="bout in recent" :key="bout.id" class="my-[15px] flex items-center justify-between rounded-[10px] bg-[#1b2c36] px-[25px] py-[18px]"><div><span class="text-[11px] text-[#8da3ae]">{{ bout.category }} · {{ bout.round }} · رقابت {{ bout.sequence }}</span><h3 class="mt-[6px]">{{ bout.entries.find(e=>e.id === bout.winner_entry_id)?.name }}</h3></div><b class="text-[25px] text-[#93d3b5]">{{ bout.performances.length ? score(bout.totals[bout.winner_entry_id]) : 'استراحت' }}</b></article></section>
        <footer class="flex justify-between pb-0 pt-10 text-[10px] text-[#6e8895]">نمایش نتایج تأییدشده <span>به‌روزرسانی هر ۲ ثانیه</span></footer>
    </div>
</template>
