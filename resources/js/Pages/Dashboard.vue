<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '../Layouts/AppLayout.vue';
import TournamentCard from '../Components/TournamentCard.vue';
defineProps({ stats: Object, tournaments: Array });
const page = usePage();
</script>
<template>
    <AppLayout><Head title="نمای کلی" />
        <section class="page-heading"><div><span class="eyebrow">میز کار برگزاری</span><h1>نمای کلی مسابقات</h1><p class="subtle">وضعیت مسابقات و نقطهٔ شروع آماده‌سازی رویداد بعدی.</p></div><Link :href="page.props.urls.tournaments" class="button">{{ page.props.auth.user.is_admin ? '+ تعریف مسابقه' : 'مشاهده مسابقات' }}</Link></section>
        <section class="stats"><article><span>کل مسابقات شما</span><strong>{{ stats.total.toLocaleString('fa-IR') }}</strong><small>رویدادهای قابل دسترسی</small></article><article><span>در حال برگزاری</span><strong>{{ stats.running.toLocaleString('fa-IR') }}</strong><small>مسابقات با وضعیت فعال</small></article><article><span>در حال آماده‌سازی</span><strong>{{ stats.draft.toLocaleString('fa-IR') }}</strong><small>پیش‌نویس‌های ثبت‌شده</small></article></section>
        <div class="section-heading"><h2>آخرین مسابقات</h2><Link :href="page.props.urls.tournaments">مشاهده همه ←</Link></div>
        <div v-if="tournaments.length" class="card-grid"><TournamentCard v-for="tournament in tournaments" :key="tournament.id" :tournament="tournament" /></div>
        <section v-else class="empty"><span class="empty-icon">▤</span><h2>هنوز مسابقه‌ای ثبت نشده</h2><p>اولین رویداد را تعریف کنید تا ساختار برگزاری آن آماده شود.</p><Link v-if="page.props.auth.user.is_admin" :href="page.props.urls.tournaments" class="button">تعریف اولین مسابقه</Link></section>
        <section class="foundation-note"><div><span class="eyebrow">مسیر آماده‌سازی</span><h2>پایهٔ یک مسابقهٔ دقیق</h2><p>تعریف رویداد، رده‌بندی، ثبت ورزشکار و تخصیص داور؛ هر مرحله پیش‌نیاز اجرای بعدی است.</p></div><ol><li><b>۱</b> تعریف مسابقه <span>فعال</span></li><li><b>۲</b> رده‌ها و شرکت‌کنندگان <span>فعال</span></li><li><b>۳</b> داوری و نمایش نتایج <span>فعال</span></li></ol></section>
    </AppLayout>
</template>

