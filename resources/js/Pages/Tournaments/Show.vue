<script setup>
import { useCompetitionUpdates } from '../../useCompetitionUpdates';
import { computed, ref } from 'vue';
import { Head, Link, useForm, usePoll } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import CompetitionSetupPanel from '../../Components/CompetitionSetupPanel.vue';
import CategoryRegistration from '../../Components/CategoryRegistration.vue';
import BoutPanel from '../../Components/BoutPanel.vue';
import FormErrors from '../../Components/FormErrors.vue';
const props = defineProps({ tournament: Object, can: Object, competitionUrls: Object });
useCompetitionUpdates(props.tournament.id);
const tab = ref('setup');
const selectedId = ref(props.tournament.categories[0]?.id);
const selected = computed(() => props.tournament.categories.find(c => c.id === selectedId.value) || props.tournament.categories[0]);
const completion = useForm({});
const labels = {draft:'آماده‌سازی',ready:'آمادهٔ شروع',running:'در حال برگزاری',completed:'پایان‌یافته',archived:'آرشیو'};
usePoll(5000, { only: ['tournament','can'] });
</script>
<template>
    <AppLayout><Head :title="tournament.name"/>
        <section class="page-heading"><div><span class="eyebrow">آماده‌سازی مسابقه</span><h1>{{ tournament.name }}</h1><p class="subtle">{{ tournament.venue || 'محل تعیین نشده' }} · {{ labels[tournament.status] }}</p></div><div class="row-actions"><Link :href="competitionUrls.base" class="button">بازگشت به میز اجرا</Link><Link :href="competitionUrls.scoreboard" class="button secondary">نمایشگر سالن</Link></div></section>
        <div class="progress-steps"><span :class="{done:tournament.courts.length}">۱ · زمین و داور</span><span :class="{done:tournament.categories.length}">۲ · رده و ثبت‌نام</span><span :class="{done:tournament.categories.some(c=>c.rounds.length)}">۳ · قرعه و اجرا</span><span :class="{done:tournament.status === 'completed'}">۴ · نتیجهٔ نهایی</span></div>
        <nav class="tabs"><button v-if="can.manage && tournament.status !== 'completed'" :class="{selected:tab === 'setup'}" @click="tab='setup'">آماده‌سازی</button><button :class="{selected:tab === 'control'}" @click="tab='control'">رده‌ها و میز اجرا</button></nav>
        <CompetitionSetupPanel v-if="tab === 'setup' && can.manage && tournament.status !== 'completed'" :tournament="tournament" :base="competitionUrls.base"/>
        <div v-else>
            <div v-if="!tournament.categories.length" class="empty"><h2>ابتدا ردهٔ مسابقه را بسازید</h2><p>از آماده‌سازی، زمین، داور و رده را تعریف کنید.</p></div>
            <template v-if="selected">
                <div class="category-switch"><label for="active-category">ردهٔ فعال</label><select id="active-category" v-model.number="selectedId"><option v-for="category in tournament.categories" :key="category.id" :value="category.id">{{ category.name }}</option></select><span class="badge">{{ selected.judge_count }} داور · {{ selected.format === 'knockout' ? 'تک‌حذفی' : 'دورهای' }} · حذف {{ selected.rules?.discard_each_end ?? 0 }} نمره از هر طرف</span></div>
                <details class="panel" v-if="can.manage && tournament.status !== 'completed'" :open="!selected.rounds.length"><summary>ثبت ورزشکار، پذیرش و ساخت دور</summary><CategoryRegistration :key="selected.id" :category="selected" :tournament="tournament" :base="competitionUrls.base"/></details>
                <div v-if="selected.completed" class="notice">این رده پایان یافته است.<span v-if="selected.champion_id"> قهرمان: {{ selected.entries.find(e => e.id === selected.champion_id)?.name }}</span></div>
                <section v-for="round in selected.rounds" :key="round.id"><div class="section-heading"><h2>{{ round.name }}</h2><span class="subtle">{{ round.bouts.length }} رقابت</span></div><BoutPanel v-for="bout in round.bouts" :key="bout.id" :bout="bout" :category="selected" :tournament="tournament" :base="competitionUrls.base" :can-operate="can.operate"/></section>
                <section v-if="selected.standings.length" class="panel"><h2>جدول بردها {{ selected.completed ? '· پایان رده' : '· موقت' }}</h2><p class="subtle">برد مساوی، رتبهٔ مشترک دارد؛ tie-break جدول دورهای خودکار اعمال نمی‌شود.</p><table><thead><tr><th>رتبه</th><th>ورزشکار</th><th>رقابت پایان‌یافته</th><th>برد</th></tr></thead><tbody><tr v-for="entry in selected.standings" :key="entry.id"><td>{{ entry.rank }}</td><td>{{ entry.name }}</td><td>{{ entry.played }}</td><td>{{ entry.wins }}</td></tr></tbody></table></section>
            </template>
        </div>
        <section v-if="can.manage && tournament.status === 'running'" class="panel"><h2>پایان مسابقه</h2><p class="subtle">فقط وقتی همهٔ رده‌ها، از جمله فینال تک‌حذفی، پایان یافته‌اند فعال می‌شود.</p><FormErrors :errors="completion.errors"/><button class="button" :disabled="completion.processing || !tournament.categories.length || !tournament.categories.every(c=>c.completed)" @click="completion.post(competitionUrls.base + '/complete', {preserveScroll:true})">تأیید پایان مسابقه</button></section>
    </AppLayout>
</template>
