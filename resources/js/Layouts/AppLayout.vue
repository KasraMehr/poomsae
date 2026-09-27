<script setup>
import { Link, usePage } from '@inertiajs/vue3';
const page = usePage();
</script>

<template>
    <div class="flex min-h-screen max-[760px]:block">
        <aside class="fixed inset-y-0 right-0 flex w-[245px] flex-col border-l border-[#e5ebe5] bg-white px-6 py-[34px] max-[1100px]:w-[210px] max-[1100px]:px-[18px] max-[1100px]:py-[28px] max-[760px]:static max-[760px]:w-full max-[760px]:border-x-0 max-[760px]:border-b max-[760px]:border-[#e2e9df] max-[760px]:px-5 max-[760px]:py-[18px]">
            <Link :href="page.props.urls.dashboard" class="flex items-center gap-3 text-2xl font-bold max-[760px]:text-xl"><span class="grid h-12 w-11 place-items-center rounded-[14px_4px_14px_4px] bg-[#215e51] text-[29px] text-white max-[760px]:h-[38px] max-[760px]:w-[35px]">پ</span><div>پومسه<small class="mt-px block text-[10px] font-normal text-[#8b9690]">مدیریت و داوری مسابقات</small></div></Link>
            <p class="mb-3 mt-[52px] text-[11px] text-[#9ca7a0] max-[760px]:hidden">میز کار</p>
            <nav class="grid gap-2 max-[760px]:mt-[15px] max-[760px]:flex max-[760px]:gap-[10px]">
                <Link :href="page.props.urls.dashboard" class="flex items-center gap-[14px] rounded-lg px-[15px] py-3 max-[760px]:px-[14px] max-[760px]:py-2" :class="page.component === 'Dashboard' ? 'bg-[#eaf3ee] font-bold text-[#286554]' : 'text-[#718078]'"><span class="text-[19px]">◈</span> نمای کلی</Link>
                <Link :href="page.props.urls.tournaments" class="flex items-center gap-[14px] rounded-lg px-[15px] py-3 max-[760px]:px-[14px] max-[760px]:py-2" :class="page.component.startsWith('Tournaments/') ? 'bg-[#eaf3ee] font-bold text-[#286554]' : 'text-[#718078]'"><span class="text-[19px]">▤</span> مسابقات</Link>
                <Link :href="page.props.urls.logout" method="post" as="button" class="hidden border-0 bg-transparent px-[14px] py-2 text-[#72806a] max-[760px]:mr-auto max-[760px]:block">خروج</Link>
            </nav>
            <div class="mt-auto px-[10px] py-[18px] text-xs text-[#527869] max-[760px]:hidden"><span class="ml-[7px] inline-block h-[7px] w-[7px] rounded-full bg-[#729c7e]"></span> استاندارد انفرادی<small class="mt-2 block leading-[1.9] text-[#9aa49e]">ثبت‌نام، اجرا و داوری<br>نسخهٔ پایلوت برگزاری</small></div>
            <div class="flex items-center gap-[9px] border-t border-[#ebefeb] pt-[23px] text-[11px] max-[760px]:hidden"><span class="grid h-9 w-8 place-items-center rounded-[9px] bg-[#f0eade]">{{ page.props.auth.user.name.charAt(0) }}</span><div>{{ page.props.auth.user.name }}<small class="block text-[10px] text-[#8c9892]">{{ page.props.auth.user.is_admin ? 'مدیر سامانه' : 'عضو مسابقه' }}</small></div><Link :href="page.props.urls.logout" method="post" as="button" class="mr-auto border-0 bg-transparent text-[#79877f]">خروج</Link></div>
        </aside>
        <div class="flex min-h-screen w-[calc(100%_-_245px)] flex-col mr-[245px] max-[1100px]:mr-[210px] max-[1100px]:w-[calc(100%_-_210px)] max-[760px]:mr-0 max-[760px]:w-full">
            <header class="flex justify-between border-b border-[#e6ebe5] bg-[#fafbf9] px-[42px] py-[23px] text-xs text-[#718078] max-[1100px]:px-[25px] max-[1100px]:py-[22px] max-[760px]:hidden"><span>سامانهٔ مدیریت مسابقات پومسه</span><span class="text-[10px] tracking-[1.8px]">POOMSAE / CONTROL CENTER</span></header>
            <main class="mx-auto w-full max-w-[1480px] flex-1 px-[42px] py-[38px] min-[1550px]:pt-[55px] max-[1100px]:px-[25px] max-[1100px]:py-[30px] max-[760px]:px-[18px] max-[760px]:py-[25px]"><div v-if="page.props.flash.success" class="mb-5 rounded-lg bg-[#e1f1e7] px-[18px] py-3 text-[#386748]" role="status">{{ page.props.flash.success }}</div><slot /></main>
            <footer class="flex justify-between px-[42px] py-5 text-[10px] text-[#9aa496] max-[760px]:px-[18px]">پومسه · زیرساخت مدیریت مسابقات <span>نسخهٔ پایلوت</span></footer>
        </div>
    </div>
</template>
