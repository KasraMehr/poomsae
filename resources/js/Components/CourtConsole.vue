<script setup>
import { useForm } from "@inertiajs/vue3";
import FormErrors from "./common/FormErrors.vue";
import ProxyScoreForm from "./ProxyScoreForm.vue";

const props = defineProps({
    court: Object,
    base: String,
    rules: Object,
    onlineJudgeIds: Array,
});
const action = useForm({ command: "", expected_version: 1 });
const labels = {
    pending: "آمادهٔ فراخوان",
    running: "در حال اجرا",
    scoring: "منتظر نمره‌ها",
    approved: "تأیید شده",
};
const commandLabel = {
    running: "پایان اجرا و باز کردن ثبت نمره",
    scoring: "تأیید نهایی و انتشار نتیجه",
};
const badgeClasses = {
    pending: "bg-[#1b2b3c] text-[#adc0d2]",
    running: "bg-[#123c34] text-[#59deb3]",
    scoring: "bg-[#4a3b13] text-[#f5d56f]",
    approved: "bg-[#dfece1] text-[#416951]",
};
const stageClasses =
    "[grid-area:stage] min-h-[390px] border-l border-[#24384d] p-8 max-[900px]:min-h-0 max-[900px]:border-l-0 max-[900px]:border-b max-[900px]:border-[#24384d] max-[560px]:px-[18px] max-[560px]:py-[22px]";
const primaryAction =
    "mt-5 inline-flex min-h-[54px] w-full items-center justify-center rounded-lg border-0 bg-[#09b5b0] px-[22px] py-[11px] text-[14px] font-bold text-[#031316] shadow-[0_7px_22px_#00a9a42e] hover:bg-[#30d0ca] disabled:cursor-wait disabled:opacity-55";

const command = (performance, value) => {
    action.command = value;
    action.expected_version = performance.version;
    action.post(`${props.base}/performances/${performance.id}/command`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <article
        class="grid overflow-hidden rounded-[14px] border border-[#24384d] bg-[#0d1928] shadow-[0_16px_40px_#0005] [grid-template-columns:minmax(0,1.75fr)_minmax(285px,.65fr)] [grid-template-areas:'header_header'_'stage_queue'] max-[900px]:grid-cols-1 max-[900px]:[grid-template-areas:'header'_'stage'_'queue']"
    >
        <header
            class="flex items-center justify-between border-b border-[#24384d] bg-[#111f30] px-[22px] py-[18px] [grid-area:header]"
        >
            <div class="flex items-center gap-[10px]">
                <span
                    class="h-[9px] w-[9px] rounded-full bg-[#4ae0a8] shadow-[0_0_0_4px_#173e36,0_0_16px_#4ae0a8]"
                ></span
                ><b>{{ court.name }}</b>
            </div>
            <span
                class="inline-block rounded-[5px] px-[9px] py-[3px] text-[10px]"
                :class="court.active ? badgeClasses[court.active.status] : ''"
                >{{
                    court.active ? labels[court.active.status] : "زمین آزاد"
                }}</span
            >
        </header>

        <section
            v-if="court.active"
            :class="[
                stageClasses,
                court.active.status === 'scoring'
                    ? 'bg-[linear-gradient(145deg,#172333,#1d241b)]'
                    : 'bg-[linear-gradient(145deg,#101f30,#0b1725)]',
            ]"
        >
            <span class="mb-2 block text-xs text-[#55cfc8]"
                >{{ court.active.category_name }} ·
                {{ court.active.round_name }} · رقابت
                {{ court.active.bout_sequence }}</span
            >
            <h2
                class="mb-[5px] text-[clamp(32px,4vw,54px)] leading-[1.35] text-white"
            >
                {{ court.active.entry_name }}
            </h2>
            <p class="text-xs text-[#778579]">
                {{ court.active.form_name }} · فرم
                {{ court.active.form_number }} از ۲
            </p>

            <div
                v-if="court.active.status === 'scoring'"
                class="mt-6 rounded-[10px] border border-[#2b4054] bg-[#09131f] p-[15px]"
            >
                <div class="flex items-center justify-between">
                    <b class="text-[28px]"
                        >{{ court.active.submitted_count }} از
                        {{ court.active.judge_count }}</b
                    ><span class="text-[11px] text-[#8fa3b8]"
                        >نمره دریافت شده</span
                    >
                </div>
                <div class="my-[13px] flex gap-2 max-[560px]:gap-[5px]">
                    <span
                        v-for="judge in court.active.judges"
                        :key="judge.id"
                        :title="`${judge.name} · ${onlineJudgeIds.includes(judge.user_id) ? 'متصل' : 'بدون اتصال زنده'}`"
                        class="relative grid h-[43px] w-[43px] place-items-center rounded-full border border-[#63333b] bg-[#39242b] text-[14px] text-[#ff9a9f] max-[560px]:h-[35px] max-[560px]:w-[35px]"
                        :class="
                            court.active.submitted_seats.includes(judge.seat)
                                ? 'border-[#246b59] bg-[#123d35] text-[#5ae4b7]'
                                : ''
                        "
                        >{{ judge.seat
                        }}<i
                            class="absolute bottom-[-2px] left-[-2px] h-2 w-2 rounded-full border-2 border-[#09131f]"
                            :class="
                                onlineJudgeIds.includes(judge.user_id)
                                    ? 'bg-[#42dfa6] shadow-[0_0_8px_#42dfa6]'
                                    : 'bg-[#697789]'
                            "
                        ></i
                    ></span>
                </div>
                <small
                    v-if="court.active.missing_seats.length"
                    class="text-[11px] text-[#8fa3b8]"
                    >در انتظار صندلی‌های
                    {{ court.active.missing_seats.join("، ") }}</small
                >
            </div>

            <button
                v-if="court.active.status === 'running'"
                :class="primaryAction"
                :disabled="action.processing"
                @click="command(court.active, 'finish')"
            >
                {{ commandLabel.running }}
            </button>
            <button
                v-if="court.active.status === 'scoring'"
                :class="primaryAction"
                :disabled="
                    action.processing ||
                    court.active.submitted_count !== court.active.judge_count
                "
                @click="command(court.active, 'approve')"
            >
                {{ commandLabel.scoring }}
            </button>
            <ProxyScoreForm
                v-if="court.active.status === 'scoring'"
                :performance="court.active"
                :rules="rules"
                :endpoint="`${base}/performances/${court.active.id}/proxy-scores`"
            />
            <FormErrors :errors="action.errors" />
        </section>

        <section
            v-else-if="court.queue.length"
            :class="[
                stageClasses,
                'bg-[linear-gradient(145deg,#101f30,#0b1725)]',
            ]"
        >
            <span class="mb-2 block text-xs text-[#55cfc8]">اجرای بعدی</span>
            <h2
                class="mb-[5px] text-[clamp(32px,4vw,54px)] leading-[1.35] text-white"
            >
                {{ court.queue[0].entry_name }}
            </h2>
            <p class="text-xs text-[#778579]">
                {{ court.queue[0].category_name }} ·
                {{ court.queue[0].form_name }}
            </p>
            <button
                :class="primaryAction"
                :disabled="action.processing"
                @click="command(court.queue[0], 'start')"
            >
                فراخوان و شروع اجرا
            </button>
            <FormErrors :errors="action.errors" />
        </section>

        <section
            v-else
            class="[grid-area:stage] min-h-[390px] border-l border-[#24384d] px-5 py-[35px] text-center max-[900px]:min-h-0 max-[900px]:border-l-0 max-[900px]:border-b max-[900px]:border-[#24384d]"
        >
            <span class="text-[25px] text-[#5c9870]">✓</span>
            <h2>صف آماده‌ای وجود ندارد</h2>
            <p class="text-[11px] text-[#8a978d]">این زمین فعلاً آزاد است.</p>
        </section>

        <section
            class="min-h-[390px] bg-[#0c1826] p-5 [grid-area:queue] max-[900px]:min-h-0"
        >
            <div class="mb-[10px] flex items-center justify-between">
                <h3>صف بعدی</h3>
                <span class="text-[10px] text-[#8297aa]"
                    >{{ court.queue.length }} اجرا</span
                >
            </div>
            <div
                v-for="(performance, index) in court.queue.slice(
                    court.active ? 0 : 1,
                )"
                :key="performance.id"
                class="grid grid-cols-[30px_1fr] gap-[10px] border-t border-[#1d3043] py-[11px]"
            >
                <b
                    class="grid h-[25px] w-[25px] place-items-center rounded-full bg-[#173047] text-[10px] text-[#70d8d3]"
                    >{{ index + (court.active ? 1 : 2) }}</b
                >
                <div>
                    <strong class="block text-xs">{{
                        performance.entry_name
                    }}</strong
                    ><small class="text-[10px] text-[#8297aa]"
                        >{{ performance.category_name }} ·
                        {{ performance.form_name }}</small
                    >
                </div>
            </div>
            <p v-if="!court.queue.length" class="text-[13px] text-[#8da0b5]">
                اجرای آماده‌ای در صف نیست.
            </p>
        </section>
    </article>
</template>
