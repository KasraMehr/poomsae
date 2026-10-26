<script setup>
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import FormErrors from '../../Shared/Components/FormErrors.vue';

const props = defineProps({ tournament: Object, base: String });
const editing = ref(false);
const form = useForm({ name: '', venue: '', starts_on: '', ends_on: '', timezone: '' });
const beginEdit = () => {
    form.clearErrors();
    for (const field of ['name', 'venue', 'starts_on', 'ends_on', 'timezone']) form[field] = props.tournament[field] || '';
    editing.value = true;
};
const save = () => form.put(props.base, { preserveScroll: true, onSuccess: () => editing.value = false });
</script>
<template>
    <!-- Keep tournament settings styled with Tailwind utilities. -->
    <section class="mb-6 rounded-xl border border-[#e2e9df] bg-white p-5 min-[761px]:p-[27px] [&_h2]:mb-4 [&_h2]:text-lg [&_h2]:font-bold [&_h2]:text-[#1e352e] [&_label]:mb-1 [&_label]:block [&_label]:text-xs [&_label]:font-semibold [&_label]:text-[#42664f] [&_input]:w-full [&_input]:rounded-lg [&_input]:border [&_input]:border-[#cfd9cc] [&_input]:bg-white [&_input]:px-3 [&_input]:py-2.5">
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3"><h2>اطلاعات مسابقه</h2><button v-if="!editing" type="button" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-transparent bg-[#286957] px-5 py-2.5 text-sm font-semibold text-white disabled:cursor-wait disabled:opacity-55 !bg-[#e8eee7] !text-[#42664f] min-h-[34px] px-3 py-1.5 text-[11px]" @click="beginEdit">ویرایش اطلاعات مسابقه</button></div>
        <p v-if="!editing" class="my-3 text-xs leading-6 text-[#8b988a]">{{ tournament.name }} · {{ tournament.venue || 'محل تعیین نشده' }} · {{ tournament.starts_on }}</p>
        <form v-else @submit.prevent="save" class="mt-[18px] grid grid-cols-1 gap-[18px] min-[761px]:grid-cols-3">
            <div><label for="edit-tournament-name">نام مسابقه</label><input id="edit-tournament-name" v-model="form.name" required maxlength="255"></div>
            <div><label for="edit-tournament-venue">محل برگزاری</label><input id="edit-tournament-venue" v-model="form.venue" maxlength="255"></div>
            <div><label for="edit-tournament-start">تاریخ شروع (میلادی)</label><input id="edit-tournament-start" v-model="form.starts_on" type="date" dir="ltr" required :disabled="!tournament.dates_editable"></div>
            <div><label for="edit-tournament-end">تاریخ پایان (میلادی)</label><input id="edit-tournament-end" v-model="form.ends_on" type="date" dir="ltr" :min="form.starts_on" :disabled="!tournament.dates_editable"></div>
            <div><label for="edit-tournament-timezone">منطقهٔ زمانی</label><input id="edit-tournament-timezone" v-model="form.timezone" dir="ltr" required :disabled="!tournament.dates_editable"></div>
            <p v-if="!tournament.dates_editable" class="my-3 text-xs leading-6 text-[#8b988a] col-span-full">بعد از قرعه، تاریخ و منطقهٔ زمانی ثابت است؛ نام و محل برگزاری قابل اصلاح‌اند.</p>
            <FormErrors class="col-span-full" :errors="form.errors"/>
            <div class="flex flex-wrap items-center gap-2.5 col-span-full"><button class="inline-flex min-h-11 items-center justify-center rounded-lg border border-transparent bg-[#286957] px-5 py-2.5 text-sm font-semibold text-white disabled:cursor-wait disabled:opacity-55" :disabled="form.processing">ذخیرهٔ اطلاعات مسابقه</button><button type="button" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-transparent bg-[#286957] px-5 py-2.5 text-sm font-semibold text-white disabled:cursor-wait disabled:opacity-55 !bg-[#e8eee7] !text-[#42664f]" :disabled="form.processing" @click="editing = false">انصراف</button></div>
        </form>
    </section>
</template>
