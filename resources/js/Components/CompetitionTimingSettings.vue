<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import FormErrors from './FormErrors.vue';
const props = defineProps({ category: Object, base: String });
const existing = computed(() => props.category.timing || {});
const freestyle = props.category.discipline === 'freestyle';
const form = useForm({
    enabled: existing.value.maximum_duration_seconds != null,
    minimum_duration_seconds: existing.value.minimum_duration_seconds ?? (freestyle ? 90 : 0),
    maximum_duration_seconds: existing.value.maximum_duration_seconds ?? (freestyle ? 100 : 90),
    minimum_rest_seconds: existing.value.minimum_rest_seconds ?? 30,
    time_penalty_hundredths: existing.value.time_penalty_hundredths ?? 30,
});
</script>
<template>
    <section class="panel">
        <h2>زمان اجرا و استراحت</h2>
        <p class="subtle">زمان از دکمهٔ شروع تا پایان اجرا ثبت می‌شود. استراحت از پایان اجرای قبلی همین ورودی محاسبه می‌شود؛ دریافت و تأیید نمره نیز جزو این فاصله است. پس از اولین شروع، تنظیمات قفل می‌شود.</p>
        <form v-if="category.timing_editable" class="tournament-form" @submit.prevent="form.put(base + '/categories/' + category.id + '/timing', { preserveScroll: true })">
            <label class="check wide"><input v-model="form.enabled" type="checkbox">کنترل محدودهٔ زمان و جریمهٔ خودکار</label>
            <button type="button" class="button secondary wide" @click="form.enabled = true; form.minimum_duration_seconds = freestyle ? 90 : 0; form.maximum_duration_seconds = freestyle ? 100 : 90; form.minimum_rest_seconds = 30; form.time_penalty_hundredths = 30">انتخاب تنظیمات پیشنهادی WT (سپس ذخیره کنید)</button>
            <div><label>حداقل زمان اجرا (ثانیه)</label><input v-model.number="form.minimum_duration_seconds" type="number" min="0" max="3600" :disabled="!form.enabled" required></div>
            <div><label>حداکثر زمان اجرا (ثانیه)</label><input v-model.number="form.maximum_duration_seconds" type="number" min="1" max="3600" :disabled="!form.enabled" required></div>
            <div><label>حداقل استراحت بین اجراهای هر ورودی (ثانیه؛ صفر یعنی غیرفعال)</label><input v-model.number="form.minimum_rest_seconds" type="number" min="0" max="3600" required></div>
            <div><label>جریمهٔ زمان، به صدم امتیاز (۳۰ = ۰٫۳۰ امتیاز)</label><input v-model.number="form.time_penalty_hundredths" type="number" min="0" max="1000" :disabled="!form.enabled" required></div>
            <FormErrors class="wide" :errors="form.errors"/>
            <button class="button" :disabled="form.processing">ذخیرهٔ زمان و استراحت</button>
        </form>
        <p v-else>محدودهٔ اجرا: {{ existing.maximum_duration_seconds == null ? 'غیرفعال' : `${existing.minimum_duration_seconds || 0} تا ${existing.maximum_duration_seconds} ثانیه` }} · استراحت: {{ existing.minimum_rest_seconds }} ثانیه · جریمه: {{ (existing.time_penalty_hundredths / 100).toFixed(2) }} امتیاز</p>
    </section>
</template>
