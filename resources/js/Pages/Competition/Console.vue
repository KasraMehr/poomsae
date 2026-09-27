<script setup>
import { computed } from 'vue';
import { Head, Link, useForm, usePoll } from '@inertiajs/vue3';
import ArenaLayout from '../../Layouts/ArenaLayout.vue';
import CourtConsole from '../../Components/CourtConsole.vue';
import BoutPanel from '../../Components/BoutPanel.vue';
import FormErrors from '../../Components/common/FormErrors.vue';
import { useCompetitionUpdates } from '../../useCompetitionUpdates';
import { useJudgePresence } from '../../useJudgePresence';

const props = defineProps({ console: Object, can: Object, competitionUrls: Object });
const competition = computed(() => props.console);
const tournament = computed(() => competition.value.tournament);
const rulesFor = court => tournament.value.categories.find(category => category.id === (court.active || court.queue[0])?.category_id)?.rules || {};
const completion = useForm({});
const realtime = useCompetitionUpdates(props.console.tournament.id, ['console']);
const onlineJudgeIds = useJudgePresence(props.console.tournament.id);
usePoll(2000, { only: ['console', 'can'] });

const btn = 'inline-flex min-h-11 items-center justify-center rounded-lg border-0 bg-[#09b5b0] px-[22px] py-[11px] text-[13px] font-bold text-[#031316] shadow-[0_7px_22px_#00a9a42e] hover:bg-[#30d0ca] disabled:cursor-wait disabled:opacity-55';
const btnSecondary = 'inline-flex min-h-11 items-center justify-center rounded-lg border border-[#263b51] bg-[#132235] px-[22px] py-[11px] text-[13px] text-[#b8c9da] disabled:cursor-wait disabled:opacity-55';
</script>

<template>
    <ArenaLayout :connection="realtime"><template #actions><Link v-if="can.manage" :href="competitionUrls.setup">آماده‌سازی</Link><Link v-if="can.judge" :href="competitionUrls.judge">پنل داور</Link><Link :href="competitionUrls.scoreboard">نمایشگر</Link></template><div>
        <Head :title="`میز اجرا · ${tournament.name}`" />
        <section class="mb-5 flex items-center justify-between gap-5 border-b border-[#1e3044] pb-6 max-[760px]:flex-col max-[760px]:items-start max-[760px]:gap-[15px] max-[760px]:pb-[18px]">
            <div><div class="mb-2 flex items-center gap-3 text-[10px] font-bold tracking-[2px] text-[#47d5ce] max-[760px]:flex-wrap"><span>COURT CONTROL</span><span>LIVE OPERATIONS</span></div><h1 class="text-[34px] font-bold leading-[1.6] tracking-[-0.5px] max-[760px]:text-[27px]">{{ tournament.name }}</h1><p class="text-[13px] text-[#8da0b5]">{{ tournament.venue || 'محل تعیین نشده' }} · مرکز فرمان اجرای زنده</p></div>
            <div class="flex flex-wrap items-center gap-[10px]"><a :href="competitionUrls.export" :class="btnSecondary">دریافت نتایج CSV</a></div>
        </section>

        <section class="mb-[22px] grid grid-cols-4 gap-3 max-[900px]:grid-cols-2 max-[500px]:grid-cols-1">
            <article class="flex items-center justify-between rounded-[10px] border border-[#1e3044] bg-[#0e1b2b] px-[18px] py-4 max-[500px]:px-[15px] max-[500px]:py-3"><span class="text-[11px] text-[#91a6bb]">در حال اجرا</span><b class="text-[24px] tabular-nums text-[#f5fbff]">{{ competition.attention.running }}</b></article>
            <article class="flex items-center justify-between rounded-[10px] border border-[#1e3044] bg-[#0e1b2b] px-[18px] py-4 max-[500px]:px-[15px] max-[500px]:py-3" :class="competition.attention.waiting_for_scores ? 'border-[#745f24] bg-[#3a2d13]' : ''"><span class="text-[11px] text-[#91a6bb]">منتظر نمره داورها</span><b class="text-[24px] tabular-nums text-[#f5fbff]">{{ competition.attention.waiting_for_scores }}</b></article>
            <article class="flex items-center justify-between rounded-[10px] border border-[#1e3044] bg-[#0e1b2b] px-[18px] py-4 max-[500px]:px-[15px] max-[500px]:py-3"><span class="text-[11px] text-[#91a6bb]">آمادهٔ فراخوان</span><b class="text-[24px] tabular-nums text-[#f5fbff]">{{ competition.attention.ready }}</b></article>
            <article class="flex items-center justify-between rounded-[10px] border border-[#1e3044] bg-[#0e1b2b] px-[18px] py-4 max-[500px]:px-[15px] max-[500px]:py-3"><span class="text-[11px] text-[#91a6bb]">زمین‌های فعال</span><b class="text-[24px] tabular-nums text-[#f5fbff]">{{ competition.courts.filter(c => c.active).length }} / {{ competition.courts.length }}</b></article>
        </section>

        <div v-if="competition.courts.length" class="mb-[26px] grid grid-cols-1 gap-5"><CourtConsole v-for="court in competition.courts" :key="court.id" :court="court" :rules="rulesFor(court)" :online-judge-ids="onlineJudgeIds" :base="competitionUrls.base" /></div>
        <div v-else class="rounded-xl border border-dashed border-[#2a3c50] bg-[#0c1724] px-5 py-[50px] text-center"><h2>هنوز زمینی تعریف نشده است</h2><p class="my-3 mb-[22px] text-xs text-[#74889d]">برای شروع، یک زمین و رده را در آماده‌سازی تعریف کنید.</p><Link v-if="can.manage" :href="competitionUrls.setup" :class="btn">رفتن به آماده‌سازی</Link></div>

        <details v-for="category in tournament.categories" :key="category.id" class="group mb-6 mt-4 rounded-xl border border-[#24384d] bg-[#0d1928] p-[27px] text-[#e8f2fb] max-[760px]:p-5 [&_form>button]:mt-[15px] [&_form>label]:mt-[15px] [&_form]:mt-[18px] [&_h2]:mb-5">
            <summary class="cursor-pointer py-[5px] text-[14px] font-bold text-[#70d8d3] group-open:mb-5">{{ category.name }} · جدول و تصمیم‌های ویژه</summary>
            <p v-if="!category.rounds.length" class="text-[13px] text-[#8da0b5]">هنوز دوری برای این رده ساخته نشده است.</p>
            <section v-for="round in category.rounds" :key="round.id"><div class="mb-[18px] mt-[26px] flex items-center justify-between"><h2>{{ round.name }}</h2><span class="text-[13px] text-[#8da0b5]">{{ round.bouts.length }} رقابت</span></div><BoutPanel v-for="bout in round.bouts" :key="bout.id" :bout="bout" :category="category" :tournament="tournament" :base="competitionUrls.base" :can-operate="can.operate" theme="dark" /></section>
        </details>

        <section v-if="can.manage && tournament.status === 'running'" class="mb-6 rounded-xl border border-[#24384d] bg-[#0d1928] p-[27px] text-[#e8f2fb] max-[760px]:p-5"><h2 class="mb-5">پایان رسمی مسابقه</h2><p class="text-[13px] text-[#8da0b5]">پس از پایان همهٔ رده‌ها، مسابقه را ببندید.</p><FormErrors :errors="completion.errors"/><button :class="btn" :disabled="completion.processing || !tournament.categories.length || !tournament.categories.every(c => c.completed)" @click="completion.post(competitionUrls.base + '/complete', { preserveScroll: true })">ثبت پایان مسابقه</button></section>
    </div></ArenaLayout>
</template>
