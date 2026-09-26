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
    <ArenaLayout :connection="realtime"><template #actions><Link :href="base">مرکز کنترل</Link></template><div class="judge-device"><Head title="پنل داور"/>
        <section class="judge-device-head"><div><span class="eyebrow">JUDGE SCORING DEVICE</span><h1>میز داوری</h1><p>{{ tournament.name }}</p></div><div class="judge-seat-label">ثبت امن و مستقیم نمره</div></section>
        <section v-if="!assigned.length" class="empty"><span class="empty-icon">◷</span><h2>منتظر شروع اجرای بعدی</h2><p>این صفحه هر دو ثانیه تازه می‌شود. پس از پایان اجرا، فرم ثبت نمره فعال می‌شود.</p></section>
        <section v-for="{category,bout,performance} in assigned" :key="performance.id" class="panel judging-card">
            <div class="bout-heading"><div><span class="eyebrow">{{ category.name }} · {{ tournament.courts.find(c => c.id === bout.court_id)?.name }}</span><h2>{{ bout.entries.find(e => e.id === performance.entry_id)?.name }}</h2><p>{{ performance.form_name }} · فرم {{ performance.form_number }} از ۲</p></div><span class="badge running">{{ performance.status === 'running' ? 'در حال اجرا' : 'نوبت ثبت نمره' }}</span></div>
            <JudgeScoreForm v-if="performance.status === 'scoring'" :key="performance.id + '-scoring'" :performance="performance" :rules="category.rules" :endpoint="base + '/performances/' + performance.id + '/scores'"/>
            <div v-else class="notice">اجرا در جریان است. پس از پایان توسط اپراتور، نمره را ثبت کنید.</div>
        </section>
        <p class="judge-offline-note">در قطع شبکه، پیش‌نویس روی همین دستگاه می‌ماند. فقط پیام موفق سرور به معنی ثبت نمره است.</p>
    </div></ArenaLayout>
</template>

