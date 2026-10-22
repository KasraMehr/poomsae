<script setup>
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import FormErrors from '../Shared/Components/FormErrors.vue';

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
    <section class="panel">
        <div class="section-heading"><h2>اطلاعات مسابقه</h2><button v-if="!editing" type="button" class="button secondary small" @click="beginEdit">ویرایش اطلاعات مسابقه</button></div>
        <p v-if="!editing" class="subtle">{{ tournament.name }} · {{ tournament.venue || 'محل تعیین نشده' }} · {{ tournament.starts_on }}</p>
        <form v-else @submit.prevent="save" class="tournament-form">
            <div><label for="edit-tournament-name">نام مسابقه</label><input id="edit-tournament-name" v-model="form.name" required maxlength="255"></div>
            <div><label for="edit-tournament-venue">محل برگزاری</label><input id="edit-tournament-venue" v-model="form.venue" maxlength="255"></div>
            <div><label for="edit-tournament-start">تاریخ شروع (میلادی)</label><input id="edit-tournament-start" v-model="form.starts_on" type="date" dir="ltr" required :disabled="!tournament.dates_editable"></div>
            <div><label for="edit-tournament-end">تاریخ پایان (میلادی)</label><input id="edit-tournament-end" v-model="form.ends_on" type="date" dir="ltr" :min="form.starts_on" :disabled="!tournament.dates_editable"></div>
            <div><label for="edit-tournament-timezone">منطقهٔ زمانی</label><input id="edit-tournament-timezone" v-model="form.timezone" dir="ltr" required :disabled="!tournament.dates_editable"></div>
            <p v-if="!tournament.dates_editable" class="subtle wide">بعد از قرعه، تاریخ و منطقهٔ زمانی ثابت است؛ نام و محل برگزاری قابل اصلاح‌اند.</p>
            <FormErrors class="wide" :errors="form.errors"/>
            <div class="row-actions wide"><button class="button" :disabled="form.processing">ذخیرهٔ اطلاعات مسابقه</button><button type="button" class="button secondary" :disabled="form.processing" @click="editing = false">انصراف</button></div>
        </form>
    </section>
</template>
