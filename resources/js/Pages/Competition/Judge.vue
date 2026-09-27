<script setup>
import { useCompetitionUpdates } from '../../useCompetitionUpdates';
import { computed } from 'vue';
import { Head, usePoll, usePage, Link } from '@inertiajs/vue3';
import ArenaLayout from '../../Layouts/ArenaLayout.vue';
import JudgeScoreForm from '../../Components/JudgeScoreForm.vue';
const props = defineProps({ tournament: Object });
const realtime = useCompetitionUpdates(props.tournament.id);
const page = usePage();
const base = computed(() => page.props.urls.tournaments + '/' + props.tournament.id);
const assigned = computed(() => props.tournament.categories.flatMap(category => category.rounds.flatMap(round => round.bouts.flatMap(bout => bout.performances.filter(p => p.is_assigned && ['running','scoring'].includes(p.status)).map(performance => ({ category, bout, performance }))))));
usePoll(2000,{only:['tournament']});
</script>
<template>
    <ArenaLayout :connection="realtime"><template #actions><Link :href="base">مرکز کنترل</Link></template><div class="mx-auto max-w-[1050px]"><Head title="پنل داور"/>
        <section class="mb-[25px] flex items-end justify-between border-b border-[#1d2d40] pb-6 pt-[25px] max-[900px]:items-start max-[900px]:gap-5"><div><span class="mb-2 block text-xs text-[#578678]">JUDGE SCORING DEVICE</span><h1 class="text-[38px] max-[560px]:text-[29px]">میز داوری</h1><p class="text-xs text-[#8195a9]">{{ tournament.name }}</p></div><div class="rounded-[7px] bg-[#10263a] px-[15px] py-[9px] text-[10px] text-[#63d7cf] max-[560px]:hidden">ثبت امن و مستقیم نمره</div></section>
        <section v-if="!assigned.length" class="rounded-xl border border-dashed border-[#2a3c50] bg-[#0c1724] px-5 py-[50px] text-center"><span class="text-[35px] text-[#a0b297]">◷</span><h2>منتظر شروع اجرای بعدی</h2><p class="my-3 mb-[22px] text-xs text-[#74889d]">این صفحه هر دو ثانیه تازه می‌شود. پس از پایان اجرا، فرم ثبت نمره فعال می‌شود.</p></section>
        <section v-for="{category,bout,performance} in assigned" :key="performance.id" class="mb-[25px] rounded-xl border border-[#263b50] bg-[#0d1928] p-[30px] shadow-[0_18px_55px_#0005] [&_form]:mt-[18px] [&_h2]:mb-5">
            <div class="mb-5 flex items-center justify-between gap-[15px] border-b border-[#23374a] pb-[22px] max-[850px]:flex-col max-[850px]:items-start"><div><span class="mb-2 block text-xs text-[#578678]">{{ category.name }} · {{ tournament.courts.find(c => c.id === bout.court_id)?.name }}</span><h2 class="text-[32px] text-white">{{ bout.entries.find(e => e.id === performance.entry_id)?.name }}</h2><p>{{ performance.form_name }} · فرم {{ performance.form_number }} از ۲</p></div><span class="inline-block rounded-[5px] bg-[#dcefe1] px-[9px] py-[3px] text-[10px] text-[#2a7851]">{{ performance.status === 'running' ? 'در حال اجرا' : 'نوبت ثبت نمره' }}</span></div>
            <JudgeScoreForm v-if="performance.status === 'scoring'" :key="performance.id + '-scoring'" :performance="performance" :rules="category.rules" :endpoint="base + '/performances/' + performance.id + '/scores'"/>
            <div v-else class="mb-5 rounded-lg bg-[#12382f] px-[18px] py-3 text-[#75e0b7]">اجرا در جریان است. پس از پایان توسط اپراتور، نمره را ثبت کنید.</div>
        </section>
        <p class="p-[18px] text-center text-[10px] text-[#687e92]">در قطع شبکه، پیش‌نویس روی همین دستگاه می‌ماند. فقط پیام موفق سرور به معنی ثبت نمره است.</p>
    </div></ArenaLayout>
</template>
