<script setup>
import { computed } from 'vue';
const props = defineProps({ value: [String, Object, Array, Number, Boolean] });
const labels = {
    name: 'نام', venue: 'محل برگزاری', starts_on: 'تاریخ شروع', ends_on: 'تاریخ پایان', timezone: 'منطقهٔ زمانی',
    first_name: 'نام', last_name: 'نام خانوادگی', birth_date: 'تاریخ تولد', gender: 'جنسیت', club: 'باشگاه',
    display_name: 'نام ورزشکار / تیم', members: 'اعضا', status: 'وضعیت', reason: 'دلیل', revision: 'نسخهٔ نمره',
    version: 'نسخهٔ اجرا', accuracy: 'دقت', presentation: 'اجرا', score: 'نمره', breakdown: 'جزئیات نمره',
    method: 'روش محاسبه', accuracy_penalties: 'کسورات دقت', presentation_penalties: 'کسورات اجرا',
    accuracy_components: 'مؤلفه‌های دقت', presentation_components: 'مؤلفه‌های اجرا',
    started_at: 'شروع', ended_at: 'پایان', approved_at: 'زمان تأیید', published_at: 'زمان انتشار',
    id: 'شناسه', entry_id: 'ورزشکار / تیم', bout_id: 'رقابت', category_id: 'رده', court_id: 'زمین',
    judge_assignment_id: 'صندلی داوری', winner_entry_id: 'برنده', approved_by: 'تأییدکننده', submitted_by: 'ثبت‌کننده',
    minimum_age: 'حداقل سن', maximum_age: 'حداکثر سن', format: 'نوع برگزاری', definition: 'قواعد محاسبه',
    form_sequence: 'فرم‌ها', sequence: 'ترتیب', judge_count: 'تعداد داور', planned_round_count: 'تعداد مراحل',
};
const states = { draft: 'پیش‌نویس', ready: 'آماده', running: 'در حال اجرا', completed: 'پایان‌یافته', archived: 'آرشیو', submitted: 'ثبت‌شده', approved: 'تأییدشده', pending: 'در انتظار', scoring: 'در انتظار نمره', checked_in: 'حاضر', withdrawn: 'انصراف', registered: 'ثبت‌نام‌شده', cancelled: 'لغوشده', male: 'آقایان', female: 'بانوان', knockout: 'تک‌حذفی', round_robin: 'دوره‌ای' };
const parsed = computed(() => {
    if (typeof props.value !== 'string') return props.value;
    try { return JSON.parse(props.value); } catch { return props.value; }
});
const object = computed(() => parsed.value !== null && typeof parsed.value === 'object' && !Array.isArray(parsed.value));
const display = (key, value) => ['accuracy', 'presentation'].includes(key) && typeof value === 'number' ? (value / 100).toFixed(2) : value;
</script>
<template>
    <div>
    <dl v-if="object" class="audit-snapshot">
        <div v-for="(value, key) in parsed" :key="key"><dt>{{ labels[key] || key }}</dt><dd><AuditSnapshot v-if="value !== null && typeof value === 'object'" :value="value" /><span v-else>{{ states[display(key, value)] ?? display(key, value) ?? '—' }}</span></dd></div>
    </dl>
    <div v-else-if="Array.isArray(parsed)" class="audit-snapshot-list"><AuditSnapshot v-for="(value, index) in parsed" :key="index" :value="value" /></div>
    <span v-else>{{ parsed === null || parsed === '' ? '—' : parsed }}</span>
    </div>
</template>
