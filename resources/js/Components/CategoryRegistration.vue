<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import FormErrors from './FormErrors.vue';
const props = defineProps({ category: Object, tournament: Object, base: String });
const blankMember = () => ({ first_name: '', last_name: '', birth_date: '', gender: props.category.gender === 'female' ? 'female' : 'male', club: '' });
const memberCount = computed(() => props.category.entry_type === 'team' ? 3 : props.category.entry_type === 'pair' ? 2 : 1);
const entry = useForm({ first_name: '', last_name: '', birth_date: '', gender: props.category.gender === 'female' ? 'female' : 'male', club: '', members: [blankMember(), blankMember(), blankMember()], music: null });
const submitEntry = () => entry.transform(data => {
    const payload = { ...data };
    if (memberCount.value === 1) delete payload.members;
    else {
        delete payload.first_name; delete payload.last_name; delete payload.birth_date; delete payload.gender; delete payload.club;
        payload.members = data.members.slice(0, memberCount.value);
    }
    if (props.category.discipline !== 'freestyle') delete payload.music;
    return payload;
}).post(props.base + '/categories/' + props.category.id + '/entries', { preserveScroll: true, forceFormData: props.category.discipline === 'freestyle', onSuccess: () => entry.reset() });
const status = useForm({ status: '' });
const schedule = useForm({ court_id: props.tournament.courts[0]?.id || '', judge_ids: [] });
const draw = useForm({ round_id: null });
const scheduledRounds = computed(() => props.category.rounds.filter(round => round.scheduled));
const nextStage = computed(() => props.category.rounds.find(round => !round.scheduled));
const canSchedule = computed(() => !props.category.completed && !props.category.management_source && (props.category.planned_round_count ? Boolean(nextStage.value) : !scheduledRounds.value.length || props.category.format === 'knockout'));
const previousComplete = computed(() => !scheduledRounds.value.length || scheduledRounds.value.at(-1).status === 'completed');
const drawAvailable = computed(() => Date.now() >= Date.parse(props.category.draw_available_at));
const drawForms = roundId => { draw.round_id = roundId; draw.post(props.base + '/categories/' + props.category.id + '/forms/draw', { preserveScroll: true }); };
const setStatus = (id,value) => { status.status = value; status.patch(props.base + '/entries/' + id + '/status', { preserveScroll: true }); };
const scheduleRound = () => schedule.post(props.base + '/categories/' + props.category.id + '/rounds', { preserveScroll: true });
</script>
<template>
    <div>
        <section v-if="category.management_source" class="panel"><p>اطلاعات از {{ category.management_source }} دریافت شده است · نسخهٔ {{ category.management_version }}</p><p class="subtle">نسخهٔ محلی ذخیره شده است؛ اجرای مرحلهٔ آماده به اتصال سیستم مدیریت وابسته نیست.</p></section>
        <section class="panel" v-if="!category.registration_locked && !category.management_source">
            <h2>ثبت {{ category.entry_type === 'team' ? 'تیم' : category.entry_type === 'pair' ? 'زوج' : 'ورزشکار' }}</h2><p class="subtle">سن هر عضو در روز شروع مسابقه محاسبه می‌شود. بعد از ساخت قرعه، فهرست رده قفل خواهد شد.</p>
            <form @submit.prevent="submitEntry" class="tournament-form">
                <template v-if="memberCount === 1">
                <div><label :for="'first-'+category.id">نام</label><input :id="'first-'+category.id" v-model="entry.first_name" required maxlength="100"></div>
                <div><label :for="'last-'+category.id">نام خانوادگی</label><input :id="'last-'+category.id" v-model="entry.last_name" required maxlength="100"></div>
                <div><label :for="'birth-'+category.id">تاریخ تولد (میلادی)</label><input :id="'birth-'+category.id" v-model="entry.birth_date" type="date" required :max="tournament.starts_on" dir="ltr"></div>
                <div><label :for="'sex-'+category.id">جنسیت</label><select :id="'sex-'+category.id" v-model="entry.gender"><option value="female">بانوان</option><option value="male">آقایان</option></select></div>
                <div><label :for="'club-'+category.id">باشگاه</label><input :id="'club-'+category.id" v-model="entry.club" maxlength="100"></div>
                </template>
                <div v-for="(member,index) in entry.members.slice(0, memberCount)" v-else :key="index" class="wide fields-two">
                    <h3 class="wide">عضو {{ index + 1 }}</h3>
                    <div><label :for="`member-first-${category.id}-${index}`">نام</label><input :id="`member-first-${category.id}-${index}`" v-model="member.first_name" required maxlength="100"></div>
                    <div><label :for="`member-last-${category.id}-${index}`">نام خانوادگی</label><input :id="`member-last-${category.id}-${index}`" v-model="member.last_name" required maxlength="100"></div>
                    <div><label :for="`member-birth-${category.id}-${index}`">تاریخ تولد (میلادی)</label><input :id="`member-birth-${category.id}-${index}`" v-model="member.birth_date" type="date" required :max="tournament.starts_on" dir="ltr"></div>
                    <div><label :for="`member-gender-${category.id}-${index}`">جنسیت</label><select :id="`member-gender-${category.id}-${index}`" v-model="member.gender"><option value="female">بانوان</option><option value="male">آقایان</option></select></div>
                    <div><label :for="`member-club-${category.id}-${index}`">باشگاه</label><input :id="`member-club-${category.id}-${index}`" v-model="member.club" maxlength="100"></div>
                </div>
                <div v-if="category.discipline === 'freestyle'"><label :for="`music-${category.id}`">فایل موسیقی (MP3، WAV یا M4A، حداکثر ۲۰ مگابایت)</label><input :id="`music-${category.id}`" type="file" accept=".mp3,.wav,.m4a,audio/*" required @change="entry.music = $event.target.files[0] || null"></div>
                <div class="align-end"><button class="button" :disabled="entry.processing">ثبت ورزشکار</button></div>
                <FormErrors class="wide" :errors="entry.errors"/>
            </form>
        </section>
        <section class="panel">
            <div class="section-heading"><h2>فهرست و پذیرش ورودی‌ها</h2><span class="badge">{{ category.entries.filter(e => e.status === 'checked_in').length }} ورودی حاضر</span></div>
            <FormErrors :errors="status.errors"/>
            <div class="table-wrap"><table><thead><tr><th>ورودی</th><th>باشگاه</th><th>وضعیت</th><th v-if="!category.registration_locked && !category.management_source">پذیرش</th></tr></thead><tbody><tr v-for="athlete in category.entries" :key="athlete.id"><td>{{ athlete.name }}</td><td>{{ athlete.club || '—' }}</td><td>{{ {registered:'در انتظار حضور',checked_in:'حاضر',withdrawn:'منصرف',disqualified:'حذف‌شده'}[athlete.status] }}</td><td v-if="!category.registration_locked && !category.management_source" class="row-actions"><button v-if="athlete.status !== 'checked_in'" class="button small" :disabled="status.processing" @click="setStatus(athlete.id,'checked_in')">حاضر</button><button v-if="athlete.status !== 'withdrawn'" class="button secondary small" :disabled="status.processing" @click="setStatus(athlete.id,'withdrawn')">انصراف</button></td></tr></tbody></table></div>
            <p v-if="!category.entries.length" class="subtle">هنوز ورزشکاری ثبت نشده است.</p>
        </section>
        <section v-if="category.planned_round_count && category.discipline === 'recognized'" class="panel">
            <h2>قرعهٔ پومسه‌های مراحل</h2>
            <p>{{ { day_before: 'روز قبل مسابقه', morning: 'صبح مسابقه', before_stage: 'قبل از شروع هر مرحله' }[category.form_draw_timing] }} · {{ category.allow_form_repetition ? 'تکرار بین مراحل مجاز است' : 'بدون تکرار بین مراحل' }}</p>
            <p class="subtle">زمان مجاز: {{ new Date(category.draw_available_at).toLocaleString('fa-IR', { timeZone: tournament.timezone || 'Asia/Tehran' }) }}</p>
            <div class="chips"><span v-for="form in category.form_pool" :key="form.id" class="badge">{{ form.name }}</span></div>
            <div v-for="round in category.rounds" :key="round.id" class="section-heading"><span>{{ round.name }} · {{ round.forms_ready ? round.form_names.join('، ') : 'در انتظار قرعهٔ پومسه' }}</span><button v-if="!category.management_source && category.form_draw_timing === 'before_stage' && !round.forms_ready" type="button" class="button small" :disabled="draw.processing || !drawAvailable || !round.previous_completed" @click="drawForms(round.id)">قرعهٔ این مرحله</button></div>
            <button v-if="!category.management_source && category.form_draw_timing !== 'before_stage' && category.rounds.some(round => !round.forms_ready)" type="button" class="button" :disabled="draw.processing || !drawAvailable" @click="drawForms(null)">قرعهٔ فرم‌های تمام مراحل</button>
            <FormErrors :errors="draw.errors"/>
        </section>
        <section class="panel" v-if="canSchedule">
            <h2>{{ nextStage ? `ساخت جدول ${nextStage.name}` : scheduledRounds.length ? 'ساخت دور بعد' : 'ساخت قرعه و برنامهٔ اجرا' }}</h2>
            <p class="subtle">{{ scheduledRounds.length ? 'پس از پایان مرحلهٔ قبل، برنامهٔ مرحلهٔ بعد ساخته می‌شود.' : 'ورودی‌های حاضر با قرعهٔ تصادفی در جدول قرار می‌گیرند.' }}</p>
            <form @submit.prevent="scheduleRound">
                <label :for="'schedule-court-'+category.id">زمین</label><select :id="'schedule-court-'+category.id" v-model.number="schedule.court_id" required><option value="" disabled>انتخاب زمین</option><option v-for="court in tournament.courts" :key="court.id" :value="court.id">{{ court.name }}</option></select>
                <p class="field-label">پنل {{ category.judge_count }} نفره · {{ schedule.judge_ids.length }} انتخاب شده</p>
                <div class="judge-options"><label v-for="judge in tournament.members.filter(m => m.role === 'judge' && m.is_active)" :key="judge.id" class="check"><input v-model="schedule.judge_ids" type="checkbox" :value="judge.id">{{ judge.name }}</label></div>
                <p class="subtle">ترتیب انتخاب، شمارهٔ صندلی داور را مشخص می‌کند.</p>
                <FormErrors :errors="schedule.errors"/><button class="button" :disabled="schedule.processing || !previousComplete || schedule.judge_ids.length !== category.judge_count">تأیید و ساخت جدول</button>
            </form>
        </section>
    </div>
</template>
