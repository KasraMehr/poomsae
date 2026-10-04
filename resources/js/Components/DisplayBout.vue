<script setup>
import { entryTotal, elapsed, formatScore, statusLabels } from '../display';
defineProps({ bout: Object, category: Object, round: Object, courtName: String, now: Number });
const ranks = (category, round, entryId) => category.standings_round_id === round.id ? category.standings.find(entry => entry.id === entryId)?.rank : null;
</script>

<template>
    <section class="live-bout display-bout">
        <div class="section-heading"><div><span class="eyebrow">{{ courtName }} · {{ statusLabels[round.status] }}</span><h2>{{ category.name }} · {{ round.name }} · رقابت {{ bout.sequence }}</h2></div><span class="display-badge" :class="bout.status">{{ statusLabels[bout.status] }}</span></div>
        <div class="contestants" :class="{ solo: bout.entries.length === 1 }">
            <article v-for="entry in bout.entries" :key="entry.id" :class="category.format === 'knockout' ? entry.side : 'table-entry'">
                <span class="eyebrow">{{ { individual: 'انفرادی', pair: 'زوجی', team: 'تیمی' }[category.entry_type] }} · {{ category.format === 'knockout' ? (entry.side === 'chung' ? 'چونگ · آبی' : 'هونگ · قرمز') : 'Table' }}</span>
                <h2>{{ entry.name }}</h2>
                <div v-for="performance in bout.performances.filter(item => item.entry_id === entry.id).sort((a, b) => a.form_number - b.form_number)" :key="performance.id" class="display-form">
                    <span>{{ performance.form_name }}</span><strong :class="{ 'temporary-score': performance.result == null && performance.preview_result != null }">{{ formatScore(performance.result ?? performance.preview_result) }}</strong>
                    <small>{{ statusLabels[performance.status] }}<span v-if="performance.status === 'running'"> · {{ elapsed(performance, now) }}</span><span v-if="performance.status === 'scoring'"> · {{ performance.submitted_count }} از {{ category.judge_count }} نمره دریافت شد</span></small>
                    <small v-if="performance.result == null && performance.preview_result != null" class="temporary-score">امتیاز موقت · در انتظار تأیید مسئول</small>
                </div>
                <p v-if="!bout.performances.length" class="display-bye">استراحت در جدول · صعود بدون اجرا</p>
                <div class="display-total"><span>{{ category.forms_per_round === 1 ? 'نمرهٔ اجرا' : category.rules?.aggregation === 'mean_two_forms' ? 'میانگین دو پومسه' : 'مجموع امتیاز' }}<small v-if="entryTotal(bout, category, entry.id).temporary"> · موقت</small></span><b>{{ formatScore(entryTotal(bout, category, entry.id).score) }}</b></div>
                <p v-if="bout.winner_entry_id === entry.id" class="display-winner">برندهٔ رقابت<span v-if="category.champion_id === entry.id"> · قهرمان رده</span></p>
                <p v-if="ranks(category, round, entry.id)" class="display-winner">رتبهٔ {{ ranks(category, round, entry.id).toLocaleString('fa-IR') }}</p>
            </article>
        </div>
        <p v-if="bout.status === 'running' && bout.performances.length && bout.performances.every(performance => performance.status === 'approved') && !bout.winner_entry_id" class="display-tie">تساوی · در انتظار تصمیم سرداور</p>
    </section>
</template>
