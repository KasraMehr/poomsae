<script setup>
import { useForm } from '@inertiajs/vue3';
import FormErrors from './common/FormErrors.vue';
const props = defineProps({ bout: Object, category: Object, tournament: Object, base: String, canOperate: Boolean });
const action = useForm({ command: '', expected_version: 1 });
const resolution = useForm({ winner_entry_id: '', reason: '' });
const walkover = useForm({ winner_entry_id: '', reason: '', decision_type: 'walkover', confirmed: false });
const command = (performance,value) => { action.command = value; action.expected_version = performance.version; action.post(props.base + '/performances/' + performance.id + '/command', { preserveScroll: true }); };
const entryName = (id) => props.bout.entries.find(e => e.id === id)?.name || '—';
const labels = {pending:'در انتظار',running:'در حال اجرا',scoring:'دریافت نمره',approved:'تأیید و منتشر شده',cancelled:'لغو شده',completed:'پایان‌یافته'};
const fmt = value => value === null || value === undefined ? '—' : Number(value).toLocaleString('fa-IR',{minimumFractionDigits:3,maximumFractionDigits:6});
</script>
<template>
    <article class="bout-panel">
        <div class="bout-heading"><div><span class="eyebrow">رقابت {{ bout.sequence }} · {{ tournament.courts.find(c => c.id === bout.court_id)?.name }}</span><h3><span v-for="(entry,index) in bout.entries" :key="entry.id"><span :class="entry.side">{{ entry.name }}</span><span v-if="index === 0 && bout.entries.length === 2" class="versus"> / </span></span></h3></div><span class="badge" :class="bout.status">{{ labels[bout.status] }}</span></div>
        <div v-if="bout.winner_entry_id" class="winner">برنده: {{ entryName(bout.winner_entry_id) }} <small>{{ bout.resolution_reason }}</small></div>
        <div v-if="bout.performances.length" class="performance-grid">
            <section v-for="p in bout.performances" :key="p.id" class="performance" :class="p.status">
                <div class="card-top"><b>{{ entryName(p.entry_id) }}</b><span class="badge" :class="p.status">{{ labels[p.status] }}</span></div>
                <p>{{ p.form_name }} <small>فرم {{ p.form_number }} از ۲</small></p>
                <div class="score-result">{{ fmt(p.result) }}</div>
                <p v-if="p.status === 'scoring'" class="subtle">{{ p.submitted_count }} از {{ category.judge_count }} داور نمره داده‌اند.</p>
                <div v-if="canOperate && tournament.status !== 'completed'" class="row-actions">
                    <button v-if="p.status === 'pending'" class="button small" :disabled="action.processing" @click="command(p,'start')">شروع اجرا</button>
                    <button v-if="p.status === 'running'" class="button small" :disabled="action.processing" @click="command(p,'finish')">پایان اجرا و دریافت نمره</button>
                    <button v-if="p.status === 'scoring'" class="button small" :disabled="action.processing || p.submitted_count !== category.judge_count" @click="command(p,'approve')">تأیید نهایی و انتشار</button>
                </div>
                <details v-if="canOperate && p.scores.length"><summary>نمرهٔ داوران</summary><div class="judge-score-row" v-for="score in p.scores" :key="score.seat"><span>صندلی {{ score.seat }} · ویرایش {{ score.revision }}</span><b>{{ (score.values.accuracy / 100).toFixed(2) }} + {{ (score.values.presentation / 100).toFixed(2) }}</b></div></details>
            </section>
        </div>
        <FormErrors :errors="action.errors"/>
        <details v-if="canOperate && ['pending','running'].includes(bout.status) && bout.performances.some(p=>p.status !== 'approved')" class="walkover">
            <summary>انصراف یا عدم حضور</summary>
            <form @submit.prevent="walkover.post(base + '/bouts/' + bout.id + '/resolve', { preserveScroll: true })">
                <label :for="'walkover-winner-'+bout.id">ورزشکار برنده</label><select :id="'walkover-winner-'+bout.id" v-model.number="walkover.winner_entry_id" required><option value="" disabled>انتخاب برنده</option><option v-for="entry in bout.entries" :key="entry.id" :value="entry.id">{{ entry.name }}</option></select>
                <label :for="'walkover-reason-'+bout.id">شرح انصراف یا عدم حضور طرف مقابل</label><textarea :id="'walkover-reason-'+bout.id" v-model="walkover.reason" required minlength="5" maxlength="900"></textarea>
                <label class="check"><input v-model="walkover.confirmed" type="checkbox" required>تأیید می‌کنم اجراهای تأییدنشدهٔ این رقابت لغو و برنده ثبت شود.</label>
                <FormErrors :errors="walkover.errors"/><button class="button secondary" :disabled="walkover.processing">ثبت تصمیم و پایان رقابت</button>
            </form>
        </details>
        <div v-if="Object.keys(bout.totals).length" class="totals"><span v-for="(score,id) in bout.totals" :key="id">{{ entryName(Number(id)) }} <b>{{ fmt(score) }}</b></span></div>
        <form v-if="canOperate && bout.status === 'running' && Object.keys(bout.totals).length === 2 && !bout.winner_entry_id" class="tie-form" @submit.prevent="resolution.post(base + '/bouts/' + bout.id + '/resolve', { preserveScroll: true })">
            <h3>تساوی؛ تصمیم سرداور لازم است</h3><p>برنده را فقط مطابق آیین‌نامهٔ رویداد مشخص کنید. دلیل تصمیم در سابقهٔ مسابقه باقی می‌ماند.</p>
            <label :for="'winner-'+bout.id">برنده</label><select :id="'winner-'+bout.id" v-model.number="resolution.winner_entry_id" required><option value="" disabled>انتخاب ورزشکار</option><option v-for="entry in bout.entries" :key="entry.id" :value="entry.id">{{ entry.name }}</option></select>
            <label :for="'reason-'+bout.id">دلیل تصمیم</label><textarea :id="'reason-'+bout.id" v-model="resolution.reason" required minlength="5" maxlength="1000"></textarea>
            <FormErrors :errors="resolution.errors"/><button class="button" :disabled="resolution.processing">ثبت برنده و دلیل</button>
        </form>
    </article>
</template>
