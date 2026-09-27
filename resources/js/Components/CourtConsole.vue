<script setup>
import { useForm } from '@inertiajs/vue3';
import FormErrors from './common/FormErrors.vue';
import ProxyScoreForm from './ProxyScoreForm.vue';

const props = defineProps({ court: Object, base: String, rules: Object, onlineJudgeIds: Array });
const action = useForm({ command: '', expected_version: 1 });
const labels = { pending: 'آمادهٔ فراخوان', running: 'در حال اجرا', scoring: 'منتظر نمره‌ها', approved: 'تأیید شده' };
const commandLabel = { running: 'پایان اجرا و باز کردن ثبت نمره', scoring: 'تأیید نهایی و انتشار نتیجه' };

const command = (performance, value) => {
    action.command = value;
    action.expected_version = performance.version;
    action.post(`${props.base}/performances/${performance.id}/command`, { preserveScroll: true });
};
</script>

<template>
    <article class="court-console">
        <header class="court-header">
            <div><span class="live-dot"></span><b>{{ court.name }}</b></div>
            <span class="badge" :class="court.active?.status">{{ court.active ? labels[court.active.status] : 'زمین آزاد' }}</span>
        </header>

        <section v-if="court.active" class="active-performance" :class="court.active.status">
            <span class="eyebrow">{{ court.active.category_name }} · {{ court.active.round_name }} · رقابت {{ court.active.bout_sequence }}</span>
            <h2>{{ court.active.entry_name }}</h2>
            <p>{{ court.active.form_name }} · فرم {{ court.active.form_number }} از ۲</p>

            <div v-if="court.active.status === 'scoring'" class="judge-readiness">
                <div class="readiness-title"><b>{{ court.active.submitted_count }} از {{ court.active.judge_count }}</b><span>نمره دریافت شده</span></div>
                <div class="seat-grid">
                    <span v-for="judge in court.active.judges" :key="judge.id" :title="`${judge.name} · ${onlineJudgeIds.includes(judge.user_id) ? 'متصل' : 'بدون اتصال زنده'}`" :class="{ received: court.active.submitted_seats.includes(judge.seat), online: onlineJudgeIds.includes(judge.user_id) }">{{ judge.seat }}<i></i></span>
                </div>
                <small v-if="court.active.missing_seats.length">در انتظار صندلی‌های {{ court.active.missing_seats.join('، ') }}</small>
            </div>

            <button v-if="court.active.status === 'running'" class="button primary-action" :disabled="action.processing" @click="command(court.active, 'finish')">{{ commandLabel.running }}</button>
            <button v-if="court.active.status === 'scoring'" class="button primary-action" :disabled="action.processing || court.active.submitted_count !== court.active.judge_count" @click="command(court.active, 'approve')">{{ commandLabel.scoring }}</button>
            <ProxyScoreForm v-if="court.active.status === 'scoring'" :performance="court.active" :rules="rules" :endpoint="`${base}/performances/${court.active.id}/proxy-scores`" />
            <FormErrors :errors="action.errors" />
        </section>

        <section v-else-if="court.queue.length" class="next-call">
            <span class="eyebrow">اجرای بعدی</span>
            <h2>{{ court.queue[0].entry_name }}</h2>
            <p>{{ court.queue[0].category_name }} · {{ court.queue[0].form_name }}</p>
            <button class="button primary-action" :disabled="action.processing" @click="command(court.queue[0], 'start')">فراخوان و شروع اجرا</button>
            <FormErrors :errors="action.errors" />
        </section>

        <section v-else class="court-empty"><span>✓</span><h2>صف آماده‌ای وجود ندارد</h2><p>این زمین فعلاً آزاد است.</p></section>

        <section class="court-queue">
            <div class="queue-title"><h3>صف بعدی</h3><span>{{ court.queue.length }} اجرا</span></div>
            <div v-for="(performance, index) in court.queue.slice(court.active ? 0 : 1)" :key="performance.id" class="queue-row">
                <b>{{ index + (court.active ? 1 : 2) }}</b>
                <div><strong>{{ performance.entry_name }}</strong><small>{{ performance.category_name }} · {{ performance.form_name }}</small></div>
            </div>
            <p v-if="!court.queue.length" class="subtle">اجرای آماده‌ای در صف نیست.</p>
        </section>
    </article>
</template>
