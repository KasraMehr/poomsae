<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch } from 'vue';
import CheckboxField from '../../Shared/Components/CheckboxField.vue';
import DropdownSelect from '../../Shared/Components/DropdownSelect.vue';
import TimeField from '../../Shared/Components/TimeField.vue';
import FormErrors from '../../Shared/Components/FormErrors.vue';
const props = defineProps({ tournament: Object, base: String, ageCategories: Array, ageTableYear: Number, section: { type: String, default: 'all' } });
const court = useForm({ name: '' });
const editingCourtId = ref(null);
const courtEdit = useForm({ name: '' });
const beginCourtEdit = selected => { editingCourtId.value = selected.id; courtEdit.name = selected.name; courtEdit.clearErrors(); };
const saveCourt = () => courtEdit.put(props.base + '/courts/' + editingCourtId.value, { preserveScroll: true, onSuccess: () => editingCourtId.value = null });
const member = useForm({ name: '', email: '', password: '', role: 'judge' });
const memberRoleOptions = [
    { value: 'judge', label: 'داور' },
    { value: 'operator', label: 'اپراتور' },
    { value: 'display', label: 'نمایشگر RTDS' },
];
const showMemberPassword = ref(false);
const editingCategoryId = ref(null);
const loadingCategory = ref(false);
const editingCategory = computed(() => props.tournament.categories.find(item => item.id === editingCategoryId.value));
const category = useForm({ name: '', discipline: 'recognized', entry_type: 'individual', gender: 'male', age_category: 'recognized_individual_under_12', minimum_age: null, maximum_age: null, form_names: [], selected_form_numbers: [2, 3, 4, 5, 6, 7, 8, 9], format: 'knockout', execution_mode: 'alternating', performance_order: 'consecutive', judge_count: 5, accuracy_max: 400, discard_each_end: 1, rules_acknowledged: false, planned_round_count: 1, allow_form_repetition: true, form_draw_timing: 'morning', form_draw_time: '08:00', draw_method: 'random' });
const availableAgeCategories = computed(() => props.ageCategories.filter(ageCategory => ageCategory.discipline === category.discipline && ageCategory.entry_type === category.entry_type));
const selectedAgeCategory = computed(() => availableAgeCategories.value.find(ageCategory => ageCategory.id === category.age_category));
const categoryChoiceOptions = computed(() => [
    { value: '', label: 'ساخت ردهٔ جدید' },
    ...props.tournament.categories.map(item => ({ value: Number(item.id), label: item.name })),
]);
const disciplineOptions = [
    { value: 'recognized', label: 'استاندارد' },
    { value: 'freestyle', label: 'ابداعی' },
];
const entryTypeOptions = computed(() => [
    { value: 'individual', label: 'انفرادی' },
    { value: 'pair', label: category.discipline === 'recognized' ? 'دو نفره' : 'تیمی ابداعی دو نفره' },
    ...(category.discipline === 'recognized' ? [{ value: 'team', label: 'تیمی سه‌نفره' }] : []),
]);
const genderOptions = [
    { value: 'female', label: 'بانوان' },
    { value: 'male', label: 'آقایان' },
];
const ageCategoryOptions = computed(() => [
    ...(editingCategory.value && !editingCategory.value.rules?.age_category ? [{ value: '', label: 'بازهٔ سنی فعلی رده' }] : []),
    ...availableAgeCategories.value.map(item => ({ value: item.id, label: item.label })),
]);
const formatOptions = [
    { value: 'knockout', label: 'تک‌حذفی' },
    { value: 'round_robin', label: 'دوره ای' },
];
const executionModeOptions = [
    { value: 'alternating', label: 'سینگل · A۱، B۱، A۲، B۲' },
    { value: 'simultaneous', label: 'دوبل · دو ورزشکار همزمان در هر فرم' },
];
const performanceOrderOptions = [
    { value: 'consecutive', label: 'دو فرم هر ورودی پشت‌سرهم' },
    { value: 'phased', label: 'فرم اول همه، سپس فرم دوم همه' },
];
const judgeCountOptions = [3, 5, 7].map(value => ({ value, label: value.toLocaleString('fa-IR') + ' داور' }));
const repetitionOptions = [
    { value: true, label: 'مجاز' },
    { value: false, label: 'غیرتکراری · حداکثر چهار مرحله' },
];
const drawTimingOptions = [
    { value: 'day_before', label: 'روز قبل مسابقه · تمام مراحل' },
    { value: 'morning', label: 'صبح مسابقه · تمام مراحل' },
    { value: 'before_stage', label: 'قبل از شروع هر مرحله' },
];
const discardScoreOptions = computed(() => [
    ...(category.judge_count === 3
        ? [{ value: 0, label: 'بدون حذف نمره · میانگین هر ۳ داور' }]
        : [{ value: 1, label: 'یک نمرهٔ بالا و یک نمرهٔ پایین · روش WT' }]),
    { value: 2, label: 'دو نمرهٔ بالا و دو نمرهٔ پایین · فقط ۷ داور، روش سفارشی', disabled: category.judge_count !== 7 },
]);
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
const updateSelectedForms = formNumbers => {
    category.selected_form_numbers = [...formNumbers].sort((left, right) => left - right);
};
const highFormNames = {
    9: 'کوریو',
    10: 'کومگانگ',
    11: 'ته بک',
    12: 'پیونگ وان',
    13: 'شیپ جین',
    14: 'جی ته',
    15: 'چون کوان',
    16: 'هانسو',
    17: 'ایل یو',
};
const formLabel = number => highFormNames[number] || `فرم ${number.toLocaleString('fa-IR')}`;
const formatFormsList = forms => {
    if (!forms || !forms.length) return '—';
    return forms.map(number => {
        if (number <= 8) return number.toLocaleString('fa-IR');
        return highFormNames[number] || `فرم ${number.toLocaleString('fa-IR')}`;
    }).join('، ');
};
const formNameToNumber = name => {
    if (!name) return null;
    const map = {
        'فرم ۱': 1, 'فرم 1': 1, '1': 1, '۱': 1,
        'فرم ۲': 2, 'فرم 2': 2, '2': 2, '۲': 2,
        'فرم ۳': 3, 'فرم 3': 3, '3': 3, '۳': 3,
        'فرم ۴': 4, 'فرم 4': 4, '4': 4, '۴': 4,
        'فرم ۵': 5, 'فرم 5': 5, '5': 5, '۵': 5,
        'فرم ۶': 6, 'فرم 6': 6, '6': 6, '۶': 6,
        'فرم ۷': 7, 'فرم 7': 7, '7': 7, '۷': 7,
        'فرم ۸': 8, 'فرم 8': 8, '8': 8, '۸': 8,
        'کوریو': 9, 'فرم ۹': 9, 'فرم 9': 9, '9': 9, '۹': 9,
        'کومگانگ': 10, 'فرم ۱۰': 10, 'فرم 10': 10, '10': 10, '۱۰': 10,
        'ته بک': 11, 'ته‌بک': 11, 'فرم ۱۱': 11, 'فرم 11': 11, '11': 11, '۱۱': 11,
        'پیونگ وان': 12, 'پیونگ‌وان': 12, 'فرم ۱۲': 12, 'فرم 12': 12, '12': 12, '۱۲': 12,
        'شیپ جین': 13, 'شیپ‌جین': 13, 'فرم ۱۳': 13, 'فرم 13': 13, '13': 13, '۱۳': 13,
        'جی ته': 14, 'جی‌ته': 14, 'فرم ۱۴': 14, 'فرم 14': 14, '14': 14, '۱۴': 14,
        'چون کوان': 15, 'چون‌کوان': 15, 'فرم ۱۵': 15, 'فرم 15': 15, '15': 15, '۱۵': 15,
        'هانسو': 16, 'فرم ۱۶': 16, 'فرم 16': 16, '16': 16, '۱۶': 16,
        'ایل یو': 17, 'ایل‌یو': 17, 'فرم ۱۷': 17, 'فرم 17': 17, '17': 17, '۱۷': 17,
    };
    const cleaned = String(name).trim().replace(/\s+/g, ' ');
    if (map[cleaned] !== undefined) return map[cleaned];
    const match = cleaned.match(/(\d+)/);
    return match ? Number(match[1]) : null;
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
        category.selected_form_numbers = selected.form_pool.map(form => formNameToNumber(form.name)).filter(Number.isFinite);
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
defineExpose({ loadCategory });
</script>
<template>
    <div class="setup-grid">
        <section v-if="section === 'all' || section === 'team'" class="panel !border-[#c8ddcb] !bg-[#e7f1e8]">
            <h2> زمین‌های مسابقه</h2>
            <form @submit.prevent="court.post(base + '/courts', { preserveScroll: true, onSuccess: () => court.reset() })">
                <label for="court-name">نام زمین</label><input id="court-name" v-model="court.name" required maxlength="100" placeholder="زمین یک">
                <FormErrors :errors="court.errors"/><button class="button" :disabled="court.processing">افزودن زمین</button>
            </form>
            <div v-for="selectedCourt in tournament.courts" :key="selectedCourt.id" class="court-entry">
                <div class="section-heading court-row"><span class="flex min-w-0 items-center gap-2.5"><span class="h-2.5 w-2.5 shrink-0 rounded-full bg-[#286957] ring-4 ring-[#e2f0e4]" aria-hidden="true"></span><strong class="truncate text-sm text-[#254b3d]">{{ selectedCourt.name }}</strong></span><button v-if="editingCourtId !== selectedCourt.id" type="button" class="button secondary small" :disabled="courtEdit.processing" @click="beginCourtEdit(selectedCourt)">ویرایش نام زمین</button></div>
                <form v-if="editingCourtId === selectedCourt.id" class="court-edit-form" @submit.prevent="saveCourt"><label :for="`edit-court-name-${selectedCourt.id}`">نام جدید زمین</label><input :id="`edit-court-name-${selectedCourt.id}`" v-model="courtEdit.name" required maxlength="100"><FormErrors :errors="courtEdit.errors"/><div class="row-actions"><button class="button" :disabled="courtEdit.processing">ذخیرهٔ نام زمین</button><button type="button" class="button secondary" :disabled="courtEdit.processing" @click="editingCourtId = null">انصراف</button></div></form>
            </div>
        </section>
        <section v-if="section === 'all' || section === 'team'" class="panel !border-[#c8ddcb] !bg-[#e7f1e8]">
            <h2> تیم برگزاری</h2>
            <form @submit.prevent="member.post(base + '/members', { preserveScroll: true, onSuccess: () => member.reset() })" class="fields-two">
                <div><label for="member-name">نام و نام خانوادگی</label><input id="member-name" v-model="member.name" required maxlength="100"></div>
                <DropdownSelect id="member-role" v-model="member.role" label="نقش" :options="memberRoleOptions" />
                <div><label for="member-email">ایمیل ورود</label><input id="member-email" v-model="member.email" type="email" dir="ltr" required autocomplete="off"></div>
                <div><label for="member-password">رمز حساب جدید (۱۲ کاراکتر)</label><div class="relative"><input id="member-password" v-model="member.password" :type="showMemberPassword ? 'text' : 'password'" dir="ltr" minlength="12" autocomplete="new-password" class="!pr-10"><button type="button" class="absolute right-2 top-1/2 grid h-8 w-8 -translate-y-1/2 place-items-center rounded-md text-[#557563] transition hover:bg-[#e8f1e9] hover:text-[#205a48] focus-visible:outline-2 focus-visible:outline-[#78aa8b]" :aria-label="showMemberPassword ? 'مخفی‌کردن رمز عبور' : 'نمایش رمز عبور'" :aria-pressed="showMemberPassword" @click="showMemberPassword = !showMemberPassword"><svg v-if="!showMemberPassword" aria-hidden="true" viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M2.5 12s3.3-6 9.5-6 9.5 6 9.5 6-3.3 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.6"/></svg><svg v-else aria-hidden="true" viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7"><path d="m3 3 18 18M10.6 6.2A10.5 10.5 0 0 1 12 6c6.2 0 9.5 6 9.5 6a15 15 0 0 1-3 3.7M6.2 6.2C3.8 7.8 2.5 12 2.5 12s3.3 6 9.5 6a9.8 9.8 0 0 0 3.1-.5"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg></button></div></div>
                <p class="subtle wide">اگر حساب وجود دارد، فقط نقش اضافه می‌شود و رمز آن تغییر نمی‌کند.</p>
                <FormErrors class="wide" :errors="member.errors"/><button class="button" :disabled="member.processing">افزودن عضو</button>
            </form>
            <div class="member-list"><div v-for="(user,i) in tournament.members" :key="user.id + '-' + i"><span>{{ user.name }} <small dir="ltr">{{ user.email }}</small></span><span class="badge">{{ {judge:'داور',operator:'اپراتور',display:'نمایشگر',manager:'مدیر'}[user.role] || user.role }}</span></div></div>
        </section>
        <section v-if="section === 'all' || section === 'categories'" class="panel wide">

            <div class="section-heading"><DropdownSelect id="edit-category-choice" label="ساخت یا ویرایش رده و قوانین" :model-value="editingCategoryId || ''" :options="categoryChoiceOptions" :disabled="category.processing" @update:model-value="loadCategory($event ? Number($event) : null)" /></div>
            <p v-if="editingCategory" class="subtle">نسخهٔ قوانین: {{ editingCategory.rule_version }}. با ذخیره، نسخهٔ جدید تأیید می‌شود.</p>
            <p v-if="editingCategory && !editingCategory.settings_editable" class="notice">ویرایش تنظیمات فقط قبل از قرعهٔ فرم‌ها و ساخت جدول، برای رده‌های محلی مجاز است.</p>
            <form @submit.prevent="submitCategory" class="tournament-form">
                <fieldset class="wide tournament-form category-fields" :disabled="loadingCategory || Boolean(editingCategory && !editingCategory.settings_editable)">
                <div class="category-form-section wide"><h3>مشخصات رده</h3><div class="category-section-fields">
                <div><label for="category-name">نام رده</label><input id="category-name" v-model="category.name" required maxlength="100" placeholder="انفرادی بزرگسالان"></div>
                <DropdownSelect id="discipline" v-model="category.discipline" label="سبک" :options="disciplineOptions" :disabled="Boolean(editingCategory?.entries.length)" />
                <DropdownSelect id="entry-type" v-model="category.entry_type" label="نوع شرکت" :options="entryTypeOptions" :disabled="Boolean(editingCategory?.entries.length)" />
                <DropdownSelect id="gender" v-model="category.gender" label="جنسیت" :options="genderOptions" />
                </div></div>
                <div class="category-form-section wide"><h3>شرایط سنی</h3><div class="category-section-fields">
                <div><DropdownSelect id="age-category" v-model="category.age_category" label="ردهٔ سنی" :options="ageCategoryOptions" :required="!editingCategory" /><small>مطابق جدول رده‌های سنی ایران، سال {{ ageTableYear.toLocaleString('fa-IR', { useGrouping: false }) }}</small></div>
                <template v-if="editingCategory && !category.age_category"><div><label for="edit-min-age">حداقل سن</label><input id="edit-min-age" v-model.number="category.minimum_age" type="number" min="1" max="100"></div><div><label for="edit-max-age">حداکثر سن</label><input id="edit-max-age" v-model.number="category.maximum_age" type="number" min="1" max="100"></div><div v-if="category.discipline === 'recognized'" class="wide"><label>فرم‌های مجاز</label><input v-for="(_, index) in category.form_names" :key="index" v-model="category.form_names[index]" required maxlength="100" :aria-label="`فرم ${index + 1}`"></div></template>
                <div v-if="selectedAgeCategory"><label>تاریخ تولد مجاز (شمسی)</label><p>{{ selectedAgeCategory.birth_date_label }}</p></div>
                </div></div>
                <div class="category-form-section wide"><h3>فرم‌های مجاز و جدول رده‌های سنی</h3><div class="category-section-fields">
                <p v-if="category.discipline === 'recognized' && selectedAgeCategory" class="subtle wide">فرم‌ها ابتدا طبق جدول ردهٔ سنی انتخاب می‌شوند. می‌توانید فهرست را ویرایش کنید؛ حداقل دو فرم از همین فهرست باید باقی بماند.</p>
                <div v-if="category.discipline === 'recognized' && selectedAgeCategory" class="wide"><div class="chips"><CheckboxField v-for="number in selectedAgeCategory.forms" :id="`allowed-form-${number}`" :key="number" v-model="category.selected_form_numbers" :value="number" :label="formLabel(number)" variant="chip" :disabled="category.selected_form_numbers.includes(number) && category.selected_form_numbers.length <= 2" @update:model-value="updateSelectedForms" /></div></div>
                </div>
            <details class="age-table"><summary>جدول رده‌های سنی و فرم‌های ایران · {{ ageTableYear.toLocaleString('fa-IR', { useGrouping: false }) }}</summary><div class="table-wrap"><table><thead><tr><th>ردیف</th><th>ردهٔ سنی</th><th>تاریخ تولد</th><th>پومسه‌های اجباری</th></tr></thead><tbody><tr v-for="(ageCategory, index) in ageCategories" :key="ageCategory.id" :class="{ 'selected-age-row': category.age_category === ageCategory.id }"><td>{{ (index + 1).toLocaleString('fa-IR') }}</td><td>{{ ageCategory.label }}</td><td>{{ ageCategory.birth_date_label }}</td><td>{{ formatFormsList(ageCategory.forms) }}</td></tr></tbody></table></div></details>
                </div>
                <div class="category-form-section wide"><h3>روش برگزاری و زمان‌بندی قرعه</h3><div class="category-section-fields">
                <div><DropdownSelect id="format" v-model="category.format" label="مدل برگزاری" :options="formatOptions" /><small>تعداد شرکت‌کنندگان سقف ندارد.</small></div>
                <div v-if="category.discipline === 'freestyle'"><label>ترتیب اجرای ابداعی</label><p>{{ category.format === 'knockout' ? 'نوبتی · ابتدا A، سپس B' : 'نوبتی · A، سپس B، سپس C و ...' }}</p><small>هر ورزشکار یا زوج در هر مرحله یک اجرا با موسیقی دارد. اعضای هر زوج با هم یک اجرا انجام می‌دهند.</small></div>
                <div v-else-if="category.format === 'knockout'"><DropdownSelect id="execution-mode" v-model="category.execution_mode" label="شیوهٔ اجرای تک‌حذفی" :options="executionModeOptions" /></div>
                <div v-else><DropdownSelect id="performance-order" v-model="category.performance_order" label="ترتیب اجرای دوره ای" :options="performanceOrderOptions" /></div>
                <DropdownSelect id="judges" v-model.number="category.judge_count" label="تعداد داور" :options="judgeCountOptions" />
                <div v-if="category.format === 'knockout'"><label>تعداد مراحل</label><p>خودکار بر اساس تعداد ورودی‌های حاضر</p><small>پس از تعیین حضور و قبل از قرعه محاسبه می‌شود؛ مثلاً ۸ ورودی = ۳ مرحله و ۹ تا ۱۶ ورودی = ۴ مرحله. هر تیم یا زوج یک ورودی محسوب می‌شود.</small></div>
                <div v-else><label for="stage-count">تعداد مراحل</label><input id="stage-count" v-model.number="category.planned_round_count" type="number" min="1" :max="category.discipline === 'recognized' && !category.allow_form_repetition ? 4 : undefined" required><small>نام مراحل خودکار است: مرحله 1، مرحله 2 و ... .</small></div>
                <template v-if="category.discipline === 'recognized'">
                <DropdownSelect id="form-repetition" v-model="category.allow_form_repetition" label="تکرار بین مراحل" :options="repetitionOptions" />
                <DropdownSelect id="form-draw-timing" v-model="category.form_draw_timing" label="زمان قرعهٔ پومسه‌ها" :options="drawTimingOptions" />
                <TimeField v-if="category.form_draw_timing !== 'before_stage'" id="form-draw-time" v-model="category.form_draw_time" label="ساعت قرعه در منطقهٔ زمانی مسابقه" required />
                </template>
                </div></div>
                <div class="category-form-section wide"><h3>داوری و امتیازدهی</h3><div class="category-section-fields">
                <div><label>ساختار امتیاز</label><ul class="scoring-notes"><li v-if="category.discipline === 'recognized'">دقت از ۴ با افزایش/کاهش ۰٫۱ و ۰٫۳؛ اجرا از ۶ در سه مؤلفهٔ ۲ نمره‌ای.</li><template v-else><li>دقت از ۶ در سه مؤلفهٔ ۲ نمره‌ای؛ اجرا از ۴ با افزایش/کاهش ۰٫۱ و ۰٫۳.</li><li>یک اجرا با موسیقی انجام می‌شود.</li></template></ul></div>
                <div class="rules-summary wide">
                <DropdownSelect id="discard-each-end" v-model.number="category.discard_each_end" label="روش حذف نمره‌های داوران" :options="discardScoreOptions" />
                <ul class="scoring-notes">
                    <template v-if="category.discipline === 'recognized'"><li>برای دقت و اجرا جداگانه اعمال می‌شود.</li><li>داور کسرهای دقت و سه مؤلفهٔ اجرا را ثبت می‌کند.</li><li>نتیجهٔ رقابت مجموع دو فرم است.</li></template>
                    <template v-else><li>دقت و اجرا جداگانه فیلتر و میانگین‌گیری می‌شوند.</li><li>جمع دو میانگین، نتیجهٔ یک اجرای ابداعی از ۱۰ است.</li></template>
                    <li>در تساوی، ابتدا نمرهٔ اجرا مقایسه می‌شود؛ در صورت برابری، نمره‌های حذف‌شده برمی‌گردند و میانگین همهٔ داوران معیار بعدی است.</li>
                    <li>پذیرش هر ورزشکار بر اساس بازهٔ تاریخ تولد جدول ردهٔ انتخاب‌شده است.</li>
                </ul>
                <CheckboxField id="rules-acknowledged" v-model="category.rules_acknowledged" label="این روش محاسبه و شرایط رده را برای این رویداد تأیید می‌کنم." required />
                </div>
                </div></div>
                <FormErrors class="wide" :errors="category.errors"/><div class="row-actions wide"><button class="button" :disabled="category.processing || (!selectedAgeCategory && !editingCategory)">{{ editingCategory ? 'ذخیرهٔ رده و قوانین' : 'ساخت رده' }}</button><button v-if="editingCategory" type="button" class="button secondary" :disabled="category.processing" @click="loadCategory(null)">انصراف از ویرایش</button></div>
</fieldset>
            </form>
        </section>
    </div>
</template>
<style scoped>
.category-fields { display: block; border: 0; padding: 0; margin: 0; min-width: 0; }
.category-form-section { min-width: 0; margin-top: 14px; padding: 16px; border: 1px solid #c8ddcb; border-radius: 12px; background: #e7f1e8; }
.category-form-section h3 { margin: 0 0 13px; color: #285b46; font-size: 14px; font-weight: 800; }
.category-section-fields { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 13px; }
.category-section-fields > .wide { grid-column: 1 / -1; }
@media (max-width: 700px) { .category-section-fields { grid-template-columns: 1fr; } .category-section-fields > .wide { grid-column: 1; } .category-form-section { padding: 13px; } }
.chips { display: flex; flex-wrap: wrap; gap: 8px; }
.age-table { margin-top: 1.5rem; }
.age-table summary { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 10px 12px; border: 1px solid #d8e6d9; border-radius: 9px; background: #f7fbf7; color: #285b46; cursor: pointer; font-weight: 700; list-style: none; transition: border-color 150ms ease, background-color 150ms ease; }
.age-table summary::-webkit-details-marker { display: none; }
.age-table summary::after { content: '⌄'; color: #4f7c5c; font-size: 16px; line-height: 1; transition: transform 150ms ease; }
.age-table summary:hover { border-color: #78a889; background: #edf6ee; }
.age-table summary:focus-visible { outline: 2px solid #78aa8b; outline-offset: 2px; }
.age-table[open] summary::after { transform: rotate(180deg); }
.age-table th { background: #286957; color: #fff; }
.age-table td { white-space: nowrap; }
.scoring-notes { display: grid; gap: 6px; margin: 0; padding: 0 18px 0 0; list-style: disc; color: #526b5b; font-size: 12px; line-height: 1.8; }
.selected-age-row { background: #fef9c3; color: #181818; }
.court-row { min-height: 48px; margin-top: 10px; padding: 7px 10px; border: 1px solid #d8e6d9; border-radius: 11px; background: #fff; }
.court-edit-form { margin-top: 8px; padding: 12px; border: 1px solid #c8ddcb; border-radius: 11px; background: #f7fbf7; }
.member-list > div { margin-top: 8px; padding: 10px 12px !important; border: 1px solid #d8e6d9 !important; border-radius: 11px; background: #fff !important; color: #254b3d; }
.member-list > div > span:first-child { min-width: 0; font-weight: 700; }
.member-list small { display: block; margin-top: 3px; color: #687d6e; font-size: 11px; }
.member-list .badge { flex: none; border: 1px solid #b9d7bf; background: #e3f1e5; color: #205b42; }
</style>
