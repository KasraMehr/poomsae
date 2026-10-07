<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch } from 'vue';
import FormErrors from './FormErrors.vue';
const props = defineProps({ tournament: Object, base: String, ageCategories: Array, ageTableYear: Number });
const court = useForm({ name: '' });
const editingCourtId = ref(null);
const courtEdit = useForm({ name: '' });
const beginCourtEdit = selected => { editingCourtId.value = selected.id; courtEdit.name = selected.name; courtEdit.clearErrors(); };
const saveCourt = () => courtEdit.put(props.base + '/courts/' + editingCourtId.value, { preserveScroll: true, onSuccess: () => editingCourtId.value = null });
const member = useForm({ name: '', email: '', password: '', role: 'judge' });
const editingCategoryId = ref(null);
const loadingCategory = ref(false);
const editingCategory = computed(() => props.tournament.categories.find(item => item.id === editingCategoryId.value));
const category = useForm({ name: '', discipline: 'recognized', entry_type: 'individual', gender: 'male', age_category: 'recognized_individual_under_12', minimum_age: null, maximum_age: null, form_names: [], selected_form_numbers: [2, 3, 4, 5, 6, 7, 8, 9], format: 'knockout', execution_mode: 'alternating', performance_order: 'consecutive', judge_count: 5, accuracy_max: 400, discard_each_end: 1, rules_acknowledged: false, planned_round_count: 1, allow_form_repetition: true, form_draw_timing: 'morning', form_draw_time: '08:00', draw_method: 'random' });
const availableAgeCategories = computed(() => props.ageCategories.filter(ageCategory => ageCategory.discipline === category.discipline && ageCategory.entry_type === category.entry_type));
const selectedAgeCategory = computed(() => availableAgeCategories.value.find(ageCategory => ageCategory.id === category.age_category));
let suggestedName = '';
const suggestCategoryName = () => {
    if (loadingCategory.value || !selectedAgeCategory.value) return;
    const name = selectedAgeCategory.value.label + ' · ' + (category.gender === 'female' ? 'بانوان' : 'آقایان');
    if (!category.name || category.name === suggestedName) category.name = name;
    suggestedName = name;
};
watch(availableAgeCategories, ageCategories => {
    if (loadingCategory.value) return;
    if (!ageCategories.some(ageCategory => ageCategory.id === category.age_category)) category.age_category = ageCategories[0]?.id ?? '';
}, { immediate: true });
watch(() => category.age_category, ageCategoryId => {
    if (loadingCategory.value) return;
    const ageCategory = availableAgeCategories.value.find(item => item.id === ageCategoryId);
    if (ageCategory) category.selected_form_numbers = [...ageCategory.forms];
});
watch([selectedAgeCategory, () => category.gender], suggestCategoryName, { immediate: true });
watch(() => category.judge_count, (judgeCount) => {
    if (loadingCategory.value) return;
    if (judgeCount === 3) category.discard_each_end = 0;
    else if (category.discard_each_end === 0 || judgeCount === 5) category.discard_each_end = 1;
});
watch(() => category.discipline, discipline => {
    if (loadingCategory.value) return;
    category.entry_type = 'individual';
    category.accuracy_max = discipline === 'freestyle' ? 600 : 400;
    if (discipline === 'freestyle') {
        category.execution_mode = 'alternating';
        category.performance_order = 'consecutive';
    }
});
const toggleForm = formNumber => {
    if (category.selected_form_numbers.includes(formNumber)) {
        if (category.selected_form_numbers.length <= 2) return;
        category.selected_form_numbers = category.selected_form_numbers.filter(number => number !== formNumber);
    } else {
        category.selected_form_numbers = [...category.selected_form_numbers, formNumber].sort((left, right) => left - right);
    }
};
const loadCategory = async id => {
    loadingCategory.value = true;
    category.clearErrors();
    editingCategoryId.value = id || null;
    const selected = editingCategory.value;
    if (selected) {
        for (const field of ['name', 'discipline', 'entry_type', 'gender', 'minimum_age', 'maximum_age', 'format', 'execution_mode', 'performance_order', 'planned_round_count', 'allow_form_repetition', 'form_draw_timing']) category[field] = selected[field];
        category.form_draw_time = selected.form_draw_time?.slice(0, 5) || '08:00';
        category.age_category = selected.rules?.age_category?.id || '';
        category.judge_count = Number(selected.judge_count);
        category.accuracy_max = selected.discipline === 'freestyle' ? 600 : 400;
        category.discard_each_end = selected.rules?.discard_each_end ?? 1;
        category.form_names = selected.form_pool.map(form => form.name);
        category.selected_form_numbers = selected.form_pool.map(form => Number(form.name.replace(/^فرم\s+/, ''))).filter(Number.isFinite);
        category.rules_acknowledged = false;
    } else {
        category.reset();
    }
    await nextTick();
    loadingCategory.value = false;
    if (!selected) { category.selected_form_numbers = [...(selectedAgeCategory.value?.forms || [])]; suggestCategoryName(); }
};
const submitCategory = () => {
    category.transform(data => {
        const payload = { ...data, execution_mode: data.discipline === 'freestyle' ? 'alternating' : data.execution_mode, performance_order: data.discipline === 'freestyle' ? 'consecutive' : data.performance_order };
        if (payload.age_category) {
            payload.planned_round_count = payload.format === 'knockout' ? 1 : payload.planned_round_count;
        } else {
            delete payload.age_category;
            delete payload.selected_form_numbers;
            if (payload.planned_round_count === null) delete payload.planned_round_count;
        }
        if (payload.discipline === 'freestyle') { delete payload.selected_form_numbers; delete payload.form_names; }
        return payload;
    });
    const options = { preserveScroll: true, onSuccess: () => loadCategory(null) };
    if (editingCategoryId.value) category.put(props.base + '/categories/' + editingCategoryId.value, options);
    else category.post(props.base + '/categories', options);
};
</script>
<template>
    <div class="setup-grid">
        <section class="panel">
            <h2>۱. زمین‌های مسابقه</h2>
            <div v-for="selectedCourt in tournament.courts" :key="selectedCourt.id" class="section-heading"><span>{{ selectedCourt.name }}</span><button type="button" class="button secondary small" :disabled="courtEdit.processing" @click="beginCourtEdit(selectedCourt)">ویرایش نام زمین</button></div>
            <form v-if="editingCourtId" @submit.prevent="saveCourt"><label for="edit-court-name">نام جدید زمین</label><input id="edit-court-name" v-model="courtEdit.name" required maxlength="100"><FormErrors :errors="courtEdit.errors"/><div class="row-actions"><button class="button" :disabled="courtEdit.processing">ذخیرهٔ نام زمین</button><button type="button" class="button secondary" :disabled="courtEdit.processing" @click="editingCourtId = null">انصراف</button></div></form>
            <form @submit.prevent="court.post(base + '/courts', { preserveScroll: true, onSuccess: () => court.reset() })">
                <label for="court-name">نام زمین</label><input id="court-name" v-model="court.name" required maxlength="100" placeholder="زمین یک">
                <FormErrors :errors="court.errors"/><button class="button" :disabled="court.processing">افزودن زمین</button>
            </form>
        </section>
        <section class="panel">
            <h2>۲. تیم برگزاری</h2>
            <form @submit.prevent="member.post(base + '/members', { preserveScroll: true, onSuccess: () => member.reset() })" class="fields-two">
                <div><label for="member-name">نام و نام خانوادگی</label><input id="member-name" v-model="member.name" required maxlength="100"></div>
                <div><label for="member-role">نقش</label><select id="member-role" v-model="member.role"><option value="judge">داور</option><option value="operator">اپراتور</option><option value="display">نمایشگر سالن</option></select></div>
                <div><label for="member-email">ایمیل ورود</label><input id="member-email" v-model="member.email" type="email" dir="ltr" required autocomplete="off"></div>
                <div><label for="member-password">رمز حساب جدید (۱۲ کاراکتر)</label><input id="member-password" v-model="member.password" type="password" dir="ltr" minlength="12" autocomplete="new-password"></div>
                <p class="subtle wide">اگر حساب وجود دارد، فقط نقش اضافه می‌شود و رمز آن تغییر نمی‌کند.</p>
                <FormErrors class="wide" :errors="member.errors"/><button class="button" :disabled="member.processing">افزودن عضو</button>
            </form>
            <div class="member-list"><div v-for="(user,i) in tournament.members" :key="user.id + '-' + i"><span>{{ user.name }} <small dir="ltr">{{ user.email }}</small></span><span class="badge">{{ {judge:'داور',operator:'اپراتور',display:'نمایشگر',manager:'مدیر'}[user.role] }}</span></div></div>
        </section>
        <section class="panel wide">
            <h2>۳. ردهٔ مسابقه</h2>
            <div class="section-heading"><div><label for="edit-category-choice">ساخت یا ویرایش رده و قوانین</label><select id="edit-category-choice" :value="editingCategoryId || ''" :disabled="category.processing" @change="loadCategory(Number($event.target.value))"><option value="">ساخت ردهٔ جدید</option><option v-for="existing in tournament.categories" :key="existing.id" :value="existing.id">{{ existing.name }}</option></select></div></div>
            <p v-if="editingCategory" class="subtle">نسخهٔ قوانین: {{ editingCategory.rule_version }}. با ذخیره، نسخهٔ جدید تأیید می‌شود.</p>
            <p v-if="editingCategory && !editingCategory.settings_editable" class="notice">ویرایش تنظیمات فقط قبل از قرعهٔ فرم‌ها و ساخت جدول، برای رده‌های محلی مجاز است.</p>
            <form @submit.prevent="submitCategory" class="tournament-form">
                <fieldset class="wide tournament-form category-fields" :disabled="loadingCategory || Boolean(editingCategory && !editingCategory.settings_editable)">
                <div><label for="category-name">نام رده</label><input id="category-name" v-model="category.name" required maxlength="100" placeholder="انفرادی بزرگسالان"></div>
                <div><label for="discipline">سبک</label><select id="discipline" v-model="category.discipline" :disabled="Boolean(editingCategory?.entries.length)"><option value="recognized">استاندارد</option><option value="freestyle">ابداعی</option></select></div>
                <div><label for="entry-type">نوع شرکت</label><select id="entry-type" v-model="category.entry_type" :disabled="Boolean(editingCategory?.entries.length)"><option value="individual">انفرادی</option><option v-if="category.discipline === 'recognized'" value="team">تیمی سه‌نفره</option><option v-else value="pair">تیمی ابداعی دو نفره</option></select></div>
                <div><label for="gender">جنسیت</label><select id="gender" v-model="category.gender"><option value="female">بانوان</option><option value="male">آقایان</option></select></div>
                <div><label for="age-category">ردهٔ سنی</label><select id="age-category" v-model="category.age_category" :required="!editingCategory"><option v-if="editingCategory && !editingCategory.rules?.age_category" value="">بازهٔ سنی فعلی رده</option><option v-for="ageCategory in availableAgeCategories" :key="ageCategory.id" :value="ageCategory.id">{{ ageCategory.label }}</option></select><small>مطابق جدول رده‌های سنی ایران، سال {{ ageTableYear.toLocaleString('fa-IR', { useGrouping: false }) }}</small></div>
                <template v-if="editingCategory && !category.age_category"><div><label for="edit-min-age">حداقل سن</label><input id="edit-min-age" v-model.number="category.minimum_age" type="number" min="1" max="100"></div><div><label for="edit-max-age">حداکثر سن</label><input id="edit-max-age" v-model.number="category.maximum_age" type="number" min="1" max="100"></div><div v-if="category.discipline === 'recognized'" class="wide"><label>فرم‌های مجاز</label><input v-for="(_, index) in category.form_names" :key="index" v-model="category.form_names[index]" required maxlength="100" :aria-label="`فرم ${index + 1}`"></div></template>
                <div v-if="selectedAgeCategory"><label>تاریخ تولد مجاز (شمسی)</label><p>{{ selectedAgeCategory.birth_date_label }}</p></div>
                <div><label for="format">مدل برگزاری</label><select id="format" v-model="category.format"><option value="knockout">تک‌حذفی</option><option value="round_robin">دورهای</option></select><small>تعداد شرکت‌کنندگان سقف ندارد.</small></div>
                <div v-if="category.discipline === 'freestyle'"><label>ترتیب اجرای ابداعی</label><p>{{ category.format === 'knockout' ? 'نوبتی · ابتدا A، سپس B' : 'نوبتی · A، سپس B، سپس C و ...' }}</p><small>هر ورزشکار یا زوج در هر مرحله یک اجرا با موسیقی دارد. اعضای هر زوج با هم یک اجرا انجام می‌دهند.</small></div>
                <div v-else-if="category.format === 'knockout'"><label for="execution-mode">شیوهٔ اجرای تک‌حذفی</label><select id="execution-mode" v-model="category.execution_mode"><option value="alternating">سینگل · A۱، B۱، A۲، B۲</option><option value="simultaneous">دوبل · دو ورزشکار همزمان در هر فرم</option></select></div>
                <div v-else><label for="performance-order">ترتیب اجرای دورهای</label><select id="performance-order" v-model="category.performance_order"><option value="consecutive">دو فرم هر ورودی پشت‌سرهم</option><option value="phased">فرم اول همه، سپس فرم دوم همه</option></select></div>
                <div><label for="judges">تعداد داور</label><select id="judges" v-model.number="category.judge_count"><option :value="3">۳ داور</option><option :value="5">۵ داور</option><option :value="7">۷ داور</option></select></div>
                <div v-if="category.format === 'knockout'"><label>تعداد مراحل</label><p>خودکار بر اساس تعداد ورودی‌های حاضر</p><small>پس از تعیین حضور و قبل از قرعه محاسبه می‌شود؛ مثلاً ۸ ورودی = ۳ مرحله و ۹ تا ۱۶ ورودی = ۴ مرحله. هر تیم یا زوج یک ورودی محسوب می‌شود.</small></div>
                <div v-else><label for="stage-count">تعداد مراحل</label><input id="stage-count" v-model.number="category.planned_round_count" type="number" min="1" :max="category.discipline === 'recognized' && !category.allow_form_repetition ? 4 : undefined" required><small>نام مراحل خودکار است: مرحله 1، مرحله 2 و ... .</small></div>
                <template v-if="category.discipline === 'recognized'">
                    <div v-if="selectedAgeCategory" class="wide"><h3>فرم‌های مجاز ردهٔ سنی</h3><div class="chips"><label v-for="number in selectedAgeCategory.forms" :key="number" class="check badge"><input type="checkbox" :checked="category.selected_form_numbers.includes(number)" :disabled="category.selected_form_numbers.includes(number) && category.selected_form_numbers.length <= 2" @change="toggleForm(number)">فرم {{ number.toLocaleString('fa-IR') }}</label></div><p class="subtle">فرم‌ها ابتدا طبق جدول سنی انتخاب می‌شوند. می‌توانید فهرست را ویرایش کنید؛ حداقل دو فرم از همین فهرست مجاز باید باقی بماند.</p></div>
                    <div><label for="form-repetition">تکرار بین مراحل</label><select id="form-repetition" v-model="category.allow_form_repetition"><option :value="true">مجاز</option><option :value="false">غیرتکراری · حداکثر چهار مرحله</option></select></div>
                    <div><label for="form-draw-timing">زمان قرعهٔ پومسه‌ها</label><select id="form-draw-timing" v-model="category.form_draw_timing"><option value="day_before">روز قبل مسابقه · تمام مراحل</option><option value="morning">صبح مسابقه · تمام مراحل</option><option value="before_stage">قبل از شروع هر مرحله</option></select></div>
                    <div v-if="category.form_draw_timing !== 'before_stage'"><label for="form-draw-time">ساعت قرعه در منطقهٔ زمانی مسابقه</label><input id="form-draw-time" v-model="category.form_draw_time" type="time" required></div>
                </template>
                <div><label>ساختار امتیاز</label><p v-if="category.discipline === 'recognized'">دقت از ۴ با افزایش/کاهش ۰٫۱ و ۰٫۳؛ اجرا از ۶ در سه مؤلفهٔ ۲ نمره‌ای.</p><p v-else>دقت از ۶ در سه مؤلفهٔ ۲ نمره‌ای؛ اجرا از ۴ با افزایش/کاهش ۰٫۱ و ۰٫۳. یک اجرا با موسیقی.</p></div>
                <div class="rules-summary wide">
                    <label for="discard-each-end">روش حذف نمره‌های داوران</label>
                    <select id="discard-each-end" v-model.number="category.discard_each_end">
                        <option v-if="category.judge_count === 3" :value="0">بدون حذف نمره · میانگین هر ۳ داور</option>
                        <option v-else :value="1">یک نمرهٔ بالا و یک نمرهٔ پایین · روش WT</option>
                        <option :value="2" :disabled="category.judge_count !== 7">دو نمرهٔ بالا و دو نمرهٔ پایین · فقط ۷ داور، روش سفارشی</option>
                    </select>
                    <p v-if="category.discipline === 'recognized'">برای دقت و اجرا جداگانه اعمال می‌شود. داور کسرهای دقت و سه مؤلفهٔ اجرا را ثبت می‌کند؛ نتیجهٔ رقابت مجموع دو فرم است.</p>
                    <p v-else>دقت و اجرا جداگانه فیلتر و میانگین‌گیری می‌شوند؛ جمع دو میانگین، نتیجهٔ یک اجرای ابداعی از ۱۰ است.</p>
                    <p>در تساوی، نمره‌های حذف‌شده برمی‌گردند و میانگین همهٔ داوران معیار دوم است. پذیرش هر ورزشکار بر اساس بازهٔ تاریخ تولد جدول ردهٔ انتخاب‌شده است.</p>
                    <label class="check"><input v-model="category.rules_acknowledged" type="checkbox" required>این روش محاسبه و شرایط رده را برای این رویداد تأیید می‌کنم.</label>
                </div>
                <FormErrors class="wide" :errors="category.errors"/><div class="row-actions wide"><button class="button" :disabled="category.processing || (!selectedAgeCategory && !editingCategory)">{{ editingCategory ? 'ذخیرهٔ رده و قوانین' : 'ساخت رده' }}</button><button v-if="editingCategory" type="button" class="button secondary" :disabled="category.processing" @click="loadCategory(null)">انصراف از ویرایش</button></div>
                </fieldset>
            </form>
            <details class="age-table"><summary>جدول رده‌های سنی و فرم‌های ایران · {{ ageTableYear.toLocaleString('fa-IR', { useGrouping: false }) }}</summary><div class="table-wrap"><table><thead><tr><th>ردیف</th><th>ردهٔ سنی</th><th>تاریخ تولد</th><th>فرم</th></tr></thead><tbody><tr v-for="(ageCategory, index) in ageCategories" :key="ageCategory.id" :class="{ 'selected-age-row': category.age_category === ageCategory.id }"><td>{{ (index + 1).toLocaleString('fa-IR') }}</td><td>{{ ageCategory.label }}</td><td>{{ ageCategory.birth_date_label }}</td><td>{{ ageCategory.forms.length ? ageCategory.forms.map(number => number.toLocaleString('fa-IR')).join('–') : '—' }}</td></tr></tbody></table></div></details>
        </section>
    </div>
</template>
<style scoped>
.category-fields { border: 0; padding: 0; margin: 0; min-width: 0; }
.chips { flex-wrap: wrap; }
.age-table { margin-top: 1.5rem; }
.age-table summary { cursor: pointer; font-weight: 700; margin-bottom: 1rem; }
.age-table th { background: #f7e75a; color: #181818; }
.age-table td { white-space: nowrap; }
.selected-age-row { background: #fef9c3; color: #181818; }
</style>
