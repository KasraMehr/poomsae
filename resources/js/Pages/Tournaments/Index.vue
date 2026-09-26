<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import TournamentCard from '../../Components/TournamentCard.vue';
defineProps({ tournaments: Object });
const page = usePage();
const form = useForm({ name: '', venue: '', starts_on: '', ends_on: '', timezone: 'Asia/Tehran' });
const submit = () => form.post(page.props.urls.tournaments);
</script>
<template>
    <AppLayout><Head title="مسابقات" />
        <section class="page-heading"><div><span class="eyebrow">مدیریت رویدادها</span><h1>مسابقات</h1><p class="subtle">هر رویداد، رده‌ها و اعضای تیم برگزاری مستقل خود را دارد.</p></div></section>
        <section v-if="page.props.auth.user.is_admin" class="panel"><h2>تعریف مسابقه</h2><form @submit.prevent="submit" class="tournament-form"><div class="wide"><label for="name">نام مسابقه</label><input id="name" v-model="form.name" required maxlength="255" placeholder="مثلاً مسابقات قهرمانی استان"></div><div><label for="venue">محل برگزاری</label><input id="venue" v-model="form.venue" maxlength="255" placeholder="نام سالن"></div><div><label for="starts">تاریخ شروع (میلادی)</label><input id="starts" v-model="form.starts_on" type="date" required dir="ltr"></div><div><label for="ends">تاریخ پایان (اختیاری)</label><input id="ends" v-model="form.ends_on" type="date" :min="form.starts_on" dir="ltr"></div><div class="form-action"><button class="button" :disabled="form.processing">{{ form.processing ? 'در حال ثبت…' : 'ساخت مسابقه' }}</button><span class="subtle">منطقهٔ زمانی: تهران</span></div><div v-if="Object.keys(form.errors).length" class="wide error" role="alert"><p v-for="(error, key) in form.errors" :key="key">{{ error }}</p></div></form></section>
        <div class="section-heading"><h2>فهرست مسابقات</h2><span class="subtle">{{ tournaments.total.toLocaleString('fa-IR') }} رویداد</span></div>
        <div class="card-grid" v-if="tournaments.data.length"><TournamentCard v-for="tournament in tournaments.data" :key="tournament.id" :tournament="tournament" /></div><div v-else class="empty"><h2>مسابقه‌ای برای نمایش وجود ندارد.</h2></div>
        <nav class="pagination" aria-label="صفحه‌بندی"><Link v-if="tournaments.prev_page_url" :href="tournaments.prev_page_url">صفحهٔ قبل</Link><span>{{ tournaments.current_page }} / {{ tournaments.last_page }}</span><Link v-if="tournaments.next_page_url" :href="tournaments.next_page_url">صفحهٔ بعد</Link></nav>
    </AppLayout>
</template>

