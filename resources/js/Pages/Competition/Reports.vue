<script setup>
import { computed, watch } from 'vue';
import { Head, Link, router, useForm, usePoll } from '@inertiajs/vue3';
import AppLayout from '../../Shared/Layouts/AppLayout.vue';
import FormErrors from '../../Shared/Components/FormErrors.vue';
import AuditSnapshot from '../../Components/competition/AuditSnapshot.vue';
import { useCompetitionUpdates } from '../../Shared/Composables/useCompetitionUpdates';

const props = defineProps({ tournament: Object, type: String, title: String, report: Object, filters: Object, options: Object, canArchive: Boolean, reportUrls: Object });
const filters = useForm({ category_id: '', judge_id: '', user_id: '', action: '', from: '', to: '', ...props.filters });
const archive = useForm({ expected_status: props.tournament.status, reason: '' });
watch(() => props.filters, value => {
    filters.defaults({ category_id: '', judge_id: '', user_id: '', action: '', from: '', to: '', ...value });
    filters.reset();
});
watch(() => props.tournament.status, status => { archive.expected_status = status; archive.reason = ''; });
useCompetitionUpdates(props.tournament.id, ['report', 'options', 'tournament']);
usePoll(5000, { only: ['report', 'options', 'tournament'] });
const statuses = { draft: 'پیش‌نویس', ready: 'آماده', running: 'در حال برگزاری', completed: 'پایان‌یافته', archived: 'آرشیو' };
const summaries = { total: 'تعداد ردیف', submitted: 'نمرهٔ ثبت‌شده', draft: 'منتظر تأیید جایگزین', corrected: 'نمرهٔ اصلاح‌شده', gold: 'طلا', silver: 'نقره', bronze: 'برنز' };
const descriptions = {
    judges: 'جمع خام، نمرهٔ همان داور است و ممکن است با نتیجهٔ محاسبه‌شده از تمام داورها تفاوت داشته باشد. نمرهٔ جایگزینِ منتظر تأیید در نتیجه لحاظ نمی‌شود.',
    audit: 'سوابق به ترتیب جدیدترین تغییر نمایش داده می‌شوند. برای مشاهدهٔ جزئیات، اطلاعات قبل و بعد را باز کنید.',
    medals: 'مدال‌های رده‌های پایان‌یافته: تک‌حذفی یک طلا، یک نقره و بازندگان نیمه‌نهایی برنز؛ دوره‌ای مطابق رتبهٔ نهایی. رتبه‌های مساوی صریحاً مشترک‌اند. اصلاح نتیجه می‌تواند مدال‌بندی را تغییر دهد.',
};
const allowedFilters = computed(() => props.type === 'judges' ? ['category_id', 'judge_id', 'from', 'to'] : props.type === 'audit' ? ['user_id', 'action', 'from', 'to'] : ['category_id']);
const applyFilters = () => filters.transform(data => Object.fromEntries(allowedFilters.value.map(key => [key, data[key]]))).get(props.reportUrls[props.type], { preserveState: true, preserveScroll: true, replace: true });
const resetFilters = () => router.get(props.reportUrls[props.type]);
const print = () => window.print();
const cell = (value, index) => value === '' || value === null ? '—' : props.report.decimal_columns.includes(index) ? Number(value).toFixed(3) : value;
const updateArchive = () => archive.post(props.tournament.status === 'archived' ? props.reportUrls.restore : props.reportUrls.archive, { preserveScroll: true, onSuccess: () => archive.reset('reason') });
</script>
<template>
    <AppLayout>
        <Head :title="title + ' · ' + tournament.name" />
        <div class="competition-reports">
            <section class="page-heading"><div><span class="eyebrow">گزارش‌های مسابقه</span><h1>{{ title }}</h1><p class="subtle">{{ tournament.name }} · {{ statuses[tournament.status] }} · {{ tournament.timezone }}</p></div><div class="row-actions report-actions"><Link :href="reportUrls.back" class="button secondary">بازگشت به مسابقه</Link><Link :href="reportUrls.archives" class="button secondary">فهرست آرشیو</Link></div></section>
            <nav class="tabs report-actions"><Link v-for="(label, key) in { judges: 'گزارش داورها', audit: 'سوابق تغییرات', medals: 'مدال‌بندی' }" :key="key" :href="reportUrls[key]" :class="{ selected: type === key }">{{ label }}</Link></nav>
            <p class="subtle">{{ descriptions[type] }}</p>
            <p v-if="tournament.status === 'archived'" class="notice">این مسابقه آرشیو شده است. گزارش‌ها و نتایج قابل مشاهده‌اند و ثبت یا اصلاح نمره بسته است.</p>
            <form class="panel report-filters report-actions" @submit.prevent="applyFilters">
                <div v-if="type !== 'audit'"><label for="report-category">رده</label><select id="report-category" v-model="filters.category_id"><option value="">همهٔ رده‌ها</option><option v-for="category in options.categories" :key="category.id" :value="category.id">{{ category.name }}</option></select></div>
                <div v-if="type === 'judges'"><label for="report-judge">داور</label><select id="report-judge" v-model="filters.judge_id"><option value="">همهٔ داورها</option><option v-for="judge in options.judges" :key="judge.id" :value="judge.id">{{ judge.name }}</option></select></div>
                <div v-if="type === 'audit'"><label for="report-actor">کاربر ثبت‌کننده</label><select id="report-actor" v-model="filters.user_id"><option value="">همهٔ کاربران</option><option v-for="actor in options.actors" :key="actor.id" :value="actor.id">{{ actor.name }}</option></select></div>
                <div v-if="type === 'audit'"><label for="report-action">نوع تغییر</label><select id="report-action" v-model="filters.action"><option value="">همهٔ تغییرات</option><option v-for="action in options.actions" :key="action.id" :value="action.id">{{ action.name }}</option></select></div>
                <template v-if="type !== 'medals'"><div><label for="report-from">از تاریخ (میلادی)</label><input id="report-from" v-model="filters.from" type="date" dir="ltr" /></div><div><label for="report-to">تا تاریخ (میلادی)</label><input id="report-to" v-model="filters.to" type="date" :min="filters.from" dir="ltr" /></div></template>
                <div class="row-actions"><button class="button" :disabled="filters.processing">اعمال فیلتر</button><button type="button" class="button secondary" @click="resetFilters">پاک‌کردن</button></div>
                <FormErrors :errors="filters.errors" />
            </form>
            <div class="report-summary"><article v-for="(value, key) in report.summary" :key="key"><span>{{ summaries[key] || key }}</span><b>{{ value.toLocaleString('fa-IR') }}</b></article></div>
            <p v-if="report.pending_categories?.length" class="notice">مدال رده‌های {{ report.pending_categories.join('، ') }} پس از پایان و تأیید جدول صعود نمایش داده می‌شود.</p>
            <div class="row-actions report-actions report-downloads"><a :href="reportUrls.excel" class="button">دریافت Excel مرتب</a><a :href="reportUrls.csv" class="button secondary">دریافت CSV</a><button type="button" class="button secondary" @click="print">چاپ صفحهٔ گزارش</button><span class="subtle">فایل خروجی شامل تمام ردیف‌های فیلترشده است.</span></div>
            <div v-if="report.rows.length" class="panel report-table-wrap"><table class="report-table"><thead><tr><th v-for="header in report.headers" :key="header" scope="col">{{ header }}</th></tr></thead><tbody><tr v-for="row in report.rows" :key="row[0]"><td v-for="(value, index) in row" :key="index" :class="{ 'report-number': report.numeric_columns.includes(index), 'report-medal': type === 'medals' && index === 4 }"><details v-if="type === 'audit' && [6, 7].includes(index) && value"><summary>مشاهدهٔ اطلاعات</summary><AuditSnapshot :value="value" /></details><template v-else>{{ cell(value, index) }}</template></td></tr></tbody></table></div>
            <p v-else class="empty">برای این گزارش و فیلترها، اطلاعاتی ثبت نشده است.</p>
            <nav v-if="report.pagination" class="pagination report-actions" aria-label="صفحه‌بندی گزارش"><Link v-if="report.pagination.prev_page_url" :href="report.pagination.prev_page_url">صفحهٔ قبل</Link><span>{{ report.pagination.current_page }} / {{ report.pagination.last_page }} · {{ report.pagination.total.toLocaleString('fa-IR') }} ردیف</span><Link v-if="report.pagination.next_page_url" :href="report.pagination.next_page_url">صفحهٔ بعد</Link></nav>
            <section v-if="canArchive && ['completed', 'archived'].includes(tournament.status)" class="panel archive-controls report-actions"><h2>{{ tournament.status === 'archived' ? 'بازگرداندن از آرشیو' : 'آرشیو مسابقه' }}</h2><p class="subtle">{{ tournament.status === 'archived' ? 'مسابقه به وضعیت پایان‌یافته بازمی‌گردد و دوباره وارد صف اجرا نمی‌شود.' : 'نتایج و سوابق حفظ می‌شوند؛ پس از آرشیو ثبت و اصلاح نمره بسته می‌شود.' }}</p><form @submit.prevent="updateArchive"><label for="archive-reason">دلیل</label><textarea id="archive-reason" v-model="archive.reason" required minlength="5" maxlength="500" :disabled="archive.processing" /><FormErrors :errors="archive.errors" /><button class="button" :disabled="archive.processing">{{ archive.processing ? 'در حال ثبت…' : tournament.status === 'archived' ? 'بازگرداندن به پایان‌یافته' : 'آرشیو مسابقه' }}</button></form></section>
        </div>
    </AppLayout>
</template>
