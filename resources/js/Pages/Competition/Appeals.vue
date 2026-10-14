<script setup>
import { computed } from 'vue';
import { Head, Link, router, useForm, usePage, usePoll } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import FormErrors from '../../Components/FormErrors.vue';
import AppealDecisionForm from '../../Components/AppealDecisionForm.vue';
import { useCompetitionUpdates } from '../../useCompetitionUpdates';
const props = defineProps({ tournament: Object, appeals: Object, candidates: Array, statusFilter: String, canFile: Boolean, canApply: Boolean, urls: Object });
const page = usePage();
useCompetitionUpdates(props.tournament.id, ['appeals', 'candidates', 'canFile', 'canApply']);
usePoll(5000, { only: ['appeals', 'candidates', 'canFile', 'canApply'] });
const labels = { submitted: 'ثبت‌شده؛ در انتظار بررسی', reviewing: 'در حال بررسی ناظر', accepted: 'پذیرفته؛ در انتظار اعمال رأی', rejected: 'ردشده؛ نتیجه حفظ شد', applied: 'رأی اعمال و پرونده تکمیل شد' };
const filing = useForm({ performance_id: '', expected_version: 1, representative_name: '', reason: '', evidence: '' });
const selected = computed(() => props.candidates.find(item => item.id === Number(filing.performance_id)));
const action = useForm({ expected_version: 1 });
const file = () => { if (!selected.value) return; filing.expected_version = selected.value.version; filing.post(props.urls.store, { preserveScroll: true, onSuccess: () => filing.reset() }); };
const command = (appeal, name) => { action.expected_version = appeal.version; action.post(props.urls.index + '/' + appeal.id + '/' + name, { preserveScroll: true }); };
const date = value => value ? new Date(value).toLocaleString('fa-IR') : '—';
const score = value => value == null ? '—' : Number(value).toLocaleString('fa-IR', { maximumFractionDigits: 6 });
</script>
<template>
    <AppLayout><Head title="اعتراض‌ها و رأی ناظر"/>
        <section class="page-heading"><div><span class="eyebrow">{{ tournament.name }}</span><h1>اعتراض‌ها و رأی ناظر</h1><p class="subtle">اعتراض به نتیجهٔ منتشرشده، تا قبل از شروع مرحلهٔ بعدِ همان رده ثبت می‌شود. هر اجرا یک پرونده دارد.</p></div><Link :href="urls.back" class="button secondary">بازگشت</Link></section>
        <section v-if="canFile" class="panel"><h2>ثبت اعتراض نمایندهٔ تیم</h2>
            <p v-if="!candidates.length" class="subtle">نتیجهٔ قابل اعتراض وجود ندارد؛ اجرا باید تأیید شده باشد و مرحلهٔ بعد شروع نشده باشد.</p>
            <form v-else class="tournament-form" @submit.prevent="file">
                <div class="wide"><label for="appeal-performance">نتیجهٔ مورد اعتراض</label><select id="appeal-performance" v-model.number="filing.performance_id" required><option value="" disabled>انتخاب اجرا</option><option v-for="item in candidates" :key="item.id" :value="item.id">{{ item.label }}</option></select></div>
                <div class="wide"><label for="appeal-representative">نام نمایندهٔ تیم یا معترض</label><input id="appeal-representative" v-model="filing.representative_name" required minlength="2" maxlength="150"></div>
                <div class="wide"><label for="appeal-reason">شرح اعتراض و اصلاح مورد درخواست</label><textarea id="appeal-reason" v-model="filing.reason" required minlength="10" maxlength="2000"></textarea></div>
                <div class="wide"><label for="appeal-evidence">شرح شواهد یا نشانی ویدئو (اختیاری)</label><textarea id="appeal-evidence" v-model="filing.evidence" maxlength="5000"></textarea></div>
                <FormErrors class="wide" :errors="filing.errors"/><button class="button" :disabled="filing.processing || !selected">ثبت اعتراض</button>
            </form>
        </section>
        <div class="section-heading"><h2>پرونده‌ها</h2><div><label for="appeal-status">وضعیت</label><select id="appeal-status" :value="statusFilter" @change="router.get(urls.index, $event.target.value ? { status: $event.target.value } : {}, { preserveScroll: true })"><option value="">همه</option><option v-for="(label, value) in labels" :key="value" :value="value">{{ label }}</option></select></div></div>
        <FormErrors :errors="action.errors"/>
        <section v-for="appeal in appeals.data" :key="appeal.id" class="panel">
            <div class="section-heading"><h2>اعتراض {{ appeal.id }} · {{ appeal.current.entry_name }}</h2><span class="badge">{{ labels[appeal.status] }}</span></div>
            <p>{{ appeal.current.category_name }} · {{ appeal.current.round_name }} · فرم {{ appeal.current.form_number }}</p>
            <p>نماینده: {{ appeal.representative_name }} · ثبت‌کننده: {{ appeal.filed_by_name }} · {{ date(appeal.created_at) }}</p>
            <p class="appeal-text">{{ appeal.reason }}</p><details v-if="appeal.evidence"><summary>شواهد ثبت‌شده</summary><p class="appeal-text">{{ appeal.evidence }}</p></details>
            <p>نتیجه هنگام ثبت اعتراض: <b>{{ score(appeal.original_snapshot.result) }}</b> · نتیجهٔ فعلی: <b>{{ score(appeal.current.result) }}</b></p>
            <details><summary>نمره‌های داورها و سابقهٔ نتیجه</summary><table><thead><tr><th>صندلی</th><th>داور</th><th>دقت هنگام اعتراض</th><th>اجرا هنگام اعتراض</th><th>ویرایش اولیه</th></tr></thead><tbody><tr v-for="sheet in appeal.original_snapshot.scores" :key="sheet.id"><td>{{ sheet.seat }}</td><td>{{ sheet.judge_name }}</td><td>{{ (sheet.values.accuracy / 100).toFixed(2) }}</td><td>{{ (sheet.values.presentation / 100).toFixed(2) }}</td><td>{{ sheet.revision }}</td></tr></tbody></table><p v-if="appeal.original_snapshot.calculation?.timing?.penalty_hundredths">جریمهٔ زمان محفوظ: {{ (appeal.original_snapshot.calculation.timing.penalty_hundredths / 100).toFixed(2) }} امتیاز</p></details>
            <p v-if="appeal.reviewed_by_name">ناظر: {{ appeal.reviewed_by_name }} · شروع بررسی: {{ date(appeal.review_started_at) }}</p>
            <template v-if="appeal.decision_reason"><h3>رأی و دلیل ناظر</h3><p class="appeal-text">{{ appeal.decision_reason }}</p><p>زمان رأی: {{ date(appeal.decided_at) }}</p></template>
            <template v-if="appeal.corrections?.length"><h3>اصلاح‌های تأییدشده</h3><ul><li v-for="correction in appeal.corrections" :key="correction.judge_assignment_id">صندلی {{ appeal.current.scores.find(sheet => sheet.judge_assignment_id === correction.judge_assignment_id)?.seat }} · {{ correction.score ? 'نمره: ' + correction.score : 'دقت: ' + correction.accuracy + ' · اجرا: ' + correction.presentation }}</li></ul><p>نتیجهٔ مصوب پس از اصلاح و جریمه‌ها: <b>{{ score(appeal.decision_snapshot.proposed_result.score) }}</b></p></template>
            <p v-if="appeal.applied_by_name">اعمال رأی: {{ appeal.applied_by_name }} · {{ date(appeal.applied_at) }} · نتیجه: {{ score(appeal.applied_snapshot.result) }}</p>
            <button v-if="appeal.can_review && appeal.status === 'submitted'" class="button" :disabled="action.processing" @click="command(appeal, 'review')">شروع بررسی توسط ناظر</button>
            <AppealDecisionForm v-if="appeal.can_review && appeal.status === 'reviewing' && appeal.reviewed_by === page.props.auth.user.id" :key="appeal.id + '-' + appeal.version" :appeal="appeal" :endpoint="urls.index + '/' + appeal.id + '/decision'"/>
            <button v-if="canApply && appeal.status === 'accepted'" class="button" :disabled="action.processing" @click="command(appeal, 'apply')">اعمال اصلاح‌های تأییدشده و تکمیل پرونده</button>
        </section>
        <p v-if="!appeals.data.length" class="empty">پرونده‌ای با این وضعیت ثبت نشده است.</p>
        <nav class="row-actions" aria-label="صفحه‌بندی اعتراض‌ها"><template v-for="(link, index) in appeals.links" :key="index"><Link v-if="link.url" :href="link.url" class="button secondary" :class="{ selected: link.active }">{{ index === 0 ? 'قبلی' : index === appeals.links.length - 1 ? 'بعدی' : link.label }}</Link></template></nav>
    </AppLayout>
</template>
<style scoped>.appeal-text { white-space: pre-wrap; overflow-wrap: anywhere; }</style>
