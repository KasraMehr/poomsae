<script setup>
import { Head, Link, usePage } from "@inertiajs/vue3";
import AppLayout from "../Layouts/AppLayout.vue";
import TournamentCard from "../Components/tournaments/TournamentCard.vue";
defineProps({ stats: Object, tournaments: Array });
const page = usePage();
</script>
<template>
    <AppLayout
        ><Head title="نمای کلی" />
        <section
            class="mb-[30px] flex items-center justify-between gap-5 max-[760px]:flex-col max-[760px]:items-start max-[760px]:gap-[15px]"
        >
            <div>
                <span class="mb-2 block text-xs text-[#578678]"
                    >میز کار برگزاری</span
                >
                <h1
                    class="text-[29px] font-bold leading-[1.6] max-[760px]:text-[25px]"
                >
                    نمای کلی مسابقات
                </h1>
                <p class="mt-2 text-[13px] text-[#7c8984]">
                    وضعیت مسابقات و نقطهٔ شروع آماده‌سازی رویداد بعدی.
                </p>
            </div>
            <Link
                :href="page.props.urls.tournaments"
                class="inline-flex min-h-11 items-center justify-center rounded-lg bg-[#286957] px-[22px] py-[11px] text-[13px] text-white hover:bg-[#1b5142]"
                >{{
                    page.props.auth.user.is_admin
                        ? "+ تعریف مسابقه"
                        : "مشاهده مسابقات"
                }}</Link
            >
        </section>
        <section class="mb-9 grid grid-cols-3 gap-5 max-[760px]:gap-[9px]">
            <article
                class="rounded-xl border border-[#e3e9e1] bg-white p-6 max-[760px]:px-3 max-[760px]:py-4"
            >
                <span class="text-xs text-[#6d7d74] max-[760px]:text-[10px]"
                    >کل مسابقات شما</span
                ><strong
                    class="my-[14px] block text-[35px] font-medium leading-[1.5] max-[760px]:text-[29px]"
                    >{{ stats.total.toLocaleString("fa-IR") }}</strong
                ><small
                    class="text-[10px] text-[#9ba59e] max-[760px]:text-[9px]"
                    >رویدادهای قابل دسترسی</small
                >
            </article>
            <article
                class="rounded-xl border border-[#e3e9e1] bg-white p-6 max-[760px]:px-3 max-[760px]:py-4"
            >
                <span class="text-xs text-[#6d7d74] max-[760px]:text-[10px]"
                    >در حال برگزاری</span
                ><strong
                    class="my-[14px] block text-[35px] font-medium leading-[1.5] max-[760px]:text-[29px]"
                    >{{ stats.running.toLocaleString("fa-IR") }}</strong
                ><small
                    class="text-[10px] text-[#9ba59e] max-[760px]:text-[9px]"
                    >مسابقات با وضعیت فعال</small
                >
            </article>
            <article
                class="rounded-xl border border-[#e3e9e1] bg-white p-6 max-[760px]:px-3 max-[760px]:py-4"
            >
                <span class="text-xs text-[#6d7d74] max-[760px]:text-[10px]"
                    >در حال آماده‌سازی</span
                ><strong
                    class="my-[14px] block text-[35px] font-medium leading-[1.5] max-[760px]:text-[29px]"
                    >{{ stats.draft.toLocaleString("fa-IR") }}</strong
                ><small
                    class="text-[10px] text-[#9ba59e] max-[760px]:text-[9px]"
                    >پیش‌نویس‌های ثبت‌شده</small
                >
            </article>
        </section>
        <div class="mb-[18px] mt-[26px] flex items-center justify-between">
            <h2 class="text-[17px] font-bold">آخرین مسابقات</h2>
            <Link
                :href="page.props.urls.tournaments"
                class="text-xs text-[#618575]"
                >مشاهده همه ←</Link
            >
        </div>
        <div
            v-if="tournaments.length"
            class="grid grid-cols-1 gap-[19px] min-[761px]:grid-cols-2 min-[1101px]:grid-cols-3"
        >
            <TournamentCard
                v-for="tournament in tournaments"
                :key="tournament.id"
                :tournament="tournament"
            />
        </div>
        <section
            v-else
            class="rounded-xl border border-dashed border-[#ccd9ca] bg-[#fafcf8] px-5 py-[50px] text-center"
        >
            <span class="mb-2 block text-[35px] text-[#a0b297]">▤</span>
            <h2 class="text-lg font-bold">هنوز مسابقه‌ای ثبت نشده</h2>
            <p class="my-3 text-xs text-[#8b988a]">
                اولین رویداد را تعریف کنید تا ساختار برگزاری آن آماده شود.
            </p>
            <Link
                v-if="page.props.auth.user.is_admin"
                :href="page.props.urls.tournaments"
                class="inline-flex min-h-11 items-center justify-center rounded-lg bg-[#286957] px-[22px] py-[11px] text-[13px] text-white hover:bg-[#1b5142]"
                >تعریف اولین مسابقه</Link
            >
        </section>
        <section
            class="mt-[35px] grid grid-cols-1 gap-[25px] rounded-xl bg-[#e9f0e8] px-[30px] py-[29px] min-[761px]:grid-cols-2 min-[1101px]:gap-[45px] max-[760px]:px-[22px]"
        >
            <div>
                <span class="mb-2 block text-xs text-[#578678]"
                    >مسیر آماده‌سازی</span
                >
                <h2 class="text-lg font-bold">پایهٔ یک مسابقهٔ دقیق</h2>
                <p class="mt-3 max-w-[370px] text-xs text-[#7b8b7f]">
                    تعریف رویداد، رده‌بندی، ثبت ورزشکار و تخصیص داور؛ هر مرحله
                    پیش‌نیاز اجرای بعدی است.
                </p>
            </div>
            <ol class="m-0 grid list-none gap-[14px] p-0 text-xs">
                <li class="flex items-center gap-3">
                    <b
                        class="grid h-7 w-7 place-items-center rounded-full bg-[#f7faf3] text-[#739078]"
                        >۱</b
                    >
                    تعریف مسابقه
                    <span class="mr-auto text-[10px] text-[#8e9e8f]">فعال</span>
                </li>
                <li class="flex items-center gap-3">
                    <b
                        class="grid h-7 w-7 place-items-center rounded-full bg-[#f7faf3] text-[#739078]"
                        >۲</b
                    >
                    رده‌ها و شرکت‌کنندگان
                    <span class="mr-auto text-[10px] text-[#8e9e8f]">فعال</span>
                </li>
                <li class="flex items-center gap-3">
                    <b
                        class="grid h-7 w-7 place-items-center rounded-full bg-[#f7faf3] text-[#739078]"
                        >۳</b
                    >
                    داوری و نمایش نتایج
                    <span class="mr-auto text-[10px] text-[#8e9e8f]">فعال</span>
                </li>
            </ol>
        </section>
    </AppLayout>
</template>
