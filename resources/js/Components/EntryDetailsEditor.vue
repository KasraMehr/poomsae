<script setup>
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import FormErrors from './FormErrors.vue';

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
    <div>
        <button v-if="!editing" type="button" class="button secondary small" @click="beginEdit">ویرایش ورزشکار</button>
        <form v-else @submit.prevent="save" class="entry-editor">
            <p v-if="category.registration_locked" class="subtle">بعد از قرعه فقط نام و باشگاه قابل اصلاح است.</p>
            <div v-for="(member, index) in form.members" :key="member.id" class="fields-two">
                <h3 v-if="form.members.length > 1" class="wide">عضو {{ index + 1 }}</h3>
                <div><label :for="`edit-first-${member.id}`">نام</label><input :id="`edit-first-${member.id}`" v-model="member.first_name" required maxlength="100"></div>
                <div><label :for="`edit-last-${member.id}`">نام خانوادگی</label><input :id="`edit-last-${member.id}`" v-model="member.last_name" required maxlength="100"></div>
                <div><label :for="`edit-birth-${member.id}`">تاریخ تولد (میلادی)</label><input :id="`edit-birth-${member.id}`" v-model="member.birth_date" type="date" dir="ltr" required :max="tournament.starts_on" :disabled="category.registration_locked"></div>
                <div><label :for="`edit-gender-${member.id}`">جنسیت</label><select :id="`edit-gender-${member.id}`" v-model="member.gender" :disabled="category.registration_locked"><option value="male">آقایان</option><option value="female">بانوان</option></select></div>
                <div><label :for="`edit-club-${member.id}`">باشگاه</label><input :id="`edit-club-${member.id}`" v-model="member.club" maxlength="100"></div>
            </div>
            <FormErrors :errors="form.errors"/>
            <div class="row-actions"><button class="button small" :disabled="form.processing">ذخیرهٔ ورزشکار</button><button type="button" class="button secondary small" :disabled="form.processing" @click="editing = false">انصراف</button></div>
        </form>
    </div>
</template>
<style scoped>
.entry-editor { min-width: 360px; padding: 12px; white-space: normal; }
</style>
