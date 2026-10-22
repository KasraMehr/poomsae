<script setup>
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import FormErrors from '../../Shared/Components/FormErrors.vue';

const props = defineProps({ entry: Object, category: Object, tournament: Object, base: String });
const editing = ref(false);
const form = useForm({ members: [] });
const beginEdit = () => {
    form.clearErrors();
    form.members = props.entry.members.map(member => ({ id: member.id, first_name: member.first_name, last_name: member.last_name, birth_date: member.birth_date, gender: member.gender, club: member.club || '' }));
    editing.value = true;
};
const save = () => form.put(props.base + '/entries/' + props.entry.id, { preserveScroll: true, onSuccess: () => editing.value = false });
</script>
<template>
    <!-- Keep entry editing styled with Tailwind utilities. -->
    <div class="[&_h3]:mb-3 [&_h3]:font-semibold [&_h3]:text-[#315747] [&_label]:mb-1 [&_label]:block [&_label]:text-xs [&_label]:font-semibold [&_label]:text-[#42664f] [&_input]:w-full [&_input]:rounded-lg [&_input]:border [&_input]:border-[#cfd9cc] [&_input]:bg-white [&_input]:px-3 [&_input]:py-2.5 [&_select]:w-full [&_select]:rounded-lg [&_select]:border [&_select]:border-[#cfd9cc] [&_select]:bg-white [&_select]:px-3 [&_select]:py-2.5 [&_button]:cursor-pointer [&_button]:hover:brightness-95">
        <button v-if="!editing" type="button" class="inline-flex min-h-[34px] items-center justify-center rounded-lg border border-transparent bg-[#e8eee7] px-3 py-1.5 text-[11px] font-semibold text-[#42664f] disabled:cursor-wait disabled:opacity-55" @click="beginEdit">ویرایش ورزشکار</button>
        <form v-else @submit.prevent="save" class="min-w-[360px] whitespace-normal p-3">
            <p v-if="category.registration_locked" class="my-3 text-xs leading-6 text-[#8b988a]">بعد از قرعه فقط نام و باشگاه قابل اصلاح است.</p>
            <div v-for="(member, index) in form.members" :key="member.id" class="grid grid-cols-1 gap-[18px] min-[761px]:grid-cols-2">
                <h3 v-if="form.members.length > 1" class="col-span-full">عضو {{ index + 1 }}</h3>
                <div><label :for="`edit-first-${member.id}`">نام</label><input :id="`edit-first-${member.id}`" v-model="member.first_name" required maxlength="100"></div>
                <div><label :for="`edit-last-${member.id}`">نام خانوادگی</label><input :id="`edit-last-${member.id}`" v-model="member.last_name" required maxlength="100"></div>
                <div><label :for="`edit-birth-${member.id}`">تاریخ تولد (میلادی)</label><input :id="`edit-birth-${member.id}`" v-model="member.birth_date" type="date" dir="ltr" required :max="tournament.starts_on" :disabled="category.registration_locked"></div>
                <div><label :for="`edit-gender-${member.id}`">جنسیت</label><select :id="`edit-gender-${member.id}`" v-model="member.gender" :disabled="category.registration_locked"><option value="male">آقایان</option><option value="female">بانوان</option></select></div>
                <div><label :for="`edit-club-${member.id}`">باشگاه</label><input :id="`edit-club-${member.id}`" v-model="member.club" maxlength="100"></div>
            </div>
            <FormErrors :errors="form.errors"/>
            <div class="flex flex-wrap items-center gap-2.5"><button class="inline-flex min-h-[34px] items-center justify-center rounded-lg border border-transparent bg-[#286957] px-3 py-1.5 text-[11px] font-semibold text-white disabled:cursor-wait disabled:opacity-55" :disabled="form.processing">ذخیرهٔ ورزشکار</button><button type="button" class="inline-flex min-h-[34px] items-center justify-center rounded-lg border border-transparent bg-[#e8eee7] px-3 py-1.5 text-[11px] font-semibold text-[#42664f] disabled:cursor-wait disabled:opacity-55" :disabled="form.processing" @click="editing = false">انصراف</button></div>
        </form>
    </div>
</template>
