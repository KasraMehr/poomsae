<script setup>
import { Link, usePage } from "@inertiajs/vue3";
const page = usePage();
</script>

<template>
    <div class="flex min-h-screen flex-col">
        <header class="border-b border-[#e5ebe5] bg-white">
            <div
                class="mx-auto grid min-h-[104px] w-full max-w-[1560px] grid-cols-[1fr_auto_1fr] items-center gap-8 px-10 max-[1100px]:gap-5 max-[1100px]:px-6 max-[760px]:grid-cols-[1fr_auto] max-[760px]:gap-x-4 max-[760px]:gap-y-3 max-[760px]:px-[18px] max-[760px]:py-4"
            >
                <Link
                    :href="page.props.urls.dashboard"
                    class="flex shrink-0 items-center gap-3 justify-self-start"
                >
                    <span
                        class="grid h-11 w-11 place-items-center rounded-[14px_4px_14px_4px] bg-[#215e51] text-[27px] font-bold text-white shadow-sm"
                        >پ</span
                    >
                    <span>
                        <strong class="block text-[17px] leading-tight text-[#213d35]">پومسه</strong>
                        <small class="mt-1 block text-[10px] leading-tight text-[#89978f]">پنل مدیریت مسابقات</small>
                    </span>
                </Link>

                <nav
                    aria-label="منوی اصلی"
                    class="flex items-center gap-1 justify-self-center rounded-xl bg-[#f5f7f4] p-1 max-[760px]:order-3 max-[760px]:col-span-2 max-[760px]:w-full"
                >
                    <Link
                        :href="page.props.urls.dashboard"
                        :aria-current="page.component === 'Dashboard' ? 'page' : undefined"
                        class="flex min-h-11 items-center gap-2 rounded-lg px-4 text-[13px] transition-colors max-[760px]:flex-1 max-[760px]:justify-center max-[480px]:gap-1.5 max-[480px]:px-2 max-[480px]:text-xs"
                        :class="
                            page.component === 'Dashboard'
                                ? 'bg-white font-bold text-[#205a48] shadow-sm'
                                : 'text-[#718078] hover:bg-white/70 hover:text-[#315d4c]'
                        "
                    >
                        <svg aria-hidden="true" viewBox="0 0 20 20" class="h-[18px] w-[18px]" fill="none" stroke="currentColor" stroke-width="1.6">
                            <path d="M3 10.2 10 4l7 6.2v6.3a.5.5 0 0 1-.5.5h-13a.5.5 0 0 1-.5-.5z" stroke-linejoin="round" />
                            <path d="M7.5 17v-5h5v5" stroke-linejoin="round" />
                        </svg>
                        نمای کلی
                    </Link>
                    <Link
                        :href="page.props.urls.tournaments"
                        :aria-current="page.component.startsWith('Tournaments/') ? 'page' : undefined"
                        class="flex min-h-11 items-center gap-2 rounded-lg px-4 text-[13px] transition-colors max-[760px]:flex-1 max-[760px]:justify-center max-[480px]:gap-1.5 max-[480px]:px-2 max-[480px]:text-xs"
                        :class="
                            page.component.startsWith('Tournaments/')
                                ? 'bg-white font-bold text-[#205a48] shadow-sm'
                                : 'text-[#718078] hover:bg-white/70 hover:text-[#315d4c]'
                        "
                    >
                        <svg aria-hidden="true" viewBox="0 0 20 20" class="h-[18px] w-[18px]" fill="none" stroke="currentColor" stroke-width="1.6">
                            <rect x="3" y="4.5" width="14" height="13" rx="2" />
                            <path d="M6.5 3v3M13.5 3v3M3 8.5h14M6.5 11h2M11.5 11h2M6.5 14h2" stroke-linecap="round" />
                        </svg>
                        مسابقات
                    </Link>
                </nav>

                <div class="flex shrink-0 items-center gap-3 justify-self-end rounded-2xl border border-[#e8ece6] bg-[#fafbf9] px-3 py-2 max-[760px]:gap-2 max-[760px]:border-0 max-[760px]:bg-transparent max-[760px]:px-0 max-[760px]:py-0">
                    <span
                        class="grid h-11 w-11 place-items-center rounded-xl bg-[#e9efe9] text-sm font-bold text-[#315d4c] max-[480px]:h-9 max-[480px]:w-9"
                        aria-hidden="true"
                        >{{ page.props.auth.user.name.charAt(0) }}</span
                    >
                    <div class="min-w-[100px] max-[480px]:hidden">
                        <strong class="block max-w-[180px] truncate text-[13px] font-bold leading-5 text-[#344a40]">{{ page.props.auth.user.name }}</strong>
                        <small class="mt-0.5 flex items-center gap-1.5 text-[10px] leading-4 text-[#839087]">
                            <span class="h-1.5 w-1.5 rounded-full bg-[#5b9a70]"></span>
                            {{
                            page.props.auth.user.is_admin
                                ? "مدیر سامانه"
                                : "عضو مسابقه"
                            }}</small>
                    </div>
                    <Link
                        :href="page.props.urls.logout"
                        method="post"
                        as="button"
                        class="mr-1 inline-flex min-h-10 items-center gap-1.5 rounded-lg border border-[#f0d7d5] bg-white px-3 text-[11px] font-semibold text-[#b84b47] transition-colors hover:border-[#e8b8b5] hover:bg-[#fff5f4] hover:text-[#9f3431] max-[760px]:mr-0 max-[480px]:px-2.5"
                    >
                        <svg aria-hidden="true" viewBox="0 0 20 20" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7">
                            <path d="M8 4H4.8a.8.8 0 0 0-.8.8v10.4a.8.8 0 0 0 .8.8H8M12 6l4 4-4 4m4-4H7" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        خروج
                    </Link>
                    
                </div>
            </div>
        </header>

        <div class="flex flex-1 flex-col">
            <main
                class="mx-auto w-full max-w-[1480px] flex-1 px-10 py-9 min-[1550px]:pt-[52px] max-[1100px]:px-6 max-[1100px]:py-[30px] max-[760px]:px-[18px] max-[760px]:py-6"
            >
                <div
                    v-if="page.props.flash.success"
                    class="mb-5 rounded-lg bg-[#e1f1e7] px-[18px] py-3 text-[#386748]"
                    role="status"
                >
                    {{ page.props.flash.success }}
                </div>
                <slot />
            </main>
            <footer
                class="mx-auto flex w-full max-w-[1480px] justify-between border-t border-[#e9ede8] px-10 py-4 text-[10px] text-[#9aa496] max-[1100px]:px-6 max-[760px]:px-[18px]"
            >
                پومسه · زیرساخت مدیریت مسابقات <span>نسخهٔ پایلوت</span>
            </footer>
        </div>
    </div>
</template>
