<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import FormErrors from '../../Shared/Components/FormErrors.vue';
import CheckboxField from '../../Shared/Components/CheckboxField.vue';
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
    <!-- Keep tournament settings styled with Tailwind utilities. -->
    <section class="mb-6 rounded-xl border border-[#e2e9df] bg-white p-5 min-[761px]:p-[27px] [&_h2]:mb-4 [&_h2]:text-lg [&_h2]:font-bold [&_h2]:text-[#1e352e] [&_label]:mb-1 [&_label]:block [&_label]:text-xs [&_label]:font-semibold [&_label]:text-[#42664f] [&_input]:w-full [&_input]:rounded-lg [&_input]:border [&_input]:border-[#cfd9cc] [&_input]:bg-white [&_input]:px-3 [&_input]:py-2.5">
        <h2>زمان اجرا و استراحت</h2>
        <p class="my-3 text-xs leading-6 text-[#8b988a]">زمان از دکمهٔ شروع تا پایان اجرا ثبت می‌شود. استراحت از پایان اجرای قبلی همین ورودی محاسبه می‌شود؛ دریافت و تأیید نمره نیز جزو این فاصله است. پس از اولین شروع، تنظیمات قفل می‌شود.</p>
        <form v-if="category.timing_editable" class="mt-[18px] grid grid-cols-1 gap-[18px] min-[761px]:grid-cols-3" @submit.prevent="form.put(base + '/categories/' + category.id + '/timing', { preserveScroll: true })">
            <CheckboxField id="timing-enabled" v-model="form.enabled" label="کنترل محدودهٔ زمان و جریمهٔ خودکار" class="col-span-full" />
            <button type="button" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-transparent bg-[#286957] px-5 py-2.5 text-sm font-semibold text-white disabled:cursor-wait disabled:opacity-55 !bg-[#e8eee7] !text-[#42664f] col-span-full" @click="form.enabled = true; form.minimum_duration_seconds = freestyle ? 90 : 0; form.maximum_duration_seconds = freestyle ? 100 : 90; form.minimum_rest_seconds = 30; form.time_penalty_hundredths = 30">انتخاب تنظیمات پیشنهادی WT (سپس ذخیره کنید)</button>
            <div><label>حداقل زمان اجرا (ثانیه)</label><input v-model.number="form.minimum_duration_seconds" type="number" min="0" max="3600" :disabled="!form.enabled" required></div>
            <div><label>حداکثر زمان اجرا (ثانیه)</label><input v-model.number="form.maximum_duration_seconds" type="number" min="1" max="3600" :disabled="!form.enabled" required></div>
            <div><label>حداقل استراحت بین اجراهای هر ورودی (ثانیه؛ صفر یعنی غیرفعال)</label><input v-model.number="form.minimum_rest_seconds" type="number" min="0" max="3600" required></div>
            <div><label>جریمهٔ زمان، به صدم امتیاز (۳۰ = ۰٫۳۰ امتیاز)</label><input v-model.number="form.time_penalty_hundredths" type="number" min="0" max="1000" :disabled="!form.enabled" required></div>
            <FormErrors class="col-span-full" :errors="form.errors"/>
            <button class="inline-flex min-h-11 items-center justify-center rounded-lg border border-transparent bg-[#286957] px-5 py-2.5 text-sm font-semibold text-white disabled:cursor-wait disabled:opacity-55" :disabled="form.processing">ذخیرهٔ زمان و استراحت</button>
        </form>
        <p v-else>محدودهٔ اجرا: {{ existing.maximum_duration_seconds == null ? 'غیرفعال' : `${existing.minimum_duration_seconds || 0} تا ${existing.maximum_duration_seconds} ثانیه` }} · استراحت: {{ existing.minimum_rest_seconds }} ثانیه · جریمه: {{ (existing.time_penalty_hundredths / 100).toFixed(2) }} امتیاز</p>
    </section>
</template>
