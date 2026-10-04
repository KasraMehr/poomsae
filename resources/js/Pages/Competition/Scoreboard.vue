<script setup>
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import DisplayBout from '../../Components/DisplayBout.vue';
import { useDisplayUpdates } from '../../useDisplayUpdates';
import { connectionLabels, entryTotal, formatScore, statusLabels } from '../../display';
const props = defineProps({ display: Object, displayUrls: Object });
const { display, now, connection } = useDisplayUpdates(props.display, props.displayUrls.data);
const tournament = computed(() => display.value.tournament);
const courtId = ref('');
const categoryId = ref('');
const categories = computed(() => tournament.value.categories.filter(category => !categoryId.value || category.id === Number(categoryId.value)));
const bouts = computed(() => categories.value.flatMap(category => category.rounds.flatMap(round => round.bouts
    .filter(bout => !courtId.value || bout.court_id === Number(courtId.value))
    .map(bout => ({ bout, category, round })))));
const active = computed(() => bouts.value.filter(item => item.bout.status === 'running'));
const recent = computed(() => bouts.value.filter(item => item.bout.status === 'completed').sort((a, b) => b.bout.id - a.bout.id).slice(0, 2));
const courtName = id => tournament.value.courts.find(court => court.id === id)?.name || 'زمین تعیین نشده';
const stageBouts = round => round.bouts.filter(bout => !courtId.value || bout.court_id === Number(courtId.value));
</script>

<template>
    <div class="scoreboard hall-display">
        <Head :title="`نمایشگر سالن · ${tournament.name}`" />
        <header class="scoreboard-header"><div><span class="eyebrow">نمایشگر اصلی سالن</span><h1>{{ tournament.name }}</h1><p>{{ statusLabels[tournament.status] }}</p></div><div class="display-controls"><label>زمین<select v-model="courtId"><option value="">همهٔ زمین‌ها</option><option v-for="court in tournament.courts" :key="court.id" :value="court.id">{{ court.name }}</option></select></label><label>رده<select v-model="categoryId"><option value="">همهٔ رده‌ها</option><option v-for="category in tournament.categories" :key="category.id" :value="category.id">{{ category.name }}</option></select></label><Link :href="displayUrls.rtds">نمایشگر RTDS</Link><Link :href="displayUrls.back">بازگشت</Link></div></header>
        <p class="display-connection" :class="connection" role="status">{{ connectionLabels[connection] }}<span v-if="connection === 'denied'"> · <Link :href="displayUrls.back">ورود مجدد</Link></span></p>
        <section v-if="!active.length" class="scoreboard-wait"><span>{{ tournament.status === 'completed' ? 'مسابقه پایان یافته است' : 'آمادهٔ اجرای بعدی' }}</span><p>امتیاز موقت پس از دریافت همهٔ نمره‌ها و نتیجهٔ قطعی پس از تأیید مسئول نمایش داده می‌شود.</p></section>
        <DisplayBout v-for="item in active" :key="item.bout.id" v-bind="item" :court-name="courtName(item.bout.court_id)" :now="now" />
        <section v-if="recent.length" class="display-section"><h2>آخرین نتایج قطعی</h2><DisplayBout v-for="item in recent" :key="item.bout.id" v-bind="item" :court-name="courtName(item.bout.court_id)" :now="now" /></section>
        <section v-for="category in categories" :key="category.id" class="display-section">
            <div class="section-heading"><h2>{{ category.name }} · {{ category.format === 'knockout' ? 'جدول تک‌حذفی' : 'Table' }}</h2><span v-if="category.champion_id" class="display-winner">قهرمان: {{ category.entries.find(entry => entry.id === category.champion_id)?.name }}</span></div>
            <div class="display-stages"><article v-for="round in category.rounds" :key="round.id" class="display-stage"><div class="section-heading"><h3>{{ round.name }}</h3><span class="display-badge" :class="round.status">{{ statusLabels[round.status] }}</span></div><p>{{ category.discipline === 'freestyle' ? 'یک اجرای ابداعی با موسیقی' : (round.form_names.length ? round.form_names.join('، ') : category.form_names.length ? category.form_names.join('، ') : 'در انتظار قرعهٔ پومسه') }}</p><p v-if="!round.scheduled">جدول مرحله هنوز ساخته نشده است.</p><p v-else-if="!round.previous_completed">در انتظار پایان مرحلهٔ قبل</p>
                <div v-if="stageBouts(round).length" class="table-wrap"><table><thead><tr><th>رقابت / زمین</th><th>بازیکن یا تیم</th><th>نمره‌های پومسه‌ها</th><th>مجموع / نتیجه</th></tr></thead><tbody><tr v-for="bout in stageBouts(round)" :key="bout.id"><td>{{ bout.sequence.toLocaleString('fa-IR') }} · {{ courtName(bout.court_id) }}<small>{{ statusLabels[bout.status] }}</small></td><td><p v-for="entry in bout.entries" :key="entry.id" :class="category.format === 'knockout' ? entry.side : ''">{{ entry.name }}</p></td><td><p v-for="entry in bout.entries" :key="entry.id"><span v-for="performance in bout.performances.filter(item => item.entry_id === entry.id).sort((a, b) => a.form_number - b.form_number)" :key="performance.id" class="stage-form-score">{{ performance.form_name }}: {{ formatScore(performance.result ?? performance.preview_result) }}<small v-if="performance.result == null && performance.preview_result != null">موقت</small></span><span v-if="!bout.performances.length">استراحت</span></p></td><td><p v-for="entry in bout.entries" :key="entry.id">{{ formatScore(entryTotal(bout, category, entry.id).score) }}<small v-if="entryTotal(bout, category, entry.id).temporary">موقت</small><b v-if="bout.winner_entry_id === entry.id" class="display-winner"> · برنده</b></p></td></tr></tbody></table></div>
            </article></div>
            <section v-if="category.standings.length" class="display-stage"><h3>رتبه‌بندی {{ category.rounds.find(round => round.id === category.standings_round_id)?.name }} · {{ category.completed ? 'پایان رده' : 'موقت' }}</h3><div class="table-wrap"><table><thead><tr><th>رتبه</th><th>بازیکن / تیم</th><th>امتیاز قطعی</th><th>معیار رفع تساوی</th></tr></thead><tbody><tr v-for="entry in category.standings" :key="entry.id"><td>{{ entry.rank?.toLocaleString('fa-IR') ?? '—' }}</td><td>{{ entry.name }}</td><td>{{ formatScore(entry.score) }}</td><td>{{ formatScore(entry.tie_break_score) }}</td></tr></tbody></table></div></section>
        </section>
        <footer>نتیجهٔ موقت، برنده یا رتبهٔ قطعی را تغییر نمی‌دهد.<span>دریافت تغییرات با اعلان زنده و بررسی دوره‌ای</span></footer>
    </div>
</template>
