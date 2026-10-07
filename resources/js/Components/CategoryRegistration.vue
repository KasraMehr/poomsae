<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import FormErrors from './FormErrors.vue';
import EntryDetailsEditor from './EntryDetailsEditor.vue';
const props = defineProps({ category: Object, tournament: Object, base: String });
const blankMember = () => ({ first_name: '', last_name: '', birth_date: '', gender: props.category.gender === 'female' ? 'female' : 'male', club: '' });
const memberCount = computed(() => props.category.entry_type === 'team' ? 3 : props.category.entry_type === 'pair' ? 2 : 1);
const ageCategory = computed(() => props.category.rules?.age_category);
const maximumBirthDate = computed(() => ageCategory.value?.birth_date_max && ageCategory.value.birth_date_max < props.tournament.starts_on ? ageCategory.value.birth_date_max : props.tournament.starts_on);
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
const schedule = useForm({ courts: props.tournament.courts.length ? [{ court_id: props.tournament.courts[0].id, judge_ids: [] }] : [] });
const availableJudges = computed(() => props.tournament.members.filter(member => member.role === 'judge' && member.is_active));
const selectedCourtIds = computed(() => schedule.courts.map(panel => panel.court_id));
const courtPanelsReady = computed(() => {
    const judgeIds = schedule.courts.flatMap(panel => panel.judge_ids);
    return schedule.courts.length >= 1 && schedule.courts.length <= 4 && schedule.courts.every(panel => panel.judge_ids.length === props.category.judge_count) && new Set(judgeIds).size === judgeIds.length;
});
const toggleCourt = courtId => {
    if (selectedCourtIds.value.includes(courtId)) schedule.courts = schedule.courts.filter(panel => panel.court_id !== courtId);
    else if (schedule.courts.length < 4) schedule.courts.push({ court_id: courtId, judge_ids: [] });
};
const judgeAssignedElsewhere = (judgeId, courtId) => schedule.courts.some(panel => panel.court_id !== courtId && panel.judge_ids.includes(judgeId));
const courtName = courtId => props.tournament.courts.find(court => court.id === courtId)?.name || 'زمین';
const draw = useForm({ round_id: null });
const manualForms = useForm({ round_id: null, form_ids: [] });
const scheduledRounds = computed(() => props.category.rounds.filter(round => round.scheduled));
const nextStage = computed(() => props.category.rounds.find(round => !round.scheduled));
const canSchedule = computed(() => !props.category.completed && !props.category.management_source && (props.category.planned_round_count ? Boolean(nextStage.value) : !scheduledRounds.value.length || props.category.format === 'knockout'));
const previousComplete = computed(() => !scheduledRounds.value.length || scheduledRounds.value.at(-1).status === 'completed');
const drawAvailable = computed(() => Date.now() >= Date.parse(props.category.draw_available_at));
const attendanceReady = computed(() => props.category.entries.filter(entry => entry.status === 'checked_in').length >= 2 && !props.category.entries.some(entry => entry.status === 'registered'));
const automaticStagePlanReady = computed(() => !props.category.round_count_automatic || attendanceReady.value);
const drawForms = roundId => { draw.round_id = roundId; draw.post(props.base + '/categories/' + props.category.id + '/forms/draw', { preserveScroll: true }); };
const selectedManualForms = round => round.form_ids || [];
const toggleManualForm = (round, formId) => {
    const selected = selectedManualForms(round);
    if (selected.includes(formId)) {
        if (selected.length <= 2) return;
        round.form_ids = selected.filter(id => id !== formId);
    } else if (selected.length < 2) {
        round.form_ids = [...selected, formId];
    }
};
const assignForms = (roundId, formIds) => { manualForms.round_id = roundId; manualForms.form_ids = formIds; manualForms.post(props.base + '/categories/' + props.category.id + '/forms/assign', { preserveScroll: true }); };
const setStatus = (id,value) => { status.status = value; status.patch(props.base + '/entries/' + id + '/status', { preserveScroll: true }); };
const scheduleRound = () => schedule.post(props.base + '/categories/' + props.category.id + '/rounds', { preserveScroll: true });
</script>
<template>
    <div>
        <section v-if="category.discipline === 'freestyle'" class="panel"><h2>ترتیب اجرای ابداعی</h2><p>{{ category.format === 'knockout' ? 'هر رقابت: ابتدا A، سپس B؛ یک اجرا با موسیقی برای هر ورودی.' : 'ورودی‌ها به‌ترتیب A، B، C و ... اجرا می‌کنند؛ یک اجرا با موسیقی برای هر ورودی در هر مرحله.' }}</p><p v-if="category.entry_type === 'pair'" class="subtle">اعضای هر زوج با هم یک اجرا انجام می‌دهند؛ هر زوج یک ورودی مسابقه است.</p></section>
        <section v-if="category.management_source" class="panel"><p>اطلاعات از {{ category.management_source }} دریافت شده است · نسخهٔ {{ category.management_version }}</p><p class="subtle">نسخهٔ محلی ذخیره شده است؛ اجرای مرحلهٔ آماده به اتصال سیستم مدیریت وابسته نیست.</p></section>
        <section class="panel" v-if="!category.registration_locked && !category.management_source">
            <h2>ثبت {{ category.entry_type === 'team' ? 'تیم' : category.entry_type === 'pair' ? 'زوج' : 'ورزشکار' }}</h2><p v-if="ageCategory" class="notice">{{ ageCategory.label }} · {{ ageCategory.birth_date_label }}</p><p class="subtle">{{ ageCategory ? 'تاریخ تولد هر عضو باید در بازهٔ ردهٔ انتخاب‌شده باشد.' : 'سن هر عضو در روز شروع مسابقه محاسبه می‌شود.' }} بعد از ساخت قرعه، فهرست رده قفل خواهد شد.</p>
            <form @submit.prevent="submitEntry" class="tournament-form">
                <template v-if="memberCount === 1">
                <div><label :for="'first-'+category.id">نام</label><input :id="'first-'+category.id" v-model="entry.first_name" required maxlength="100"></div>
                <div><label :for="'last-'+category.id">نام خانوادگی</label><input :id="'last-'+category.id" v-model="entry.last_name" required maxlength="100"></div>
                <div><label :for="'birth-'+category.id">تاریخ تولد (میلادی)</label><input :id="'birth-'+category.id" v-model="entry.birth_date" type="date" required :min="ageCategory?.birth_date_min || undefined" :max="maximumBirthDate" dir="ltr"></div>
                <div><label :for="'sex-'+category.id">جنسیت</label><select :id="'sex-'+category.id" v-model="entry.gender"><option value="female">بانوان</option><option value="male">آقایان</option></select></div>
                <div><label :for="'club-'+category.id">باشگاه</label><input :id="'club-'+category.id" v-model="entry.club" maxlength="100"></div>
                </template>
                <div v-for="(member,index) in entry.members.slice(0, memberCount)" v-else :key="index" class="wide fields-two">
                    <h3 class="wide">عضو {{ index + 1 }}</h3>
                    <div><label :for="`member-first-${category.id}-${index}`">نام</label><input :id="`member-first-${category.id}-${index}`" v-model="member.first_name" required maxlength="100"></div>
                    <div><label :for="`member-last-${category.id}-${index}`">نام خانوادگی</label><input :id="`member-last-${category.id}-${index}`" v-model="member.last_name" required maxlength="100"></div>
                    <div><label :for="`member-birth-${category.id}-${index}`">تاریخ تولد (میلادی)</label><input :id="`member-birth-${category.id}-${index}`" v-model="member.birth_date" type="date" required :min="ageCategory?.birth_date_min || undefined" :max="maximumBirthDate" dir="ltr"></div>
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
            <p v-if="category.round_count_automatic" class="notice">{{ category.required_round_count ? `تعداد مراحل تک‌حذفی: ${category.required_round_count.toLocaleString('fa-IR')} مرحله تا فینال · محاسبهٔ خودکار بر اساس ورودی‌های حاضر` : 'تعداد مراحل تک‌حذفی پس از تأیید حضور حداقل دو ورودی، خودکار مشخص می‌شود.' }}<span v-if="!attendanceReady"> ابتدا حضور یا انصراف همهٔ ورودی‌ها را مشخص کنید.</span></p>
            <FormErrors :errors="status.errors"/>
            <div class="table-wrap"><table><thead><tr><th>ورودی</th><th>باشگاه</th><th>وضعیت</th><th v-if="!category.registration_locked && !category.management_source">پذیرش</th><th v-if="!category.management_source">ویرایش</th></tr></thead><tbody><tr v-for="athlete in category.entries" :key="athlete.id"><td>{{ athlete.name }}</td><td>{{ athlete.club || '—' }}</td><td>{{ {registered:'در انتظار حضور',checked_in:'حاضر',withdrawn:'منصرف',disqualified:'حذف‌شده'}[athlete.status] }}</td><td v-if="!category.registration_locked && !category.management_source" class="row-actions"><button v-if="athlete.status !== 'checked_in'" class="button small" :disabled="status.processing" @click="setStatus(athlete.id,'checked_in')">حاضر</button><button v-if="athlete.status !== 'withdrawn'" class="button secondary small" :disabled="status.processing" @click="setStatus(athlete.id,'withdrawn')">انصراف</button></td><td v-if="!category.management_source"><EntryDetailsEditor :entry="athlete" :category="category" :tournament="tournament" :base="base"/></td></tr></tbody></table></div>
            <p v-if="!category.entries.length" class="subtle">هنوز ورزشکاری ثبت نشده است.</p>
        </section>
        <section v-if="category.planned_round_count && category.discipline === 'recognized'" class="panel">
            <h2>قرعهٔ پومسه‌های مراحل</h2>
            <p>{{ { day_before: 'روز قبل مسابقه', morning: 'صبح مسابقه', before_stage: 'قبل از شروع هر مرحله' }[category.form_draw_timing] }} · {{ category.allow_form_repetition ? 'تکرار بین مراحل مجاز است' : 'بدون تکرار بین مراحل' }}</p>
            <p class="subtle">زمان مجاز: {{ new Date(category.draw_available_at).toLocaleString('fa-IR', { timeZone: tournament.timezone || 'Asia/Tehran' }) }}</p>
            <div class="chips"><span v-for="form in category.form_pool" :key="form.id" class="badge">{{ form.name }}</span></div><p class="subtle">این فهرست توسط مدیر هنگام ساخت رده انتخاب شده است؛ قرعهٔ هر مرحله فقط از فرم‌های همین فهرست انجام می‌شود.</p>
            <div v-for="round in category.rounds" :key="round.id"><div class="section-heading"><span>{{ round.name }} · {{ round.forms_ready ? round.form_names.join('، ') : 'در انتظار قرعهٔ پومسه' }}</span><span class="row-actions"><button v-if="!category.management_source && category.form_draw_timing === 'before_stage' && !round.forms_ready" type="button" class="button small" :disabled="draw.processing || manualForms.processing || !drawAvailable || !round.previous_completed || !automaticStagePlanReady" @click="drawForms(round.id)">قرعهٔ خودکار</button><button v-if="!category.management_source && round.previous_completed && !round.started_at" type="button" class="button small secondary" :disabled="manualForms.processing || !automaticStagePlanReady || selectedManualForms(round).length !== 2" @click="assignForms(round.id, selectedManualForms(round))">ثبت انتخاب دستی</button></span></div><div v-if="!category.management_source && round.previous_completed && !round.started_at" class="chips"><label v-for="form in category.form_pool" :key="form.id" class="check badge"><input type="checkbox" :checked="selectedManualForms(round).includes(form.id)" :disabled="manualForms.processing || (!selectedManualForms(round).includes(form.id) && selectedManualForms(round).length >= 2)" @change="toggleManualForm(round, form.id)">{{ form.name }}</label></div></div>
            <button v-if="!category.management_source && category.form_draw_timing !== 'before_stage' && category.rounds.some(round => !round.forms_ready)" type="button" class="button" :disabled="draw.processing || !drawAvailable || !automaticStagePlanReady" @click="drawForms(null)">قرعهٔ فرم‌های تمام مراحل</button>
            <FormErrors :errors="draw.errors"/>
        </section>
        <section class="panel" v-if="canSchedule">
            <h2>{{ nextStage ? `ساخت جدول ${nextStage.name}` : scheduledRounds.length ? 'ساخت دور بعد' : 'ساخت قرعه و برنامهٔ اجرا' }}</h2>
            <p class="subtle">{{ scheduledRounds.length ? 'پس از پایان مرحلهٔ قبل، برنامهٔ مرحلهٔ بعد ساخته می‌شود.' : 'ورودی‌های حاضر با قرعهٔ تصادفی در جدول قرار می‌گیرند.' }}</p>
            <form @submit.prevent="scheduleRound">
                <p class="field-label">زمین‌های اجرای این مرحله · انتخاب یک تا چهار زمین</p>
                <div class="judge-options"><label v-for="court in tournament.courts" :key="court.id" class="check"><input type="checkbox" :checked="selectedCourtIds.includes(court.id)" :disabled="schedule.processing || (!selectedCourtIds.includes(court.id) && schedule.courts.length === 4)" @change="toggleCourt(court.id)">{{ court.name }}</label></div>
                <p class="subtle">جدول این رده بین زمین‌های انتخاب‌شده تقسیم می‌شود. هر زمین صف اجرا و پنل داوری مستقل دارد؛ رده‌های دیگر هم می‌توانند روی زمین‌های آزاد اجرا شوند.</p>
                <fieldset v-for="panel in schedule.courts" :key="panel.court_id" class="court-panel">
                    <legend>{{ courtName(panel.court_id) }} · پنل {{ category.judge_count }} نفره · {{ panel.judge_ids.length }} انتخاب شده</legend>
                    <div class="judge-options"><label v-for="judge in availableJudges" :key="judge.id" class="check"><input v-model="panel.judge_ids" type="checkbox" :value="judge.id" :disabled="schedule.processing || judgeAssignedElsewhere(judge.id, panel.court_id) || (!panel.judge_ids.includes(judge.id) && panel.judge_ids.length >= category.judge_count)">{{ judge.name }}</label></div>
                </fieldset>
                <p class="subtle">ترتیب انتخاب داورها، شمارهٔ صندلی را مشخص می‌کند. {{ category.format === 'knockout' ? 'پس از پایان همهٔ زمین‌های این رده، برنده‌ها برای مرحلهٔ بعد در جدول مشترک قرار می‌گیرند؛ برای ادامه می‌توانید تعداد زمین‌ها را کم کنید و فینال را روی یک زمین برگزار کنید.' : 'نتایج تمام زمین‌ها در رتبه‌بندی مشترک این رده جمع می‌شود؛ ساخت مرحلهٔ بعد پس از پایان همهٔ زمین‌های همین رده مجاز است.' }}</p>
                <FormErrors :errors="schedule.errors"/><button class="button" :disabled="schedule.processing || !previousComplete || !courtPanelsReady || !automaticStagePlanReady">تأیید و ساخت جدول</button>
            </form>
        </section>
        <section v-if="scheduledRounds.length" class="panel"><h2>زمین‌های مراحل این رده</h2><div v-for="round in scheduledRounds" :key="round.id" class="section-heading"><span>{{ round.name }}</span><span>{{ round.court_ids.map(courtName).join('، ') }} · {{ round.status === 'completed' ? 'پایان همهٔ زمین‌ها' : 'در انتظار پایان مرحله' }}</span></div></section>
    </div>
</template>
<style scoped>
.court-panel { border: 1px solid #dce5da; border-radius: 8px; padding: 16px; margin: 16px 0; }
.court-panel legend { font-size: 13px; padding: 0 8px; }
</style>
