<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, useForm, usePage, usePoll } from '@inertiajs/vue3';
import { useCompetitionUpdates } from '../../Shared/Composables/useCompetitionUpdates';
import AppLayout from '../../Shared/Layouts/AppLayout.vue';
import CompetitionSetupPanel from '../../Components/tournaments/CompetitionSetupPanel.vue';
import CategoryRegistration from '../../Components/tournaments/CategoryRegistration.vue';
import BoutPanel from '../../Components/competition/BoutPanel.vue';
import FormErrors from '../../Shared/Components/FormErrors.vue';
import TournamentDetailsEditor from '../../Components/tournaments/TournamentDetailsEditor.vue';
import CompetitionTimingSettings from '../../Components/tournaments/CompetitionTimingSettings.vue';

const props = defineProps({ tournament: Object, can: Object, competitionUrls: Object, ageCategories: Array, ageTableYear: Number });
const page = usePage();
const isTournamentSummaryOpen = ref(false);
useCompetitionUpdates(props.tournament.id);
usePoll(5000, { only: ['tournament', 'can'] });

const tab = ref(['running', 'completed', 'archived'].includes(props.tournament.status) ? 'control' : 'setup');
watch(() => props.tournament.status, status => {
    if (['completed', 'archived'].includes(status)) tab.value = 'control';
});
const selectedId = ref(props.tournament.categories[0]?.id);
const selected = computed(() => props.tournament.categories.find(category => category.id === selectedId.value) ?? props.tournament.categories[0] ?? null);
const completion = useForm({});
const labels = { draft: 'آماده‌سازی', ready: 'آمادهٔ شروع', running: 'در حال برگزاری', completed: 'پایان‌یافته', archived: 'آرشیو' };
const statusBadgeClasses = {
    draft: 'border-slate-200 bg-slate-50 text-slate-600',
    ready: 'border-sky-200 bg-sky-50 text-sky-700',
    running: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    completed: 'border-indigo-200 bg-indigo-50 text-indigo-700',
    archived: 'border-stone-200 bg-stone-100 text-stone-600',
};
const judgeCount = computed(() => props.tournament.members.filter(member => member.role === 'judge').length);
const entryCount = computed(() => props.tournament.categories.reduce((total, category) => total + category.entries.length, 0));
const scheduledCount = computed(() => props.tournament.categories.reduce((total, category) => total + category.rounds.filter(round => round.scheduled).length, 0));
const preparation = computed(() => [
    { number: '01', title: 'زمین و تیم', detail: `${props.tournament.courts.length} زمین · ${judgeCount.value} داور`, done: props.tournament.courts.length > 0 && judgeCount.value > 0 },
    { number: '02', title: 'رده و ثبت‌نام', detail: `${props.tournament.categories.length} رده · ${entryCount.value} ورودی`, done: props.tournament.categories.length > 0 && entryCount.value > 0 },
    { number: '03', title: 'قرعه و برنامه', detail: `${scheduledCount.value} مرحلهٔ برنامه‌ریزی‌شده`, done: scheduledCount.value > 0 },
    { number: '04', title: 'نتایج', detail: `${props.tournament.categories.filter(category => category.completed).length} ردهٔ پایان‌یافته`, done: props.tournament.status === 'completed' || props.tournament.status === 'archived' },
]);

function formatTournamentDate(value) {
    if (!value) {
        return 'تعیین نشده';
    }

    const dateOnly = /^(\d{4}-\d{2}-\d{2})/.exec(String(value))?.[1];
    const date = dateOnly ? new Date(`${dateOnly}T12:00:00Z`) : new Date(value);

    if (Number.isNaN(date.getTime())) {
        return 'تعیین نشده';
    }

    return new Intl.DateTimeFormat('fa-IR', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        timeZone: 'UTC',
    }).format(date);
}
</script>

<template>
    <AppLayout>
        <Head :title="tournament.name" />
        <div class="admin-page" dir="rtl">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <nav class="flex min-w-0 items-center gap-2 text-xs text-[#7c8b80]" aria-label="مسیر صفحه">
                    <Link :href="page.props.urls.tournaments" class="shrink-0 rounded-md px-2 py-1.5 font-semibold text-[#286957] transition-colors hover:bg-[#edf4ee]">مسابقات</Link>
                    <span aria-hidden="true" class="text-[#a2afa4]">/</span>
                    <span class="min-w-0 truncate font-medium text-[#52665a]" aria-current="page">{{ tournament.name }}</span>
                </nav>
                <button
                    type="button"
                    class="flex w-full min-w-0 max-w-[390px] items-center justify-between gap-3 rounded-2xl border border-[#174e40] bg-gradient-to-l from-[#174e40] to-[#2d755a] px-5 py-4 text-right text-white shadow-[0_9px_24px_#174e4030] transition hover:from-[#1c5b49] hover:to-[#358365] focus-visible:outline focus-visible:outline-offset-2 focus-visible:outline-[#286957]"
                    :aria-expanded="isTournamentSummaryOpen"
                    aria-controls="tournament-summary"
                    @click="isTournamentSummaryOpen = !isTournamentSummaryOpen"
                >
                    <span class="flex min-w-0 items-center gap-3">
                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-white/15 text-lg text-white" aria-hidden="true">▤</span>
                        <span class="min-w-0">
                            <strong class="block max-w-[250px] truncate text-sm font-bold text-white">{{ tournament.name }}</strong>
                            <small class="mt-1 block max-w-[250px] truncate text-xs text-[#d7eadc]">{{ tournament.venue || 'محل برگزاری تعیین نشده' }}</small>
                        </span>
                    </span>
                    <span class="shrink-0 rounded-full border px-2.5 py-1.5 text-[10px] font-bold" :class="statusBadgeClasses[tournament.status] || statusBadgeClasses.draft">{{ labels[tournament.status] }}</span>
                    <svg aria-hidden="true" viewBox="0 0 20 20" class="h-5 w-5 shrink-0 text-white transition-transform duration-300 ease-in-out" :class="isTournamentSummaryOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m5 7.5 5 5 5-5" stroke-linecap="round" stroke-linejoin="round" /></svg>
                </button>
            </div>
            <h1 class="sr-only">{{ tournament.name }}</h1>

            <Transition name="summary">
                <section v-if="isTournamentSummaryOpen" id="tournament-summary" class="grid min-h-0" aria-label="اطلاعات مسابقه">
                    <div class="min-h-0 overflow-hidden">
                        <div class="grid gap-5 rounded-2xl border border-[#c8ddcb] bg-[#e7f1e8] p-5 shadow-[0_6px_20px_#26452c0a] sm:p-6">
                            <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                                <div class="rounded-xl border border-[#e6ece5] bg-white px-3 py-2.5"><small class="block text-[10px] text-[#849087]">وضعیت</small><strong class="mt-1 block text-xs text-[#40594b]">{{ labels[tournament.status] }}</strong></div>
                                <div class="rounded-xl border border-[#e6ece5] bg-white px-3 py-2.5"><small class="block text-[10px] text-[#849087]">تاریخ شروع</small><strong class="mt-1 block text-xs text-[#40594b]">{{ formatTournamentDate(tournament.starts_on) }}</strong></div>
                                <div class="rounded-xl border border-[#e6ece5] bg-white px-3 py-2.5"><small class="block text-[10px] text-[#849087]">تاریخ پایان</small><strong class="mt-1 block text-xs text-[#40594b]">{{ formatTournamentDate(tournament.ends_on) }}</strong></div>
                                <div class="rounded-xl border border-[#e6ece5] bg-white px-3 py-2.5"><small class="block text-[10px] text-[#849087]">منطقهٔ زمانی</small><strong class="mt-1 block text-xs text-[#40594b]">{{ tournament.timezone || 'Asia/Tehran' }}</strong></div>
                            </div>

                            <section class="overview-grid" aria-label="آمار مسابقه">
                                <article><span>زمین‌ها</span><strong>{{ tournament.courts.length }}</strong><small>زمین تعریف‌شده</small></article>
                                <article><span>اعضای تیم</span><strong>{{ tournament.members.length }}</strong><small>{{ judgeCount }} داور ثبت‌شده</small></article>
                                <article><span>رده‌ها</span><strong>{{ tournament.categories.length }}</strong><small>{{ scheduledCount }} مرحلهٔ آماده</small></article>
                                <article><span>ورودی‌ها</span><strong>{{ entryCount }}</strong><small>در همهٔ رده‌ها</small></article>
                            </section>

                            <section class="readiness-card" aria-label="مراحل آماده‌سازی">
                                <div class="section-title"><div><span class="eyebrow">WORKFLOW</span><h2>مسیر آماده‌سازی</h2></div><span>{{ preparation.filter(step => step.done).length }} از ۴ مرحله</span></div>
                                <div class="readiness-grid">
                                    <div v-for="step in preparation" :key="step.number" class="readiness-step" :class="{ done: step.done }">
                                        <span class="step-number">{{ step.done ? '✓' : step.number }}</span>
                                        <div><strong>{{ step.title }}</strong><small>{{ step.detail }}</small></div>
                                    </div>
                                </div>
                            </section>

                            <div class="flex flex-wrap gap-2">
                                <Link :href="competitionUrls.reports" class="inline-flex min-h-9 items-center rounded-lg border border-[#dce7dd] bg-white px-3.5 text-xs font-semibold text-[#376a4e] transition-colors hover:bg-[#f0f7f1]">گزارش‌ها و آرشیو</Link>
                                <Link :href="competitionUrls.system" class="inline-flex min-h-9 items-center rounded-lg border border-[#dce7dd] bg-white px-3.5 text-xs font-semibold text-[#376a4e] transition-colors hover:bg-[#f0f7f1]">وضعیت سیستم</Link>
                            </div>
                        </div>
                    </div>
                </section>
            </Transition>

            <nav class="admin-tabs" aria-label="بخش‌های مدیریت مسابقه">
                <button v-if="can.manage && !['completed', 'archived'].includes(tournament.status)" type="button" :class="{ active: tab === 'setup' }" :aria-current="tab === 'setup' ? 'page' : undefined" @click="tab = 'setup'">تنظیمات و اعضا</button>
                <button type="button" :class="{ active: tab === 'control' }" :aria-current="tab === 'control' ? 'page' : undefined" @click="tab = 'control'">رده‌ها و برنامه <span>{{ tournament.categories.length }}</span></button>
            </nav>

            <div v-if="tab === 'setup' && can.manage && !['completed', 'archived'].includes(tournament.status)" class="admin-content">
                <TournamentDetailsEditor :tournament="tournament" :base="competitionUrls.base" />
                <CompetitionSetupPanel :tournament="tournament" :base="competitionUrls.base" :age-categories="ageCategories" :age-table-year="ageTableYear" />
            </div>

            <div v-else class="admin-content">
                <section v-if="!tournament.categories.length" class="admin-empty"><h2>هنوز رده‌ای ساخته نشده است</h2><p>از بخش «تنظیمات و اعضا» زمین، داور و رده را تعریف کنید.</p><button v-if="can.manage" type="button" @click="tab = 'setup'">رفتن به آماده‌سازی</button></section>
                <template v-if="selected">
                    <section class="category-toolbar">
                        <div><span class="eyebrow">CATEGORY CONTROL</span><h2>رده‌ها و برنامهٔ اجرا</h2></div>
                        <div class="category-picker"><label for="active-category">ردهٔ فعال</label><select id="active-category" v-model.number="selectedId"><option v-for="category in tournament.categories" :key="category.id" :value="category.id">{{ category.name }}</option></select></div>
                    </section>
                    <div class="category-facts"><span>{{ selected.discipline === 'freestyle' ? 'ابداعی' : 'استاندارد' }}</span><span>{{ { individual: 'انفرادی', pair: 'زوجی', team: 'تیمی' }[selected.entry_type] }}</span><span>{{ selected.judge_count }} داور</span><span>{{ selected.format === 'knockout' ? 'تک‌حذفی' : 'دورهای' }}</span></div>

                    <details v-if="can.manage && !['completed', 'archived'].includes(tournament.status)" id="draw-lots" class="panel" :open="!selected.registration_locked"><summary>ثبت‌نام، پذیرش، قرعهٔ پومسه و ساخت مراحل</summary><CategoryRegistration :key="selected.id" :category="selected" :tournament="tournament" :base="competitionUrls.base" /></details>
                    <CompetitionTimingSettings v-if="can.manage" :key="selected.id + '-timing'" :category="selected" :base="competitionUrls.base" />
                    <div v-if="selected.completed" class="notice">این رده پایان یافته است.<span v-if="selected.champion_id"> قهرمان: {{ selected.entries.find(entry => entry.id === selected.champion_id)?.name }}</span></div>

                    <section v-for="round in selected.rounds" :key="round.id" class="round-card">
                        <div class="section-title"><div><span class="eyebrow">ROUND {{ round.sequence }}</span><h2>{{ round.name }}</h2></div><span>{{ round.bouts.length }} {{ selected.score_based ? 'ورودی' : 'رقابت' }}</span></div>
                        <p v-if="!round.forms_ready" class="notice">در انتظار قرعهٔ پومسه؛ اجرای این مرحله تا تعیین فرم‌ها بسته است.</p>
                        <p v-if="!round.scheduled" class="subtle">جدول این مرحله هنوز آماده نشده است.</p>
                        <p v-else-if="!round.previous_completed" class="subtle">شروع این مرحله پس از پایان مرحلهٔ قبل مجاز است.</p>
                        <p v-if="round.form_names.length" class="subtle">پومسه‌های این مرحله: {{ round.form_names.join('، ') }}</p>
                        <BoutPanel v-for="bout in round.bouts" :key="bout.id" :bout="bout" :round="round" :category="selected" :tournament="tournament" :base="competitionUrls.base" :can-operate="can.operate" />
                    </section>

                    <section v-if="selected.standings.length" class="panel standings-card"><h2>{{ selected.display_label ? `${selected.display_label} · ` : '' }}{{ selected.score_based ? 'رتبه‌بندی امتیازی' : 'جدول بردها' }} {{ selected.completed ? '· پایان رده' : '· موقت' }}</h2><p class="subtle">{{ selected.score_based ? `نتیجهٔ ${selected.forms_per_round === 1 ? 'یک اجرا' : 'دو فرم'} رتبه را تعیین می‌کند. در تساوی، ابتدا نمرهٔ اجرا و سپس نمره‌های بازگردانده‌شدهٔ همهٔ داوران معیار است؛ برابری دوباره، رتبهٔ مشترک دارد.` : 'برد مساوی، رتبهٔ مشترک دارد؛ tie-break جدول دورهای خودکار اعمال نمی‌شود.' }}</p><div class="table-wrap"><table><thead><tr><th>رتبه</th><th>ورودی</th><th v-if="selected.standings.some(entry => entry.country_code)">کشور / تیم</th><th v-if="!selected.score_based">رقابت پایان‌یافته</th><th v-if="selected.score_based && selected.forms_per_round > 1 && selected.standings.some(entry => entry.r1)">R-1</th><th v-if="selected.score_based && selected.forms_per_round > 1 && selected.standings.some(entry => entry.r2)">R-2</th><th>{{ selected.score_based ? 'امتیاز نهایی' : 'برد' }}</th><th v-if="selected.score_based && selected.standings.some(entry => entry.tie_break_score)">امتیاز رفع تساوی</th></tr></thead><tbody><tr v-for="entry in selected.standings" :key="entry.id"><td>{{ entry.rank ?? '—' }}</td><td>{{ entry.short_name || entry.name }}</td><td v-if="selected.standings.some(item => item.country_code)">{{ entry.country_code || '—' }}</td><td v-if="!selected.score_based">{{ entry.played }}</td><td v-if="selected.score_based && selected.forms_per_round > 1 && selected.standings.some(item => item.r1)">{{ entry.r1 ?? '—' }}</td><td v-if="selected.score_based && selected.forms_per_round > 1 && selected.standings.some(item => item.r2)">{{ entry.r2 ?? '—' }}</td><td>{{ selected.score_based ? (entry.score ?? (entry.withdrawn ? 'انصراف' : 'در انتظار')) : entry.wins }}</td><td v-if="selected.score_based && selected.standings.some(item => item.tie_break_score)">{{ entry.tie_break_score ?? '—' }}</td></tr></tbody></table></div></section>
                </template>
            </div>

            <section v-if="can.manage && tournament.status === 'running'" class="finish-card"><div><h2>پایان مسابقه</h2><p>وقتی همهٔ رده‌ها و فینال‌ها پایان یافته‌اند، مسابقه را تأیید کنید.</p></div><FormErrors :errors="completion.errors" /><button type="button" :disabled="completion.processing || !tournament.categories.length || !tournament.categories.every(category => category.completed)" @click="completion.post(competitionUrls.base + '/complete', { preserveScroll: true })">تأیید پایان مسابقه</button></section>
        </div>
    </AppLayout>
</template>

<style scoped>
.admin-page{display:grid;gap:22px;color:#19352f}.admin-hero{display:flex;justify-content:space-between;align-items:flex-end;gap:26px;padding:30px 34px;border-radius:22px;background:linear-gradient(125deg,#123e36,#1f6655 64%,#337b60);color:white;box-shadow:0 16px 36px #143e3322}.hero-copy{min-width:0}.hero-overline,.eyebrow{font-size:10px;font-weight:800;letter-spacing:.15em;color:#9ed4bb}.admin-hero h1{margin:9px 0 13px;font-size:clamp(24px,2.7vw,38px);line-height:1.25;overflow-wrap:anywhere}.hero-meta{display:flex;align-items:center;flex-wrap:wrap;gap:9px 18px;color:#dcefe5;font-size:12px}.status-pill{padding:5px 12px;border:1px solid #b7dcc4;border-radius:999px;background:#ffffff24;color:white;font-weight:700}.hero-actions{display:flex;flex-wrap:wrap;gap:9px}.hero-actions a{display:flex;align-items:center;justify-content:space-between;gap:18px;min-width:135px;padding:10px 13px;border:1px solid #ffffff77;border-radius:10px;background:#ffffff17;font-size:12px;font-weight:700;white-space:nowrap}.hero-actions a:hover{background:#ffffff30}.overview-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.overview-grid article{display:flex;flex-direction:column;min-height:120px;padding:16px 19px;border:1px solid #dfe8e1;border-radius:16px;background:white}.overview-grid span{font-size:12px;color:#657970}.overview-grid strong{margin:4px 0 1px;font-size:32px;line-height:1.35;color:#1e5d4c}.overview-grid small{font-size:11px;color:#82928a}.readiness-card,.admin-content :deep(.panel),.round-card,.category-toolbar,.finish-card{border:1px solid #dfe8e1;border-radius:16px;background:white;box-shadow:0 4px 16px #244a2b08}.readiness-card{padding:19px 22px}.section-title{display:flex;align-items:center;justify-content:space-between;gap:15px;margin-bottom:17px}.section-title h2{margin:4px 0 0;font-size:17px}.section-title>span{color:#6f8277;font-size:11px}.readiness-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}.readiness-step{display:flex;align-items:center;gap:11px;min-width:0;padding:12px;border:1px solid #e5ebe5;border-radius:12px;background:#f8faf8}.readiness-step.done{border-color:#cbe3d3;background:#f1f8f3}.step-number{display:grid;flex:none;place-items:center;width:32px;height:32px;border-radius:10px;background:#e7ede8;color:#667e6e;font-size:12px;font-weight:800}.done .step-number{background:#286957;color:white}.readiness-step strong,.readiness-step small{display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.readiness-step strong{font-size:12px}.readiness-step small{font-size:10px;color:#7b8c81}.admin-tabs{display:flex;gap:6px;border-bottom:1px solid #dfe8e1}.admin-tabs button{display:flex;align-items:center;gap:9px;padding:12px 18px;border:0;border-bottom:3px solid transparent;background:none;color:#73877b;font-size:13px;font-weight:700}.admin-tabs button.active{border-color:#286957;color:#205a48}.admin-tabs button span{padding:0 8px;border-radius:9px;background:#e8f1e9;font-size:11px}.admin-content{display:grid;gap:18px;min-width:0}.admin-content :deep(.setup-grid){display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.admin-content :deep(.setup-grid>.wide){grid-column:1/-1}.admin-content :deep(.panel){min-width:0;padding:23px}.admin-content :deep(.panel h2){margin-bottom:15px;font-size:17px}.admin-content :deep(.panel form){margin-top:15px}.admin-content :deep(.panel .section-heading){display:flex;justify-content:space-between;align-items:center;gap:10px;margin:10px 0}.admin-content :deep(.fields-two),.admin-content :deep(.tournament-form){display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:13px}.admin-content :deep(.wide){grid-column:1/-1}.admin-content :deep(.button),.admin-content :deep(button.button){display:inline-flex;justify-content:center;align-items:center;min-height:39px;margin-top:7px;padding:8px 14px;border:1px solid #286957;border-radius:9px;background:#286957;color:white;font-size:12px;font-weight:700}.admin-content :deep(.button.secondary){border-color:#d5e1d7;background:#f2f6f1;color:#2d6650}.admin-content :deep(.button.small){min-height:32px}.admin-content :deep(.row-actions){display:flex;flex-wrap:wrap;gap:8px}.admin-content :deep(.subtle){color:#73877a;font-size:12px}.admin-content :deep(.notice){padding:12px 14px;border:1px solid #e7dba6;border-radius:10px;background:#fffbea;color:#735c2a;font-size:12px}.admin-content :deep(.member-list){margin-top:17px}.admin-content :deep(.member-list>div){display:flex;justify-content:space-between;gap:10px;padding:9px 0;border-top:1px solid #e9eee8}.admin-content :deep(.badge),.category-facts span{display:inline-flex;align-items:center;padding:4px 9px;border-radius:8px;background:#eaf3ec;color:#27634f;font-size:11px;font-weight:700}.admin-content :deep(.table-wrap){overflow-x:auto}.admin-content :deep(table){min-width:580px}.admin-content :deep(details>summary){font-size:14px}.category-toolbar{display:flex;align-items:end;justify-content:space-between;gap:18px;padding:18px 22px}.category-toolbar h2{font-size:19px}.category-picker{width:min(340px,100%)}.category-picker label{margin:0 0 5px}.category-facts{display:flex;flex-wrap:wrap;gap:7px;margin-top:-8px}.round-card{padding:20px 22px}.round-card :deep(.panel){box-shadow:none}.admin-empty{padding:46px 24px;border:1px dashed #cbdcd0;border-radius:16px;background:#f7faf7;text-align:center}.admin-empty p{margin:8px 0 16px;color:#788b7b}.admin-empty button,.finish-card button{padding:9px 15px;border:0;border-radius:9px;background:#286957;color:white;font-weight:700}.standings-card{overflow:hidden}.finish-card{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px;padding:19px 22px}.finish-card p{color:#77887c;font-size:12px}.finish-card button:disabled{background:#9caea0}
.summary-enter-active,.summary-leave-active{overflow:hidden;transition:grid-template-rows 280ms cubic-bezier(.22,1,.36,1),opacity 180ms ease}.summary-enter-from,.summary-leave-to{grid-template-rows:0fr;opacity:0}.summary-enter-to,.summary-leave-from{grid-template-rows:1fr;opacity:1}
@media(max-width:1050px){.readiness-grid,.overview-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.admin-hero{align-items:flex-start;flex-direction:column}}
@media(max-width:700px){.admin-page{gap:16px}.admin-hero{padding:22px;border-radius:16px}.hero-actions{width:100%}.hero-actions a{flex:1}.readiness-card{padding:16px}.readiness-grid{grid-template-columns:1fr 1fr}.admin-content :deep(.setup-grid),.admin-content :deep(.fields-two),.admin-content :deep(.tournament-form){grid-template-columns:1fr}.admin-content :deep(.setup-grid>.wide),.admin-content :deep(.wide){grid-column:1}.category-toolbar{align-items:stretch;flex-direction:column}.admin-content :deep(.panel){padding:17px}}
@media(max-width:480px){.overview-grid{gap:8px}.overview-grid article{min-height:100px;padding:12px}.readiness-grid{grid-template-columns:1fr}.hero-actions a{min-width:0}.admin-tabs button{padding:11px;font-size:12px}}
</style>
