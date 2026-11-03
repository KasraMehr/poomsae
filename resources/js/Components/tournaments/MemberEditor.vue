<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import DropdownSelect from '../../Shared/Components/DropdownSelect.vue';
import FormErrors from '../../Shared/Components/FormErrors.vue';

const props = defineProps({ user: Object, base: String });
const editing = ref(false);
const form = useForm({ name: props.user.name, email: props.user.email, password: '', role: props.user.role });
const removal = useForm({});
const roles = [
    { value: 'judge', label: 'داور' },
    { value: 'operator', label: 'اپراتور' },
    ...(props.user.role === 'display' ? [{ value: 'display', label: 'نمایشگر قدیمی' }] : []),
];
const save = () => form.put(`${props.base}/members/${props.user.id}`, {
    preserveScroll: true,
    onSuccess: () => { form.password = ''; editing.value = false; },
});
const remove = () => removal.delete(`${props.base}/members/${props.user.id}`, { preserveScroll: true });
</script>

<template>
    <div class="member-entry">
        <div class="member-entry-head"><span><strong>{{ user.name }}</strong><small dir="ltr">{{ user.email }}</small></span><span class="badge">{{ { judge: 'داور', operator: 'اپراتور', display: 'نمایشگر' }[user.role] || user.role }}</span><button v-if="user.role !== 'manager'" type="button" class="button secondary small" @click="editing = !editing">{{ editing ? 'بستن' : 'ویرایش' }}</button></div>
        <form v-if="editing" class="member-edit-fields" @submit.prevent="save">
            <div><label :for="`member-name-${user.id}`">نام</label><input :id="`member-name-${user.id}`" v-model="form.name" required maxlength="100"></div>
            <div><label :for="`member-email-${user.id}`">ایمیل ورود</label><input :id="`member-email-${user.id}`" v-model="form.email" type="email" dir="ltr" required></div>
            <DropdownSelect :id="`member-role-${user.id}`" v-model="form.role" label="نقش در مسابقه" :options="roles" />
            <div><label :for="`member-password-${user.id}`">رمز جدید (اختیاری)</label><input :id="`member-password-${user.id}`" v-model="form.password" type="password" dir="ltr" minlength="12" autocomplete="new-password"></div>
            <FormErrors :errors="form.errors" class="wide" />
            <FormErrors :errors="removal.errors" class="wide" />
            <div class="member-actions wide"><button class="button" :disabled="form.processing || removal.processing">ذخیرهٔ تغییرات</button><button type="button" class="button secondary" :disabled="form.processing || removal.processing" @click="remove">حذف دسترسی از این مسابقه</button></div>
            <p class="wide subtle">حذف دسترسی، حساب را برای مسابقات دیگر نگه می‌دارد.</p>
        </form>
    </div>
</template>

<style scoped>
.member-entry{display:block!important;width:100%;padding:12px 0;border-top:1px solid #d9e5da}.member-entry-head{display:flex;align-items:center;gap:10px;flex-wrap:wrap}.member-entry-head>span:first-child{display:grid;gap:3px;flex:1;min-width:180px}.member-entry-head small{color:#73877a}.member-edit-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:14px}.member-edit-fields .wide{grid-column:1/-1}.member-actions{display:flex;flex-wrap:wrap;gap:8px}@media(max-width:620px){.member-edit-fields{grid-template-columns:1fr}}
</style>
