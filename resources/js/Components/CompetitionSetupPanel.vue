<script setup>
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import FormErrors from './FormErrors.vue';
defineProps({ tournament: Object, base: String });
const court = useForm({ name: '' });
const member = useForm({ name: '', email: '', password: '', role: 'judge' });
const category = useForm({ name: '', gender: 'open', minimum_age: 10, maximum_age: 40, format: 'knockout', judge_count: 5, accuracy_max: 300, discard_each_end: 1, rules_acknowledged: false, form_names: ['', ''] });
watch(() => category.judge_count, (judgeCount) => {
    if (judgeCount === 5) category.discard_each_end = 1;
});
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
            <h2>۳. ردهٔ استاندارد انفرادی</h2>
            <form @submit.prevent="category.post(base + '/categories', { preserveScroll: true, onSuccess: () => category.reset() })" class="tournament-form">
                <div><label for="category-name">نام رده</label><input id="category-name" v-model="category.name" required maxlength="100" placeholder="انفرادی بزرگسالان"></div>
                <div><label for="gender">جنسیت</label><select id="gender" v-model="category.gender"><option value="open">آزاد</option><option value="male">مردان</option><option value="female">زنان</option></select></div>
                <div><label for="format">مدل برگزاری</label><select id="format" v-model="category.format"><option value="knockout">تک‌حذفی · حداکثر ۶۴ نفر</option><option value="round_robin">دورهای · حداکثر ۱۶ نفر</option></select></div>
                <div><label for="min-age">حداقل سن در روز شروع</label><input id="min-age" v-model.number="category.minimum_age" type="number" min="1" max="100" required></div>
                <div><label for="max-age">حداکثر سن در روز شروع</label><input id="max-age" v-model.number="category.maximum_age" type="number" :min="category.minimum_age" max="100" required></div>
                <div><label for="judges">تعداد داور</label><select id="judges" v-model.number="category.judge_count"><option :value="5">۵ داور</option><option :value="7">۷ داور</option></select></div>
                <div><label for="form-one">نام فرم اول</label><input id="form-one" v-model="category.form_names[0]" required maxlength="100"></div>
                <div><label for="form-two">نام فرم دوم</label><input id="form-two" v-model="category.form_names[1]" required maxlength="100"></div>
                <div><label for="accuracy-max">سقف دقت از ۱۰</label><select id="accuracy-max" v-model.number="category.accuracy_max"><option v-for="n in 9" :key="n" :value="n * 100">{{ n }} دقت + {{ 10 - n }} اجرا</option></select></div>
                <div class="rules-summary wide">
                    <label for="discard-each-end">روش حذف نمره‌های داوران</label>
                    <select id="discard-each-end" v-model.number="category.discard_each_end">
                        <option :value="1">یک نمرهٔ بالا و یک نمرهٔ پایین · روش WT</option>
                        <option :value="2" :disabled="category.judge_count !== 7">دو نمرهٔ بالا و دو نمرهٔ پایین · فقط ۷ داور، روش سفارشی</option>
                    </select>
                    <p>برای دقت و اجرا جداگانه اعمال می‌شود. روش دو نمره‌ای فقط با پنل ۷ داوره در دسترس است و میانگین سه نمرهٔ باقی‌مانده را می‌گیرد.</p>
                    <p>نتیجهٔ هر فرم = مجموع میانگین دو مؤلفه؛ نتیجهٔ رقابت = میانگین دو فرم. گردکردن هر مؤلفه تا شش رقم اعشار. تساوی با تصمیم ثبت‌شدهٔ سرداور تعیین می‌شود. سن با تاریخ تولد در روز شروع سنجیده می‌شود.</p>
                    <p>مدیر مسابقه روش محاسبه را پیش از قرعه برای رده تعیین می‌کند. این تنظیمات را با آیین‌نامهٔ رویداد تطبیق دهید. فرم‌ها برای تمام دورهای این رده ثابت‌اند؛ اجرای همزمان و پومسهٔ ابداعی در این نسخه فعال نیست.</p>
                    <label class="check"><input v-model="category.rules_acknowledged" type="checkbox" required>این روش محاسبه و شرایط رده را برای این رویداد تأیید می‌کنم.</label>
                </div>
                <FormErrors class="wide" :errors="category.errors"/><button class="button" :disabled="category.processing">ساخت رده</button>
            </form>
        </section>
    </div>
</template>
