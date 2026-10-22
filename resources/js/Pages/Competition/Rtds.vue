<script setup>
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { useDisplayUpdates } from '../../useDisplayUpdates';
import { connectionLabels, statusLabels } from '../../display';
import PerformanceTiming from '../../Components/competition/PerformanceTiming.vue';
const props = defineProps({ display: Object, displayUrls: Object });
const { display, now, connection } = useDisplayUpdates(props.display, props.displayUrls.data);
const courtId = ref('');
const courts = computed(() => display.value.courts.filter(court => !courtId.value || court.id === Number(courtId.value)));
</script>

<template>
    <div class="scoreboard hall-display rtds-display">
        <Head :title="`RTDS · ${display.tournament.name}`" />
        <header class="scoreboard-header"><div><span class="eyebrow">RTDS · برنامهٔ اجراهای سالن</span><h1>{{ display.tournament.name }}</h1><p>{{ statusLabels[display.tournament.status] }}</p></div><div class="display-controls"><label>زمین<select v-model="courtId"><option value="">همهٔ زمین‌ها</option><option v-for="court in display.courts" :key="court.id" :value="court.id">{{ court.name }}</option></select></label><Link :href="displayUrls.scoreboard">نمایشگر امتیازات</Link><Link :href="displayUrls.back">بازگشت</Link></div></header>
        <p class="display-connection" :class="connection" role="status">{{ connectionLabels[connection] }}<span v-if="connection === 'denied'"> · <Link :href="displayUrls.back">ورود مجدد</Link></span></p>
        <div v-if="!courts.length" class="scoreboard-wait">هنوز زمینی تعریف نشده است.</div>
        <div class="rtds-courts"><section v-for="court in courts" :key="court.id" class="rtds-court"><header><h2>{{ court.name }}</h2><span class="display-badge" :class="court.current?.status || 'pending'">{{ court.current ? statusLabels[court.current.status] : 'آمادهٔ فراخوان' }}</span></header>
            <article v-if="court.current" class="rtds-current"><span class="eyebrow">اجرای جاری · {{ court.current.category_name }}</span><h3 v-for="entry in court.current.entries" :key="entry.id" :class="court.current.format === 'knockout' ? entry.side : ''">{{ entry.name }}</h3><p>{{ court.current.round_name }} · رقابت {{ court.current.bout_sequence }} · {{ statusLabels[court.current.round_status] }}</p><strong>{{ court.current.form_name }}</strong><div v-for="performance in court.current.performances" :key="performance.id"><p>{{ statusLabels[performance.status] }}<span v-if="performance.status === 'scoring'"> · {{ performance.submitted_count }} نمره دریافت شد</span></p><PerformanceTiming :performance="performance"/></div></article>
            <article v-else class="rtds-current"><span class="eyebrow">اجرای جاری</span><h3>زمین آماده است</h3><p>اجرای بعدی از فهرست زیر فراخوان می‌شود.</p></article>
            <section class="rtds-queue"><h3>اجراهای بعدی</h3><ol v-if="court.upcoming.length"><li v-for="item in court.upcoming" :key="item.id"><div class="rtds-queue-heading"><b>{{ item.entries.map(entry => entry.name).join(' / ') }}</b><span>{{ item.ready ? 'در صف اجرا' : 'در انتظار آماده‌شدن مرحله یا پایان اجرای جاری' }}</span></div><p>{{ item.category_name }} · {{ item.round_name }} · رقابت {{ item.bout_sequence }}</p><p>{{ item.form_name }} · {{ statusLabels[item.status] }}</p><PerformanceTiming v-for="performance in item.performances" :key="performance.id" :performance="performance"/></li></ol><p v-else>اجرای دیگری در جدول این زمین ثبت نشده است.</p></section>
        </section></div>
        <footer>صف اجرا مطابق جدول و ترتیب فرم‌های هر مرحله است.<span>نمایشگر فقط برای مشاهده است.</span></footer>
    </div>
</template>
