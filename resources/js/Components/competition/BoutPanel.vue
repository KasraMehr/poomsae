<script setup>
import { computed } from "vue";
import { useForm } from "@inertiajs/vue3";
import FormErrors from "../../Shared/Components/FormErrors.vue";
import ScoreCorrectionForm from "./ScoreCorrectionForm.vue";
import PerformanceTiming from "./PerformanceTiming.vue";
import { restRemaining, usePerformanceClock } from "../../usePerformanceTiming";
const props = defineProps({
    bout: Object,
    round: Object,
    category: Object,
    tournament: Object,
    base: String,
    canOperate: Boolean,
    theme: { type: String, default: "light" },
});
const action = useForm({ command: "", expected_version: 1 });
const resolution = useForm({ winner_entry_id: "", reason: "" });
const walkover = useForm({
    winner_entry_id: "",
    reason: "",
    decision_type: "walkover",
    confirmed: false,
});
const now = usePerformanceClock(() => props.bout.performances[0]?.server_now);
// Calculate shared rest time for simultaneous knockout performances.
const restFor = (performance) =>
    Math.max(
        ...props.bout.performances
            .filter(
                (item) =>
                    item.id === performance.id ||
                    (props.category.format === "knockout" &&
                        props.category.execution_mode === "simultaneous" &&
                        item.form_number === performance.form_number),
            )
            .map((item) => restRemaining(item, now.value)),
    );
// Preserve the direct version's singleton walkover resolution behavior.
const submitWalkover = () => {
    if (props.bout.entries.length === 1) {
        walkover.winner_entry_id = null;
    }
    walkover.decision_type = "walkover";
    walkover.post(props.base + "/bouts/" + props.bout.id + "/resolve", {
        preserveScroll: true,
    });
};
const command = (performance, value) => {
    action.command = value;
    action.expected_version = performance.version;
    action.post(props.base + "/performances/" + performance.id + "/command", {
        preserveScroll: true,
    });
};
const entryName = (id) =>
    props.bout.entries.find((e) => e.id === id)?.name || "—";
const rawScore = (values, method) =>
    method === "single_score_v1"
        ? (values.accuracy / 100).toFixed(2)
        : `دقت ${(values.accuracy / 100).toFixed(2)} + اجرا ${(values.presentation / 100).toFixed(2)}`;
const orderedPerformances = computed(() =>
    [...props.bout.performances].sort((a, b) =>
        props.category.format === "knockout"
            ? a.form_number - b.form_number || a.id - b.id
            : a.id - b.id,
    ),
);
// Gate starts using the tournament's configured performance sequence.
const nextPerformanceId = computed(() => {
    const performances = props.category.score_based
        ? props.round.bouts
              .filter((bout) => bout.court_id === props.bout.court_id)
              .flatMap((bout) =>
                  bout.performances.map((performance) => ({
                      ...performance,
                      bout_sequence: bout.sequence,
                  })),
              )
        : props.bout.performances.map((performance) => ({
              ...performance,
              bout_sequence: props.bout.sequence,
          }));
    performances.sort((a, b) =>
        props.category.score_based &&
        props.category.performance_order === "phased"
            ? a.form_number - b.form_number ||
              a.bout_sequence - b.bout_sequence ||
              a.id - b.id
            : props.category.score_based
              ? a.bout_sequence - b.bout_sequence ||
                a.form_number - b.form_number ||
                a.id - b.id
              : a.form_number - b.form_number || a.id - b.id,
    );
    return performances.find(
        (performance) => !["approved", "cancelled"].includes(performance.status),
    )?.id;
});
const labels = {
    pending: "در انتظار",
    running: "در حال اجرا",
    scoring: "دریافت نمره",
    approved: "تأیید و منتشر شده",
    cancelled: "لغو شده",
    completed: "پایان‌یافته",
};
const fmt = (value) =>
    value === null || value === undefined
        ? "—"
        : Number(value).toLocaleString("fa-IR", {
              minimumFractionDigits: 3,
              maximumFractionDigits: 6,
          });

const dark = computed(() => props.theme === "dark");
const themeClasses = computed(() =>
    dark.value
        ? {
              panel: "border-[#2a4055] bg-[#101e2d]",
              eyebrow: "text-[#55cfc8]",
              chung: "text-[#50a8ff]",
              hong: "text-[#ff6972]",
              subtle: "text-[#8da0b5]",
          }
        : {
              panel: "border-[#e0e7d9] bg-white",
              eyebrow: "text-[#578678]",
              chung: "text-[#3978b5]",
              hong: "text-[#b75854]",
              subtle: "text-[#7c8984]",
          },
);
const badgeClass = (status) => {
    if (status === "running")
        return dark.value
            ? "bg-[#123c34] text-[#59deb3]"
            : "bg-[#dcefe1] text-[#2a7851]";
    if (status === "scoring")
        return dark.value
            ? "bg-[#4a3b13] text-[#f5d56f]"
            : "bg-[#f4e8ba] text-[#927020]";
    if (status === "approved" || status === "completed")
        return "bg-[#dfece1] text-[#416951]";
    return dark.value
        ? "bg-[#1b2b3c] text-[#adc0d2]"
        : "bg-[#f2f4ed] text-[#81896b]";
};
const perfClass = (status) => {
    if (status === "scoring")
        return dark.value
            ? "border-[#6a5924] bg-[#25220f]"
            : "border-[#d7bd7a] bg-[#fffcf1]";
    if (status === "running")
        return dark.value
            ? "border-[#23384c] bg-[#0a1522]"
            : "border-[#72aa8a] bg-[#f0f7ee]";
    return dark.value
        ? "border-[#23384c] bg-[#0a1522]"
        : "border-[#e6eadf] bg-[#fafcf7]";
};
const btnBase =
    "inline-flex items-center justify-center rounded-lg disabled:cursor-wait disabled:opacity-55";
const btnSize = "min-h-11 px-[22px] py-[11px] text-[13px]";
const btnSmall = "min-h-[34px] px-3 py-1.5 text-[11px]";
const btnPrimary = computed(() =>
    dark.value
        ? "bg-[#09b5b0] font-bold text-[#031316] shadow-[0_7px_22px_#00a9a42e] hover:bg-[#30d0ca]"
        : "bg-[#286957] text-white hover:bg-[#1b5142]",
);
const btnSecondary = computed(() =>
    dark.value
        ? "border border-[#263b51] bg-[#132235] text-[#b8c9da]"
        : "bg-[#e8eee7] text-[#42664f]",
);
const badgeBase = "inline-block rounded-[5px] px-[9px] py-[3px] text-[10px]";
</script>
<template>
    <article
        class="mb-5 rounded-xl border p-6 max-[500px]:p-[17px]"
        :class="themeClasses.panel"
    >
        <p
            v-if="round.advancement_review_required && bout.sequence === 1"
            class="mb-4 rounded-lg border border-[#d7bd7a] bg-[#fffcf1] px-4 py-3 text-xs text-[#927020]"
        >
            نیاز به بررسی: اصلاح نتیجهٔ مرحلهٔ قبل، برندهٔ راه‌یافته را تغییر داده است. شرکت‌کنندگان و نتایج این مرحله چون شروع شده بود، حفظ شده‌اند.
        </p>
        <div
            class="mb-5 flex items-center justify-between gap-[15px] max-[850px]:flex-col max-[850px]:items-start"
        >
            <div>
                <span class="mb-2 block text-xs" :class="themeClasses.eyebrow"
                    >{{
                        category.score_based ? "اجرا" : "رقابت"
                    }} {{ bout.sequence }} ·
                    {{
                        tournament.courts.find((c) => c.id === bout.court_id)
                            ?.name
                    }}</span
                >
                <h3>
                    <span v-for="(entry, index) in bout.entries" :key="entry.id"
                        ><span
                            :class="
                                entry.side === 'chung'
                                    ? themeClasses.chung
                                    : themeClasses.hong
                            "
                            >{{ entry.name }}</span
                        ><span
                            v-if="index === 0 && bout.entries.length === 2"
                            class="mx-[15px] font-normal text-[#a5b29a]"
                        >
                            /
                        </span></span
                    >
                </h3>
            </div>
            <span :class="[badgeBase, badgeClass(bout.status)]">{{
                labels[bout.status]
            }}</span>
        </div>
        <div
            v-if="bout.winner_entry_id"
            class="mb-4 rounded-lg bg-[#e5f2e2] px-[18px] py-[13px] font-bold text-[#3a764b]"
        >
            برنده: {{ entryName(bout.winner_entry_id) }}
            <small class="text-[11px] font-normal">{{
                bout.resolution_reason
            }}</small>
        </div>
        <div
            v-else-if="bout.resolution_reason"
            class="mb-4 rounded-lg bg-[#e5f2e2] px-[18px] py-[13px] text-xs text-[#3a764b]"
        >
            <small>{{ bout.resolution_reason }}</small>
        </div>
        <div
            v-if="bout.performances.length"
            class="grid grid-cols-2 gap-[15px] max-[850px]:grid-cols-1"
        >
            <section
                v-for="p in orderedPerformances"
                :key="p.id"
                class="rounded-[10px] border p-5"
                :class="perfClass(p.status)"
            >
                <div class="mb-[14px] text-xs">
                    <b>{{ entryName(p.entry_id) }}</b
                    ><span :class="[badgeBase, badgeClass(p.status)]">{{
                        labels[p.status]
                    }}</span>
                </div>
                <p class="text-[13px]">
                    {{ p.form_name }}
                    <small
                        v-if="category.forms_per_round > 1"
                        class="mt-[5px] text-[10px] text-[#87967c]"
                        >فرم {{ p.form_number }} از
                        {{ category.forms_per_round }}</small
                    >
                </p>
                <audio
                    v-if="canOperate && p.music_url"
                    :src="p.music_url"
                    controls
                    preload="none"
                    class="my-3 w-full"
                >پخش موسیقی پشتیبانی نمی‌شود.</audio>
                <div
                    class="my-[14px] text-[28px] font-bold tabular-nums text-[#376c4b]"
                >
                    {{ fmt(p.result) }}
                </div>
                <p
                    v-if="p.appeal_pending"
                    class="my-3 rounded-lg border border-[#d7bd7a] bg-[#fffcf1] px-3 py-2 text-xs text-[#927020]"
                >
                    نتیجه تحت بررسی اعتراض است؛ اصلاح نمره از مسیر رأی ناظر انجام می‌شود.
                </p>
                <PerformanceTiming :performance="p" />
                <p
                    v-if="p.status === 'scoring'"
                    class="text-[13px]"
                    :class="themeClasses.subtle"
                >
                    {{ p.submitted_count }} از {{ category.judge_count }} داور
                    نمره داده‌اند.
                </p>
                <div
                    v-if="canOperate && tournament.status !== 'completed'"
                    class="flex flex-wrap items-center gap-[10px]"
                >
                    <button
                        v-if="p.status === 'pending' && p.id === nextPerformanceId"
                        :class="[btnBase, btnPrimary, btnSmall]"
                        :disabled="
                            action.processing ||
                            !round.forms_ready ||
                            !round.previous_completed ||
                            restFor(p) > 0
                        "
                        @click="command(p, 'start')"
                    >
                        {{
                            category.execution_mode === 'simultaneous' &&
                            category.format === 'knockout'
                                ? 'شروع همزمان دو ورودی'
                                : 'شروع اجرا'
                        }}
                    </button>
                    <button
                        v-if="p.status === 'running'"
                        :class="[btnBase, btnPrimary, btnSmall]"
                        :disabled="action.processing"
                        @click="command(p, 'finish')"
                    >
                        پایان اجرا و دریافت نمره
                    </button>
                    <button
                        v-if="p.status === 'scoring'"
                        :class="[btnBase, btnPrimary, btnSmall]"
                        :disabled="
                            action.processing ||
                            p.submitted_count !== category.judge_count
                        "
                        @click="command(p, 'approve')"
                    >
                        تأیید نهایی و انتشار
                    </button>
                </div>
                <details v-if="canOperate && p.scores.length">
                    <summary>نمرهٔ خام داوران و تاریخچه</summary>
                    <div
                        class="border-b border-[#e4eada] py-2 text-[11px]"
                        v-for="score in p.scores"
                        :key="score.seat"
                    >
                        <div class="flex justify-between gap-3">
                            <span>
                                صندلی {{ score.seat }} · ویرایش
                                {{ score.revision }}
                            </span>
                            <b class="[direction:ltr]">
                                {{ rawScore(score.values, score.breakdown?.method) }}
                            </b>
                        </div>
                        <p
                            v-if="score.breakdown?.method === 'deductions_and_components_v1'"
                            class="mt-1 text-[10px]"
                            :class="themeClasses.subtle"
                        >
                            کسرهای دقت:
                            {{
                                score.breakdown.accuracy_penalties_hundredths.length
                                    ? score.breakdown.accuracy_penalties_hundredths
                                          .map((value) => (value / 100).toFixed(2))
                                          .join("، ")
                                    : "بدون کسر"
                            }}
                            · مؤلفه‌های اجرا:
                            {{
                                score.breakdown.presentation_components_hundredths
                                    .map((value) => (value / 100).toFixed(2))
                                    .join(" + ")
                            }}
                        </p>
                        <p
                            v-else-if="score.breakdown?.method === 'components_and_deductions_v1'"
                            class="mt-1 text-[10px]"
                            :class="themeClasses.subtle"
                        >
                            مؤلفه‌های دقت:
                            {{
                                score.breakdown.accuracy_components_hundredths
                                    .map((value) => (value / 100).toFixed(2))
                                    .join(" + ")
                            }}
                            · کسرهای اجرا:
                            {{
                                score.breakdown.presentation_penalties_hundredths.length
                                    ? score.breakdown.presentation_penalties_hundredths
                                          .map((value) => (value / 100).toFixed(2))
                                          .join("، ")
                                    : "بدون کسر"
                            }}
                        </p>
                        <p
                            v-else
                            class="mt-1 text-[10px]"
                            :class="themeClasses.subtle"
                        >نمرهٔ ثبت‌شده به روش مستقیم</p>
                        <ScoreCorrectionForm
                            v-if="['scoring', 'approved'].includes(p.status) && round.score_correction_open && !p.appeal_pending"
                            :key="score.id"
                            :performance="p"
                            :score="score"
                            :rules="category.rules"
                            :endpoint="`${base}/performances/${p.id}/proxy-scores`"
                        />
                        <p
                            v-else-if="p.status === 'approved' && !round.score_correction_open"
                            class="mt-2 text-[10px]"
                            :class="themeClasses.subtle"
                        >مهلت اصلاح نمرهٔ این مرحله بسته شده است.</p>
                        <details v-if="score.history?.length" class="mt-2">
                            <summary>سابقهٔ ثبت و اصلاح</summary>
                            <div
                                v-for="revision in score.history"
                                :key="revision.revision"
                                class="flex justify-between gap-3 border-b border-[#e4eada] py-2 text-[10px]"
                            >
                                <span>
                                    {{ revision.revision }} ·
                                    {{ revision.changed_by_name }} ·
                                    {{
                                        new Date(revision.created_at).toLocaleString(
                                            "fa-IR",
                                        )
                                    }}
                                    <small v-if="revision.reason">
                                        · {{ revision.reason }}
                                    </small>
                                </span>
                                <b>
                                    {{
                                        rawScore(
                                            revision.snapshot,
                                            revision.snapshot.breakdown?.method,
                                        )
                                    }}
                                </b>
                            </div>
                        </details>
                    </div>
                </details>
            </section>
        </div>
        <FormErrors :errors="action.errors" />
        <details
            v-if="
                canOperate &&
                ['pending', 'running'].includes(bout.status) &&
                bout.performances.some((p) => p.status !== 'approved')
            "
            class="mt-5 border-t border-[#e2e8de] pt-3"
        >
            <summary>انصراف یا عدم حضور</summary>
            <form @submit.prevent="submitWalkover">
                <template v-if="bout.entries.length === 2">
                    <label :for="'walkover-winner-' + bout.id" class="mt-[15px]"
                    >ورزشکار برنده</label
                ><select
                    :id="'walkover-winner-' + bout.id"
                    v-model.number="walkover.winner_entry_id"
                    required
                >
                    <option value="" disabled>انتخاب برنده</option>
                    <option
                        v-for="entry in bout.entries"
                        :key="entry.id"
                        :value="entry.id"
                    >
                        {{ entry.name }}
                    </option>
                </select>
                <label :for="'walkover-reason-' + bout.id" class="mt-[15px]"
                    >شرح انصراف یا عدم حضور طرف مقابل</label
                ><textarea
                    :id="'walkover-reason-' + bout.id"
                    v-model="walkover.reason"
                    required
                    minlength="5"
                    maxlength="900"
                    ></textarea>
                </template>
                <template v-else>
                    <p class="mt-3 text-xs" :class="themeClasses.subtle">
                        ورزشکار: <b>{{ bout.entries[0]?.name }}</b>
                    </p>
                    <label
                        :for="'walkover-reason-' + bout.id"
                        class="mt-[15px]"
                        >شرح انصراف یا عدم حضور ورزشکار</label
                    >
                    <textarea
                        :id="'walkover-reason-' + bout.id"
                        v-model="walkover.reason"
                        required
                        minlength="5"
                        maxlength="900"
                    ></textarea>
                </template>
                <label
                    class="flex cursor-pointer items-center gap-[9px] text-xs"
                    ><input
                        v-model="walkover.confirmed"
                        type="checkbox"
                        required
                        class="h-[18px] w-[18px] min-h-[18px] shrink-0 accent-[#286957]"
                    />تأیید می‌کنم اجراهای تأییدنشدهٔ
                    {{
                        bout.entries.length === 2
                            ? 'این رقابت لغو و برنده ثبت شود.'
                            : 'این ورزشکار لغو و مرحله ادامه یابد.'
                    }}</label
                >
                <FormErrors :errors="walkover.errors" /><button
                    :class="[btnBase, btnSecondary, btnSize]"
                    class="mt-[15px]"
                    :disabled="walkover.processing"
                >
                    {{
                        bout.entries.length === 2
                            ? 'ثبت تصمیم و پایان رقابت'
                            : 'ثبت انصراف و ادامهٔ مرحله'
                    }}
                </button>
            </form>
        </details>
        <div
            v-if="Object.keys(bout.totals).length"
            class="mt-[18px] flex justify-around gap-[25px] rounded-lg bg-[#eef4e9] p-[18px] max-[850px]:flex-col max-[850px]:gap-[10px]"
        >
            <span
                v-for="(score, id) in bout.totals"
                :key="id"
                class="flex items-center gap-5 text-xs max-[850px]:justify-between"
                >{{ entryName(Number(id)) }}
                <b class="text-[23px]">{{ fmt(score) }}</b></span
            >
        </div>
        <form
            v-if="
                canOperate &&
                !category.score_based &&
                bout.status === 'running' &&
                Object.keys(bout.totals).length === 2 &&
                !bout.winner_entry_id
            "
            class="mt-5 rounded-[10px] bg-[#fff8e5] p-5"
            @submit.prevent="
                resolution.post(base + '/bouts/' + bout.id + '/resolve', {
                    preserveScroll: true,
                })
            "
        >
            <h3>تساوی؛ تصمیم سرداور لازم است</h3>
            <p class="my-[10px] text-xs text-[#8a783f]">
                برنده را فقط مطابق آیین‌نامهٔ رویداد مشخص کنید. دلیل تصمیم در
                سابقهٔ مسابقه باقی می‌ماند.
            </p>
            <label :for="'winner-' + bout.id" class="mt-3">برنده</label
            ><select
                :id="'winner-' + bout.id"
                v-model.number="resolution.winner_entry_id"
                required
            >
                <option value="" disabled>انتخاب ورزشکار</option>
                <option
                    v-for="entry in bout.entries"
                    :key="entry.id"
                    :value="entry.id"
                >
                    {{ entry.name }}
                </option>
            </select>
            <label :for="'reason-' + bout.id" class="mt-3">دلیل تصمیم</label
            ><textarea
                :id="'reason-' + bout.id"
                v-model="resolution.reason"
                required
                minlength="5"
                maxlength="1000"
            ></textarea>
            <FormErrors :errors="resolution.errors" /><button
                :class="[btnBase, btnPrimary, btnSize]"
                class="mt-[15px]"
                :disabled="resolution.processing"
            >
                ثبت برنده و دلیل
            </button>
        </form>
    </article>
</template>
