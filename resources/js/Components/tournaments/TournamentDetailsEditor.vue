<script setup>
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import FormErrors from '../../Shared/Components/FormErrors.vue';
import PersianDateField from './PersianDateField.vue';

const props = defineProps({ tournament: Object, base: String });
const editing = ref(false);
const form = useForm({ name: '', venue: '', starts_on: '', ends_on: '', timezone: '' });
const beginEdit = () => {
    form.clearErrors();
    for (const field of ['name', 'venue', 'starts_on', 'ends_on', 'timezone']) form[field] = props.tournament[field] || '';
    editing.value = true;
};
const save = () => form.put(props.base, { preserveScroll: true, onSuccess: () => editing.value = false });
const formatDate = value => {
    if (!value) return 'تعیین نشده';
    const dateOnly = /^(\d{4}-\d{2}-\d{2})/.exec(String(value))?.[1];
    const date = dateOnly ? new Date(`${dateOnly}T12:00:00Z`) : new Date(value);
    return Number.isNaN(date.getTime()) ? 'تعیین نشده' : new Intl.DateTimeFormat('fa-IR', { year: 'numeric', month: 'long', day: 'numeric', timeZone: 'UTC' }).format(date);
};
</script>
<template>
    <!-- Keep tournament settings styled with Tailwind utilities. -->
    <section class="mb-6 rounded-xl border border-[#c8ddcb] bg-[#e7f1e8] p-5 shadow-[0_6px_20px_#26452c0a] min-[761px]:p-[27px] [&_h2]:mb-4 [&_h2]:text-lg [&_h2]:font-bold [&_h2]:text-[#1e352e] [&_label]:mb-1 [&_label]:block [&_label]:text-xs [&_label]:font-semibold [&_label]:text-[#42664f] [&_form>div>input]:w-full [&_form>div>input]:rounded-lg [&_form>div>input]:border [&_form>div>input]:border-[#cfd9cc] [&_form>div>input]:bg-white [&_form>div>input]:px-3 [&_form>div>input]:py-2.5">
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3"></div>
        <dl v-if="!editing" class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <div class="rounded-xl border border-[#d8e6d9] bg-white px-4 py-3"><dt class="text-[11px] font-semibold text-[#718579]">نام مسابقه</dt><dd class="mt-1 break-words text-sm font-bold text-[#254b3d]">{{ tournament.name || '—' }}</dd></div>
            <div class="rounded-xl border border-[#d8e6d9] bg-white px-4 py-3"><dt class="text-[11px] font-semibold text-[#718579]">محل برگزاری</dt><dd class="mt-1 break-words text-sm font-bold text-[#254b3d]">{{ tournament.venue || 'تعیین نشده' }}</dd></div>
            <div class="rounded-xl border border-[#d8e6d9] bg-white px-4 py-3"><dt class="text-[11px] font-semibold text-[#718579]">تاریخ شروع</dt><dd class="mt-1 text-sm font-bold text-[#254b3d]">{{ formatDate(tournament.starts_on) }}</dd></div>
            <div class="rounded-xl border border-[#d8e6d9] bg-white px-4 py-3"><dt class="text-[11px] font-semibold text-[#718579]">تاریخ پایان</dt><dd class="mt-1 text-sm font-bold text-[#254b3d]">{{ formatDate(tournament.ends_on) }}</dd></div>
            <div class="rounded-xl border border-[#d8e6d9] bg-white px-4 py-3"><dt class="text-[11px] font-semibold text-[#718579]">منطقهٔ زمانی</dt><dd class="mt-1 text-sm font-bold text-[#254b3d]" dir="ltr">{{ tournament.timezone || 'Asia/Tehran' }}</dd></div>
        </dl>
        <div v-if="!editing" class="mt-4 flex justify-start"><button type="button" class="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg border border-[#1f604d] bg-[#286957] px-5 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-[#1f5747] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#286957]" @click="beginEdit"><span aria-hidden="true">✎</span>ویرایش</button></div>
        <form v-else @submit.prevent="save" class="mt-[18px] grid grid-cols-1 gap-[18px] min-[761px]:grid-cols-3">
            <div><label for="edit-tournament-name">نام مسابقه</label><input id="edit-tournament-name" v-model="form.name" required maxlength="255"></div>
            <div><label for="edit-tournament-venue">محل برگزاری</label><input id="edit-tournament-venue" v-model="form.venue" maxlength="255"></div>
            <PersianDateField id="edit-tournament-start" v-model="form.starts_on" label="تاریخ شروع (شمسی)" required :disabled="!tournament.dates_editable" />
            <PersianDateField id="edit-tournament-end" v-model="form.ends_on" label="تاریخ پایان (اختیاری، شمسی)" :min-iso="form.starts_on" :disabled="!tournament.dates_editable" />
            <div><label for="edit-tournament-timezone">منطقهٔ زمانی</label><input id="edit-tournament-timezone" v-model="form.timezone" dir="ltr" required :disabled="!tournament.dates_editable"></div>
            <p v-if="!tournament.dates_editable" class="my-3 text-xs leading-6 text-[#8b988a] col-span-full">بعد از قرعه، تاریخ و منطقهٔ زمانی ثابت است؛ نام و محل برگزاری قابل اصلاح‌اند.</p>
            <FormErrors class="col-span-full" :errors="form.errors"/>
            <div class="flex flex-wrap items-center gap-2.5 col-span-full"><button class="inline-flex min-h-11 items-center justify-center rounded-lg border border-transparent bg-[#286957] px-5 py-2.5 text-sm font-semibold text-white disabled:cursor-wait disabled:opacity-55" :disabled="form.processing">ذخیرهٔ اطلاعات مسابقه</button><button type="button" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-[#aebcaf] bg-[#e4ebe4] px-5 py-2.5 text-sm font-semibold !text-[#344b3e] transition hover:bg-[#d6e1d7] disabled:cursor-wait disabled:opacity-55" :disabled="form.processing" @click="editing = false">انصراف</button></div>
        </form>
    </section>
</template>
