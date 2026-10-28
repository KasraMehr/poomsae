<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import AppLayout from '../../Shared/Layouts/AppLayout.vue';
import PersianDateField from '../../Components/tournaments/PersianDateField.vue';
import TournamentCard from '../../Components/tournaments/TournamentCard.vue';
import { currentPersianDateParts, persianDateToGregorianIso } from '../../Shared/Services/persianDate.js';

const props = defineProps({ tournaments: Object, statusFilter: String, listUrls: Object });

const page = usePage();
const today = currentPersianDateParts();
const todayIso = persianDateToGregorianIso(today.year, today.month, today.day);
const form = useForm({ name: '', venue: '', starts_on: todayIso, ends_on: '', timezone: 'Asia/Tehran' });
const isCreateFormOpen = ref(props.tournaments.total === 0);
const submit = () => form.post(page.props.urls.tournaments);

watch(() => props.tournaments.total, (total) => {
    isCreateFormOpen.value = total === 0;
});
</script>

<template>
    <AppLayout>
        <Head title="مسابقات" />

        <div class="grid gap-7">
            <section class="flex flex-wrap items-end justify-between gap-5">
                <div>
                    <h1 class="text-[29px] font-bold leading-[1.5] text-[#203c34] max-[760px]:text-[25px]">مدیریت مسابقات</h1>
                    <p class="mt-2 text-[13px] text-[#7c8984]">
                        هر مسابقه، برنامه و تیم اجرایی خودش را دارد.
                    </p>
                </div>
                <div class="flex items-center gap-3 rounded-xl border border-[#e3eae2] bg-white px-4 py-3 text-xs">
                    <svg aria-hidden="true" viewBox="0 0 20 20" class="h-5 w-5 text-[#62816e]" fill="none" stroke="currentColor" stroke-width="1.5">
                        <circle cx="10" cy="10" r="7" />
                        <path d="M10 6v4l2.5 1.5" stroke-linecap="round" />
                    </svg>
                    <span>
                        <small class="block text-[10px] text-[#849087]">منطقهٔ زمانی</small>
                        <strong class="mt-0.5 block font-semibold text-[#40594b]">تهران · Asia/Tehran</strong>
                    </span>
                </div>
            </section>

            <section v-if="page.props.auth.user.is_admin">
                <div class="rounded-2xl border border-[#e2e9df] bg-white shadow-[0_5px_18px_#26452c08] transition-colors hover:border-[#6f9c7c]">
                    <button
                        type="button"
                        class="flex w-full items-center justify-between gap-4 rounded-2xl px-6 py-5 text-right transition-colors hover:bg-[#fbfcfa] max-[600px]:px-4"
                        :aria-expanded="isCreateFormOpen"
                        aria-controls="tournament-create-form"
                        @click="isCreateFormOpen = !isCreateFormOpen"
                    >
                        <span class="flex min-w-0 items-center gap-4">
                            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-[#eaf3ee] text-xl font-medium text-[#286957]">+</span>
                            <span class="min-w-0">
                                <strong class="block text-[15px] font-bold text-[#29463b]">تعریف مسابقه</strong>
                                <small class="mt-1 block text-[11px] text-[#849087]">
                                    {{ tournaments.total === 0 ? 'برای شروع، اطلاعات اولین مسابقه را وارد کنید.' : 'برای ساخت مسابقه‌ی جدید، فرم را باز کنید.' }}
                                </small>
                            </span>
                        </span>
                        <svg
                            aria-hidden="true"
                            viewBox="0 0 20 20"
                            class="h-5 w-5 shrink-0 text-[#527263] transition-transform duration-300 ease-in-out"
                            :class="isCreateFormOpen ? 'rotate-180' : ''"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >
                            <path d="m5 7.5 5 5 5-5" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </button>

                    <Transition name="accordion">
                        <div v-if="isCreateFormOpen" id="tournament-create-form" class="grid">
                            <div class="min-h-0">
                                <form
                                    @submit.prevent="submit"
                                    class="mx-auto grid w-full max-w-[880px] grid-cols-1 gap-x-6 gap-y-4 border-t border-[#edf0eb] bg-[#fcfdfa] px-6 py-6 md:grid-cols-2 max-[600px]:px-4"
                                >
                                    <div>
                                        <label for="name" class="mb-1.5 block text-xs font-medium text-[#63756b]">نام مسابقه</label>
                                        <input
                                            id="name"
                                            v-model="form.name"
                                            required
                                            maxlength="255"
                                            placeholder="مثلاً مسابقات قهرمانی استان"
                                            class="w-full rounded-lg border border-[#dce5da] bg-white px-3 py-2.5 text-sm text-[#253c31] placeholder:text-[#a5aea6] focus:border-[#79a28b] focus:outline-2 focus:outline-[#79a28b]/30"
                                        >
                                    </div>
                                    <div>
                                        <label for="venue" class="mb-1.5 block text-xs font-medium text-[#63756b]">محل برگزاری</label>
                                        <input
                                            id="venue"
                                            v-model="form.venue"
                                            maxlength="255"
                                            placeholder="نام سالن"
                                            class="w-full rounded-lg border border-[#dce5da] bg-white px-3 py-2.5 text-sm text-[#253c31] placeholder:text-[#a5aea6] focus:border-[#79a28b] focus:outline-2 focus:outline-[#79a28b]/30"
                                        >
                                    </div>
                                    <PersianDateField
                                        id="starts"
                                        v-model="form.starts_on"
                                        label="تاریخ شروع (شمسی)"
                                        required
                                    />
                                    <PersianDateField
                                        id="ends"
                                        v-model="form.ends_on"
                                        label="تاریخ پایان (اختیاری، شمسی)"
                                        :min-iso="form.starts_on"
                                    />

                                    <div class="flex flex-wrap items-center gap-4 md:col-span-2">
                                        <button
                                            class="inline-flex min-h-11 items-center justify-center rounded-lg bg-[#286957] px-5 py-2.5 text-[13px] font-semibold text-white transition-colors hover:bg-[#1b5142] disabled:cursor-wait disabled:opacity-60"
                                            :disabled="form.processing"
                                        >
                                            {{ form.processing ? 'در حال ثبت…' : 'ساخت مسابقه' }}
                                        </button>
                                    </div>

                                    <div
                                        v-if="Object.keys(form.errors).length"
                                        class="grid gap-1 rounded-lg border border-[#f0d5d2] bg-[#fff7f6] px-4 py-3 text-xs text-[#a33f38] md:col-span-2"
                                        role="alert"
                                    >
                                        <p v-for="(error, key) in form.errors" :key="key">{{ error }}</p>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </Transition>
                </div>
            </section>

            <section class="grid gap-5">
                <nav class="flex flex-wrap gap-2 border-b border-[#e3e9e2] pb-2" aria-label="فیلتر مسابقات">
                    <Link
                        :href="listUrls.all"
                        :aria-current="statusFilter === 'all' ? 'page' : undefined"
                        class="min-h-11 rounded-lg border px-4 py-2.5 text-[13px] font-semibold transition-colors"
                        :class="statusFilter === 'all' ? 'border-[#286957] bg-[#286957] text-white shadow-sm' : 'border-[#e2e9df] bg-white text-[#66776c] hover:border-[#bfd1c1] hover:bg-[#f7faf6]'"
                    >همه</Link>
                    <Link
                        :href="listUrls.active"
                        :aria-current="statusFilter === 'active' ? 'page' : undefined"
                        class="min-h-11 rounded-lg border px-4 py-2.5 text-[13px] font-semibold transition-colors"
                        :class="statusFilter === 'active' ? 'border-[#286957] bg-[#286957] text-white shadow-sm' : 'border-[#e2e9df] bg-white text-[#66776c] hover:border-[#bfd1c1] hover:bg-[#f7faf6]'"
                    >مسابقات جاری و پایان‌یافته</Link>
                    <Link
                        :href="listUrls.archived"
                        :aria-current="statusFilter === 'archived' ? 'page' : undefined"
                        class="min-h-11 rounded-lg border px-4 py-2.5 text-[13px] font-semibold transition-colors"
                        :class="statusFilter === 'archived' ? 'border-[#286957] bg-[#286957] text-white shadow-sm' : 'border-[#e2e9df] bg-white text-[#66776c] hover:border-[#bfd1c1] hover:bg-[#f7faf6]'"
                    >آرشیو</Link>
                </nav>

                <div class="flex flex-wrap items-end justify-between gap-2">
                    <h2 class="text-[17px] font-bold text-[#29463b]">
                        {{ statusFilter === 'archived' ? 'آرشیو مسابقات' : 'فهرست مسابقات' }}
                    </h2>
                    <span class="text-xs text-[#849087]">{{ tournaments.total.toLocaleString('fa-IR') }} رویداد</span>
                </div>

                <div v-if="tournaments.data.length" class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <TournamentCard
                        v-for="tournament in tournaments.data"
                        :key="tournament.id"
                        :tournament="tournament"
                    />
                </div>
                <div v-else class="rounded-2xl border border-dashed border-[#ccd9ca] bg-[#fafcf8] px-5 py-12 text-center">
                    <span class="mx-auto mb-3 grid h-12 w-12 place-items-center rounded-full bg-[#edf3eb] text-xl text-[#82967f]">◇</span>
                    <h2 class="text-sm font-bold text-[#40594b]">مسابقه‌ای برای نمایش وجود ندارد.</h2>
                    <p class="mt-2 text-xs text-[#8b988a]">فیلتر دیگری انتخاب کنید یا یک مسابقه‌ی جدید بسازید.</p>
                </div>

                <nav
                    v-if="tournaments.data.length"
                    class="flex items-center justify-between gap-3 rounded-xl border border-[#e5ebe3] bg-white px-4 py-3 text-xs text-[#748178]"
                    aria-label="صفحه‌بندی"
                >
                    <Link
                        v-if="tournaments.prev_page_url"
                        :href="tournaments.prev_page_url"
                        class="rounded-lg px-3 py-2 font-medium text-[#286957] hover:bg-[#f1f6f1]"
                    >صفحهٔ قبل</Link>
                    <span v-else></span>
                    <span>{{ tournaments.current_page }} / {{ tournaments.last_page }}</span>
                    <Link
                        v-if="tournaments.next_page_url"
                        :href="tournaments.next_page_url"
                        class="rounded-lg px-3 py-2 font-medium text-[#286957] hover:bg-[#f1f6f1]"
                    >صفحهٔ بعد</Link>
                    <span v-else></span>
                </nav>
            </section>
        </div>
    </AppLayout>
</template>

<style scoped>
.accordion-enter-active,
.accordion-leave-active {
    overflow: hidden;
    transition:
        grid-template-rows 280ms cubic-bezier(0.22, 1, 0.36, 1),
        opacity 180ms ease;
}

.accordion-enter-from,
.accordion-leave-to {
    grid-template-rows: 0fr;
    opacity: 0;
}

.accordion-enter-to,
.accordion-leave-from {
    grid-template-rows: 1fr;
    opacity: 1;
}

@media (prefers-reduced-motion: reduce) {
    .accordion-enter-active,
    .accordion-leave-active {
        transition: none;
    }
}
</style>
