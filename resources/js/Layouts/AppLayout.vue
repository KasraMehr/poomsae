<script setup>
import { Link, usePage } from '@inertiajs/vue3';
const page = usePage();
</script>

<template>
    <div class="shell">
        <aside class="sidebar">
            <Link :href="page.props.urls.dashboard" class="brand"><span class="brand-icon">پ</span><div>پومسه<small>مدیریت و داوری مسابقات</small></div></Link>
            <p class="nav-label">میز کار</p>
            <nav>
                <Link :href="page.props.urls.dashboard" :class="{ active: page.component === 'Dashboard' }"><span>◈</span> نمای کلی</Link>
                <Link :href="page.props.urls.tournaments" :class="{ active: page.component.startsWith('Tournaments/') }"><span>▤</span> مسابقات</Link>
                <Link :href="page.props.urls.logout" method="post" as="button" class="mobile-logout">خروج</Link>
            </nav>
            <div class="sidebar-note"><span class="dot"></span> استاندارد انفرادی<small>ثبت‌نام، اجرا و داوری<br>نسخهٔ پایلوت برگزاری</small></div>
            <div class="profile"><span class="avatar">{{ page.props.auth.user.name.charAt(0) }}</span><div>{{ page.props.auth.user.name }}<small>{{ page.props.auth.user.is_admin ? 'مدیر سامانه' : 'عضو مسابقه' }}</small></div><Link :href="page.props.urls.logout" method="post" as="button" class="logout">خروج</Link></div>
        </aside>
        <div class="workspace">
            <header class="topbar"><span>سامانهٔ مدیریت مسابقات پومسه</span><span class="subtle">POOMSAE / CONTROL CENTER</span></header>
            <main><div v-if="page.props.flash.success" class="notice" role="status">{{ page.props.flash.success }}</div><slot /></main>
            <footer>پومسه · زیرساخت مدیریت مسابقات <span>نسخهٔ پایلوت</span></footer>
        </div>
    </div>
</template>

