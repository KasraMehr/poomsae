<script setup>
import { computed } from 'vue';
import { Head, Link, useForm, usePoll } from '@inertiajs/vue3';
import ArenaLayout from '../../Layouts/ArenaLayout.vue';
import CourtConsole from '../../Components/CourtConsole.vue';
import BoutPanel from '../../Components/BoutPanel.vue';
import FormErrors from '../../Components/FormErrors.vue';
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
</script>

<template>
    <ArenaLayout :connection="realtime"><template #actions><Link v-if="can.manage" :href="competitionUrls.setup">آماده‌سازی</Link><Link v-if="can.judge" :href="competitionUrls.judge">پنل داور</Link><Link :href="competitionUrls.scoreboard">نمایشگر</Link></template><div class="arena-ui">
        <Head :title="`میز اجرا · ${tournament.name}`" />
        <section class="page-heading console-heading">
            <div><div class="arena-kicker"><span>COURT CONTROL</span><span>LIVE OPERATIONS</span></div><h1>{{ tournament.name }}</h1><p class="subtle">{{ tournament.venue || 'محل تعیین نشده' }} · مرکز فرمان اجرای زنده</p></div>
            <div class="row-actions"><a :href="competitionUrls.export" class="button secondary">دریافت نتایج CSV</a></div>
        </section>

        <section class="console-stats">
            <article><span>در حال اجرا</span><b>{{ competition.attention.running }}</b></article>
            <article :class="{ alert: competition.attention.waiting_for_scores }"><span>منتظر نمره داورها</span><b>{{ competition.attention.waiting_for_scores }}</b></article>
            <article><span>آمادهٔ فراخوان</span><b>{{ competition.attention.ready }}</b></article>
            <article><span>زمین‌های فعال</span><b>{{ competition.courts.filter(c => c.active).length }} / {{ competition.courts.length }}</b></article>
        </section>

        <div v-if="competition.courts.length" class="court-grid"><CourtConsole v-for="court in competition.courts" :key="court.id" :court="court" :rules="rulesFor(court)" :online-judge-ids="onlineJudgeIds" :base="competitionUrls.base" /></div>
        <div v-else class="empty"><h2>هنوز زمینی تعریف نشده است</h2><p>برای شروع، یک زمین و رده را در آماده‌سازی تعریف کنید.</p><Link v-if="can.manage" :href="competitionUrls.setup" class="button">رفتن به آماده‌سازی</Link></div>

        <details v-for="category in tournament.categories" :key="category.id" class="panel competition-details">
            <summary>{{ category.name }} · جدول و تصمیم‌های ویژه</summary>
            <p v-if="!category.rounds.length" class="subtle">هنوز دوری برای این رده ساخته نشده است.</p>
            <section v-for="round in category.rounds" :key="round.id"><div class="section-heading"><h2>{{ round.name }}</h2><span class="subtle">{{ round.bouts.length }} {{ category.score_based ? 'ورزشکار' : 'رقابت' }}</span></div><BoutPanel v-for="bout in round.bouts" :key="bout.id" :bout="bout" :round="round" :category="category" :tournament="tournament" :base="competitionUrls.base" :can-operate="can.operate" /></section>
        </details>

        <section v-if="can.manage && tournament.status === 'running'" class="panel"><h2>پایان رسمی مسابقه</h2><p class="subtle">پس از پایان همهٔ رده‌ها، مسابقه را ببندید.</p><FormErrors :errors="completion.errors"/><button class="button" :disabled="completion.processing || !tournament.categories.length || !tournament.categories.every(c => c.completed)" @click="completion.post(competitionUrls.base + '/complete', { preserveScroll: true })">ثبت پایان مسابقه</button></section>
    </div></ArenaLayout>
</template>
