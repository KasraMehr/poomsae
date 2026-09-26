<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
const page = usePage();
const form = useForm({ email: '', password: '' });
const submit = () => form.post(page.props.urls.login, { onFinish: () => form.reset('password') });
</script>
<template>
    <div class="login-page">
        <Head title="ورود" />
        <section class="login-story"><div class="brand light"><span class="brand-icon">پ</span><div>پومسه<small>سامانهٔ مدیریت و داوری مسابقات</small></div></div><div><span class="eyebrow">از نخستین اجرا تا نتیجهٔ نهایی</span><h1>تمرکز بر مسابقه.<br>دقت در هر امتیاز.</h1><p>یک میز کار مشترک برای تیم برگزاری،<br>داوران و مدیریت مسابقات پومسه.</p></div><span class="story-footer">POOMSAE COMPETITION SYSTEM</span></section>
        <section class="login-form"><div class="login-box"><span class="eyebrow">خوش آمدید</span><h2>ورود به میز کار</h2><p class="subtle">با حسابی که مدیر سامانه ساخته است وارد شوید.</p><form @submit.prevent="submit"><label for="email">ایمیل</label><input id="email" v-model="form.email" type="email" autocomplete="username" dir="ltr" required autofocus placeholder="name@example.com"><p v-if="form.errors.email" class="error" role="alert">{{ form.errors.email }}</p><label for="password">رمز عبور</label><input id="password" v-model="form.password" type="password" autocomplete="current-password" dir="ltr" required><p v-if="form.errors.password" class="error">{{ form.errors.password }}</p><button class="button full" :disabled="form.processing">{{ form.processing ? 'در حال ورود…' : 'ورود به سامانه ←' }}</button></form><div class="login-help">برای ساخت اولین مدیر، فرمان <code dir="ltr">php artisan app:create-admin</code> را با ایمیل در ترمینال اجرا کنید.</div></div></section>
    </div>
</template>

