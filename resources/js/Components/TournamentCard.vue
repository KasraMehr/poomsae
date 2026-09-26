<script setup>
import { Link, usePage } from '@inertiajs/vue3';
defineProps({ tournament: Object });
const page = usePage();
const labels = { draft: 'پیش‌نویس', ready: 'آماده', running: 'در حال برگزاری', completed: 'پایان‌یافته', archived: 'آرشیو' };
const date = (value) => new Intl.DateTimeFormat('fa-IR', { dateStyle: 'medium', timeZone: 'UTC' }).format(new Date(value));
</script>
<template>
    <Link :href="page.props.urls.tournaments + '/' + tournament.id" class="tournament-card">
        <div class="card-top"><span class="mini-icon">▤</span><span class="badge" :class="tournament.status">{{ labels[tournament.status] }}</span></div>
        <h3>{{ tournament.name }}</h3>
        <p>{{ tournament.venue || 'محل برگزاری تعیین نشده' }}</p>
        <div class="card-bottom"><span>{{ date(tournament.starts_on) }}</span><span>{{ tournament.categories_count ?? 0 }} رده · {{ tournament.courts_count ?? 0 }} زمین</span></div>
    </Link>
</template>

