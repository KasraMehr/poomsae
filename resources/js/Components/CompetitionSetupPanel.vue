<script setup>
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import FormErrors from './FormErrors.vue';
const props = defineProps({ tournament: Object, base: String });
const court = useForm({ name: '' });
const member = useForm({ name: '', email: '', password: '', role: 'judge' });
const category = useForm({ name: '', discipline: 'recognized', entry_type: 'individual', gender: 'open', minimum_age: 10, maximum_age: 40, format: 'knockout', execution_mode: 'alternating', performance_order: 'consecutive', judge_count: 5, accuracy_max: 400, discard_each_end: 1, rules_acknowledged: false, planned_round_count: 1, stage_names: ['مرحله ۱'], allow_form_repetition: true, form_draw_timing: 'morning', form_draw_time: '08:00', draw_method: 'random', form_names: Array(8).fill('') });
watch(() => category.planned_round_count, count => {
    category.stage_names = Array.from({ length: Math.max(1, Math.min(6, Number(count) || 1)) }, (_, index) => category.stage_names[index] || `مرحله ${index + 1}`);
});
watch(() => category.judge_count, (judgeCount) => {
    if (judgeCount === 5) category.discard_each_end = 1;
});
watch(() => category.discipline, discipline => {
    category.entry_type = 'individual';
    category.accuracy_max = discipline === 'freestyle' ? 600 : 400;
});
const submitCategory = () => category.transform(data => {
    const payload = { ...data };
    if (data.discipline === 'freestyle') delete payload.form_names;
    return payload;
}).post(props.base + '/categories', { preserveScroll: true, onSuccess: () => category.reset() });
</script>
<template>
    <div class="setup-grid">
        <section class="panel">
            <h2>۱. زمین‌های مسابقه</h2>
            <div class="chips"><span class="badge" v-for="court in tournament.courts" :key="court.id">{{ court.name }}</span></div>
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
            <form @submit.prevent="submitCategory" class="tournament-form">
                <div><label for="category-name">نام رده</label><input id="category-name" v-model="category.name" required maxlength="100" placeholder="انفرادی بزرگسالان"></div>
                <div><label for="discipline">سبک</label><select id="discipline" v-model="category.discipline"><option value="recognized">استاندارد</option><option value="freestyle">ابداعی</option></select></div>
                <div><label for="entry-type">نوع شرکت</label><select id="entry-type" v-model="category.entry_type"><option value="individual">انفرادی</option><option v-if="category.discipline === 'recognized'" value="team">تیمی سه‌نفره</option><option v-else value="pair">زوجی دونفره</option></select></div>
                <div><label for="gender">جنسیت</label><select id="gender" v-model="category.gender"><option value="open">آزاد</option><option value="male">مردان</option><option value="female">زنان</option><option v-if="category.entry_type !== 'individual'" value="mixed">مختلط</option></select></div>
                <div><label for="format">مدل برگزاری</label><select id="format" v-model="category.format"><option value="knockout">تک‌حذفی · حداکثر ۶۴ نفر</option><option value="round_robin">دورهای · حداکثر ۱۶ نفر</option></select></div>
                <div v-if="category.format === 'knockout'"><label for="execution-mode">شیوهٔ اجرای تک‌حذفی</label><select id="execution-mode" v-model="category.execution_mode"><option value="alternating">سینگل · A۱، B۱، A۲، B۲</option><option value="simultaneous">دوبل · دو ورزشکار همزمان در هر فرم</option></select></div>
                <div v-else-if="category.discipline === 'recognized'"><label for="performance-order">ترتیب اجرای دورهای</label><select id="performance-order" v-model="category.performance_order"><option value="consecutive">دو فرم هر ورودی پشت‌سرهم</option><option value="phased">فرم اول همه، سپس فرم دوم همه</option></select></div>
                <div><label for="min-age">حداقل سن در روز شروع</label><input id="min-age" v-model.number="category.minimum_age" type="number" min="1" max="100" required></div>
                <div><label for="max-age">حداکثر سن در روز شروع</label><input id="max-age" v-model.number="category.maximum_age" type="number" :min="category.minimum_age" max="100" required></div>
                <div><label for="judges">تعداد داور</label><select id="judges" v-model.number="category.judge_count"><option :value="5">۵ داور</option><option :value="7">۷ داور</option></select></div>
                <div><label for="stage-count">تعداد مراحل</label><input id="stage-count" v-model.number="category.planned_round_count" type="number" min="1" :max="category.discipline === 'recognized' && !category.allow_form_repetition ? 4 : 6" required><small>تک‌حذفی: ۲ ورودی = ۱ مرحله، ۴ = ۲، ۸ = ۳، ۱۶ = ۴، ۳۲ = ۵، ۶۴ = ۶.</small></div>
                <div v-for="(_, index) in category.stage_names" :key="`stage-${index}`"><label :for="`stage-name-${index}`">نام مرحلهٔ {{ index + 1 }}</label><input :id="`stage-name-${index}`" v-model="category.stage_names[index]" required maxlength="100"></div>
                <template v-if="category.discipline === 'recognized'">
                    <div class="wide"><h3>هشت پومسهٔ مجاز ردهٔ سنی</h3><p class="subtle">در هر مرحله دو فرم متفاوت از این فهرست قرعه‌کشی می‌شود.</p></div>
                    <div v-for="(_, index) in category.form_names" :key="`form-${index}`"><label :for="`pool-form-${index}`">پومسهٔ {{ index + 1 }}</label><input :id="`pool-form-${index}`" v-model="category.form_names[index]" required maxlength="100"></div>
                    <div><label for="form-repetition">تکرار بین مراحل</label><select id="form-repetition" v-model="category.allow_form_repetition"><option :value="true">مجاز</option><option :value="false">غیرتکراری · حداکثر چهار مرحله</option></select></div>
                    <div><label for="form-draw-timing">زمان قرعهٔ پومسه‌ها</label><select id="form-draw-timing" v-model="category.form_draw_timing"><option value="day_before">روز قبل مسابقه · تمام مراحل</option><option value="morning">صبح مسابقه · تمام مراحل</option><option value="before_stage">قبل از شروع هر مرحله</option></select></div>
                    <div v-if="category.form_draw_timing !== 'before_stage'"><label for="form-draw-time">ساعت قرعه در منطقهٔ زمانی مسابقه</label><input id="form-draw-time" v-model="category.form_draw_time" type="time" required></div>
                </template>
                <div><label>ساختار امتیاز</label><p v-if="category.discipline === 'recognized'">دقت از ۴ با افزایش/کاهش ۰٫۱ و ۰٫۳؛ اجرا از ۶ در سه مؤلفهٔ ۲ نمره‌ای.</p><p v-else>دقت از ۶ در سه مؤلفهٔ ۲ نمره‌ای؛ اجرا از ۴ با افزایش/کاهش ۰٫۱ و ۰٫۳. یک اجرا با موسیقی.</p></div>
                <div class="rules-summary wide">
                    <label for="discard-each-end">روش حذف نمره‌های داوران</label>
                    <select id="discard-each-end" v-model.number="category.discard_each_end">
                        <option :value="1">یک نمرهٔ بالا و یک نمرهٔ پایین · روش WT</option>
                        <option :value="2" :disabled="category.judge_count !== 7">دو نمرهٔ بالا و دو نمرهٔ پایین · فقط ۷ داور، روش سفارشی</option>
                    </select>
                    <p v-if="category.discipline === 'recognized'">برای دقت و اجرا جداگانه اعمال می‌شود. داور کسرهای دقت و سه مؤلفهٔ اجرا را ثبت می‌کند؛ نتیجهٔ رقابت مجموع دو فرم است.</p>
                    <p v-else>دقت و اجرا جداگانه فیلتر و میانگین‌گیری می‌شوند؛ جمع دو میانگین، نتیجهٔ یک اجرای ابداعی از ۱۰ است.</p>
                    <p>در تساوی، نمره‌های حذف‌شده برمی‌گردند و میانگین همهٔ داوران معیار دوم است. سن در روز شروع مسابقه سنجیده می‌شود.</p>
                    <label class="check"><input v-model="category.rules_acknowledged" type="checkbox" required>این روش محاسبه و شرایط رده را برای این رویداد تأیید می‌کنم.</label>
                </div>
                <FormErrors class="wide" :errors="category.errors"/><button class="button" :disabled="category.processing">ساخت رده</button>
            </form>
        </section>
    </div>
</template>
