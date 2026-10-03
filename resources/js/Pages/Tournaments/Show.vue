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
        <div class="progress-steps"><span :class="{done:tournament.courts.length}">۱ · زمین و داور</span><span :class="{done:tournament.categories.length}">۲ · رده و ثبت‌نام</span><span :class="{done:tournament.categories.some(c=>c.rounds.some(r=>r.scheduled))}">۳ · قرعه و اجرا</span><span :class="{done:tournament.status === 'completed'}">۴ · نتیجهٔ نهایی</span></div>
        <nav class="tabs"><button v-if="can.manage && tournament.status !== 'completed'" :class="{selected:tab === 'setup'}" @click="tab='setup'">آماده‌سازی</button><button :class="{selected:tab === 'control'}" @click="tab='control'">رده‌ها و میز اجرا</button></nav>
        <CompetitionSetupPanel v-if="tab === 'setup' && can.manage && tournament.status !== 'completed'" :tournament="tournament" :base="competitionUrls.base"/>
        <div v-else>
            <div v-if="!tournament.categories.length" class="empty"><h2>ابتدا ردهٔ مسابقه را بسازید</h2><p>از آماده‌سازی، زمین، داور و رده را تعریف کنید.</p></div>
            <template v-if="selected">
                <div class="category-switch"><label for="active-category">ردهٔ فعال</label><select id="active-category" v-model.number="selectedId"><option v-for="category in tournament.categories" :key="category.id" :value="category.id">{{ category.name }}</option></select><span class="badge">{{ selected.discipline === 'freestyle' ? 'ابداعی' : 'استاندارد' }} · {{ {individual:'انفرادی',pair:'زوجی',team:'تیمی'}[selected.entry_type] }} · {{ selected.judge_count }} داور · {{ selected.format === 'knockout' ? 'تک‌حذفی' : 'دورهای' }}</span></div>
                <details class="panel" v-if="can.manage && tournament.status !== 'completed'" :open="!selected.registration_locked"><summary>ثبت‌نام، پذیرش، قرعهٔ پومسه و ساخت مراحل</summary><CategoryRegistration :key="selected.id" :category="selected" :tournament="tournament" :base="competitionUrls.base"/></details>
                <div v-if="selected.completed" class="notice">این رده پایان یافته است.<span v-if="selected.champion_id"> قهرمان: {{ selected.entries.find(e => e.id === selected.champion_id)?.name }}</span></div>
                <section v-for="round in selected.rounds" :key="round.id"><div class="section-heading"><h2>{{ round.name }}</h2><span class="subtle">{{ round.bouts.length }} {{ selected.score_based ? 'ورودی' : 'رقابت' }}</span></div><p v-if="!round.forms_ready" class="notice">در انتظار قرعهٔ پومسه؛ اجرای این مرحله تا تعیین فرم‌ها بسته است.</p><p v-if="!round.scheduled" class="subtle">جدول این مرحله هنوز آماده نشده است.</p><p v-else-if="!round.previous_completed" class="subtle">شروع این مرحله پس از پایان مرحلهٔ قبل مجاز است.</p><p v-if="round.form_names.length" class="subtle">پومسه‌های این مرحله: {{ round.form_names.join('، ') }}</p><BoutPanel v-for="bout in round.bouts" :key="bout.id" :bout="bout" :round="round" :category="selected" :tournament="tournament" :base="competitionUrls.base" :can-operate="can.operate"/></section>
                <section v-if="selected.standings.length" class="panel"><h2>{{ selected.score_based ? 'رتبه‌بندی امتیازی' : 'جدول بردها' }} {{ selected.completed ? '· پایان رده' : '· موقت' }}</h2><p class="subtle">{{ selected.score_based ? `نتیجهٔ ${selected.forms_per_round === 1 ? 'یک اجرا' : 'دو فرم'} رتبه را تعیین می‌کند. در تساوی، نمره‌های حذف‌شده برمی‌گردند و میانگین همهٔ داوران معیار دوم است؛ برابری دوباره، رتبهٔ مشترک دارد.` : 'برد مساوی، رتبهٔ مشترک دارد؛ tie-break جدول دورهای خودکار اعمال نمی‌شود.' }}</p><table><thead><tr><th>رتبه</th><th>ورودی</th><th v-if="!selected.score_based">رقابت پایان‌یافته</th><th>{{ selected.score_based ? 'امتیاز نهایی' : 'برد' }}</th><th v-if="selected.score_based && selected.standings.some(entry => entry.tie_break_score)">امتیاز رفع تساوی</th></tr></thead><tbody><tr v-for="entry in selected.standings" :key="entry.id"><td>{{ entry.rank ?? '—' }}</td><td>{{ entry.name }}</td><td v-if="!selected.score_based">{{ entry.played }}</td><td>{{ selected.score_based ? (entry.score ?? 'در انتظار') : entry.wins }}</td><td v-if="selected.score_based && selected.standings.some(item => item.tie_break_score)">{{ entry.tie_break_score ?? '—' }}</td></tr></tbody></table></section>
            </template>
        </div>
        <section v-if="can.manage && tournament.status === 'running'" class="panel"><h2>پایان مسابقه</h2><p class="subtle">فقط وقتی همهٔ رده‌ها، از جمله فینال تک‌حذفی، پایان یافته‌اند فعال می‌شود.</p><FormErrors :errors="completion.errors"/><button class="button" :disabled="completion.processing || !tournament.categories.length || !tournament.categories.every(c=>c.completed)" @click="completion.post(competitionUrls.base + '/complete', {preserveScroll:true})">تأیید پایان مسابقه</button></section>
    </AppLayout>
</template>
