<script setup>
import { useForm } from '@inertiajs/vue3';
import FormErrors from './common/FormErrors.vue';
const props = defineProps({ category: Object, tournament: Object, base: String });
const entry = useForm({ first_name: '', last_name: '', birth_date: '', gender: props.category.gender === 'female' ? 'female' : 'male', club: '' });
const status = useForm({ status: '' });
const schedule = useForm({ court_id: props.tournament.courts[0]?.id || '', judge_ids: [] });
const setStatus = (id,value) => { status.status = value; status.patch(props.base + '/entries/' + id + '/status', { preserveScroll: true }); };
const scheduleRound = () => schedule.post(props.base + '/categories/' + props.category.id + '/rounds', { preserveScroll: true });
</script>
<template>
    <div>
        <section class="panel" v-if="!category.rounds.length">
            <h2>ثبت ورزشکار</h2><p class="subtle">سن در روز شروع مسابقه محاسبه می‌شود. بعد از ساخت قرعه، فهرست رده قفل خواهد شد.</p>
            <form @submit.prevent="entry.post(base + '/categories/' + category.id + '/entries', { preserveScroll: true, onSuccess: () => entry.reset() })" class="tournament-form">
                <div><label :for="'first-'+category.id">نام</label><input :id="'first-'+category.id" v-model="entry.first_name" required maxlength="100"></div>
                <div><label :for="'last-'+category.id">نام خانوادگی</label><input :id="'last-'+category.id" v-model="entry.last_name" required maxlength="100"></div>
                <div><label :for="'birth-'+category.id">تاریخ تولد (میلادی)</label><input :id="'birth-'+category.id" v-model="entry.birth_date" type="date" required :max="tournament.starts_on" dir="ltr"></div>
                <div><label :for="'sex-'+category.id">جنسیت</label><select :id="'sex-'+category.id" v-model="entry.gender"><option value="male">مرد</option><option value="female">زن</option></select></div>
                <div><label :for="'club-'+category.id">باشگاه</label><input :id="'club-'+category.id" v-model="entry.club" maxlength="100"></div>
                <div class="align-end"><button class="button" :disabled="entry.processing">ثبت ورزشکار</button></div>
                <FormErrors class="wide" :errors="entry.errors"/>
            </form>
        </section>
        <section class="panel">
            <div class="section-heading"><h2>فهرست و پذیرش ورزشکاران</h2><span class="badge">{{ category.entries.filter(e => e.status === 'checked_in').length }} نفر حاضر</span></div>
            <FormErrors :errors="status.errors"/>
            <div class="table-wrap"><table><thead><tr><th>ورزشکار</th><th>باشگاه</th><th>وضعیت</th><th v-if="!category.rounds.length">پذیرش</th></tr></thead><tbody><tr v-for="athlete in category.entries" :key="athlete.id"><td>{{ athlete.name }}</td><td>{{ athlete.club || '—' }}</td><td>{{ {registered:'در انتظار حضور',checked_in:'حاضر',withdrawn:'منصرف',disqualified:'حذف‌شده'}[athlete.status] }}</td><td v-if="!category.rounds.length" class="row-actions"><button v-if="athlete.status !== 'checked_in'" class="button small" :disabled="status.processing" @click="setStatus(athlete.id,'checked_in')">حاضر</button><button v-if="athlete.status !== 'withdrawn'" class="button secondary small" :disabled="status.processing" @click="setStatus(athlete.id,'withdrawn')">انصراف</button></td></tr></tbody></table></div>
            <p v-if="!category.entries.length" class="subtle">هنوز ورزشکاری ثبت نشده است.</p>
        </section>
        <section class="panel" v-if="!category.completed && (!category.rounds.length || category.format === 'knockout')">
            <h2>{{ category.rounds.length ? 'ساخت دور بعد' : 'ساخت قرعه و برنامهٔ اجرا' }}</h2>
            <p class="subtle">{{ category.rounds.length ? 'پس از قطعی‌شدن برندهٔ تمام رقابت‌های دور قبل، دور بعد ساخته می‌شود.' : 'تنها ورزشکاران حاضر وارد جدول می‌شوند. قرعه فقط یک بار ساخته می‌شود.' }}</p>
            <form @submit.prevent="scheduleRound">
                <label :for="'schedule-court-'+category.id">زمین</label><select :id="'schedule-court-'+category.id" v-model.number="schedule.court_id" required><option value="" disabled>انتخاب زمین</option><option v-for="court in tournament.courts" :key="court.id" :value="court.id">{{ court.name }}</option></select>
                <p class="field-label">پنل {{ category.judge_count }} نفره · {{ schedule.judge_ids.length }} انتخاب شده</p>
                <div class="judge-options"><label v-for="judge in tournament.members.filter(m => m.role === 'judge' && m.is_active)" :key="judge.id" class="check"><input v-model="schedule.judge_ids" type="checkbox" :value="judge.id">{{ judge.name }}</label></div>
                <p class="subtle">ترتیب انتخاب، شمارهٔ صندلی داور را مشخص می‌کند.</p>
                <FormErrors :errors="schedule.errors"/><button class="button" :disabled="schedule.processing || schedule.judge_ids.length !== category.judge_count">تأیید و ساخت جدول</button>
            </form>
        </section>
    </div>
</template>
