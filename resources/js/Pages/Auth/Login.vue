<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import Button from '../../Components/common/Button.vue';
const page = usePage();
const form = useForm({ email: '', password: '' });
const submit = () => form.post(page.props.urls.login, { onFinish: () => form.reset('password') });
</script>
<template>
    <div class="grid min-h-screen grid-cols-1 min-[761px]:grid-cols-2">
        <Head title="ورود" />
        <section class="flex min-h-screen flex-col justify-between bg-[#244c40] px-17.5 py-15 text-[#f6f7eb] min-[761px]:max-[1100px]:px-8.75 min-[761px]:max-[1100px]:py-12.5 max-[760px]:min-h-0 max-[760px]:px-6.25 max-[760px]:py-6.25">
            <div class="flex items-center gap-3 text-2xl font-bold max-[760px]:text-xl"><span class="grid h-12 w-11 place-items-center rounded-[14px_4px_14px_4px] bg-[#dae4bd] text-[29px] text-[#294e3c] max-[760px]:h-9.5 max-[760px]:w-8.75">پ</span><div>پومسه<small class="mt-px block text-[10px] font-normal text-[#a8bbae]">سامانهٔ مدیریت و داوری مسابقات</small></div></div>
            <div class="max-[760px]:hidden"><span class="mb-2 block text-xs text-[#c4d1a8]">از نخستین اجرا تا نتیجهٔ نهایی</span><h1 class="my-5 text-[34px] font-medium leading-[1.85] min-[1101px]:text-[45px]">تمرکز بر مسابقه.<br>دقت در هر امتیاز.</h1><p class="text-[#a5bbae] leading-[2.3]">یک میز کار مشترک برای تیم برگزاری،<br>داوران و مدیریت مسابقات پومسه.</p></div>
            <span class="text-[10px] tracking-[3px] text-[#a2b4a7] max-[760px]:hidden">POOMSAE COMPETITION SYSTEM</span>
        </section>
        <section class="grid place-items-center px-[45px] py-[45px] max-[760px]:px-[25px] max-[760px]:py-10"><div class="w-full max-w-[365px] max-[760px]:max-w-[420px]"><span class="mb-2 block text-xs text-[#578678]">خوش آمدید</span><h2 class="mb-3 text-[28px] font-bold">ورود به میز کار</h2><p class="text-[13px] text-[#7c8984]">با حسابی که مدیر سامانه ساخته است وارد شوید.</p><form class="mt-[33px]" @submit.prevent="submit"><label class="mb-[7px] mt-5 block text-xs text-[#63756b]" for="email">ایمیل</label><input id="email" v-model="form.email" class="min-h-11 w-full rounded-[7px] border border-[#dce5da] bg-[#fcfdfa] px-3 py-[10px] text-[#253c31] focus:outline-2 focus:outline-[#79a28b]" type="email" autocomplete="username" dir="ltr" required autofocus placeholder="name@example.com"><p v-if="form.errors.email" class="mt-1.5 text-xs text-[#a33939]" role="alert">{{ form.errors.email }}</p><label class="mb-[7px] mt-5 block text-xs text-[#63756b]" for="password">رمز عبور</label><input id="password" v-model="form.password" class="min-h-11 w-full rounded-[7px] border border-[#dce5da] bg-[#fcfdfa] px-3 py-[10px] text-[#253c31] focus:outline-2 focus:outline-[#79a28b]" type="password" autocomplete="current-password" dir="ltr" required><p v-if="form.errors.password" class="mt-1.5 text-xs text-[#a33939]">{{ form.errors.password }}</p><Button type="submit" full :disabled="form.processing" class="mt-7">{{ form.processing ? 'در حال ورود…' : 'ورود به سامانه ←' }}</Button></form><div class="mt-8 border-t border-[#e0e7dc] pt-5 text-[11px] leading-[2.2] text-[#8e9a90]">برای ساخت اولین مدیر، فرمان <code class="inline-block text-[10px] text-[#667b6c]" dir="ltr">php artisan app:create-admin</code> را با ایمیل در ترمینال اجرا کنید.</div></div></section>
    </div>
</template>
