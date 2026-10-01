<script setup>
import { computed } from "vue";
import { useForm } from "@inertiajs/vue3";
import FormErrors from "./common/FormErrors.vue";
const props = defineProps({
    bout: Object,
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
const command = (performance, value) => {
    action.command = value;
    action.expected_version = performance.version;
    action.post(props.base + "/performances/" + performance.id + "/command", {
        preserveScroll: true,
    });
};
const entryName = (id) =>
    props.bout.entries.find((e) => e.id === id)?.name || "—";
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
        <div
            class="mb-5 flex items-center justify-between gap-[15px] max-[850px]:flex-col max-[850px]:items-start"
        >
            <div>
                <span class="mb-2 block text-xs" :class="themeClasses.eyebrow"
                    >رقابت {{ bout.sequence }} ·
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
            v-if="bout.performances.length"
            class="grid grid-cols-2 gap-[15px] max-[850px]:grid-cols-1"
        >
            <section
                v-for="p in bout.performances"
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
                    <small class="mt-[5px] text-[10px] text-[#87967c]"
                        >فرم {{ p.form_number }} از ۲</small
                    >
                </p>
                <div
                    class="my-[14px] text-[28px] font-bold tabular-nums text-[#376c4b]"
                >
                    {{ fmt(p.result) }}
                </div>
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
                        v-if="p.status === 'pending'"
                        :class="[btnBase, btnPrimary, btnSmall]"
                        :disabled="action.processing"
                        @click="command(p, 'start')"
                    >
                        شروع اجرا
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
                    <summary>نمرهٔ داوران</summary>
                    <div
                        class="flex justify-between border-b border-[#e4eada] py-2 text-[11px]"
                        v-for="score in p.scores"
                        :key="score.seat"
                    >
                        <span
                            >صندلی {{ score.seat }} · ویرایش
                            {{ score.revision }}</span
                        ><b class="[direction:ltr]"
                            >{{ (score.values.accuracy / 100).toFixed(2) }} +
                            {{
                                (score.values.presentation / 100).toFixed(2)
                            }}</b
                        >
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
            <form
                @submit.prevent="
                    walkover.post(base + '/bouts/' + bout.id + '/resolve', {
                        preserveScroll: true,
                    })
                "
            >
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
                <label
                    class="flex cursor-pointer items-center gap-[9px] text-xs"
                    ><input
                        v-model="walkover.confirmed"
                        type="checkbox"
                        required
                        class="h-[18px] w-[18px] min-h-[18px] shrink-0 accent-[#286957]"
                    />تأیید می‌کنم اجراهای تأییدنشدهٔ این رقابت لغو و برنده ثبت
                    شود.</label
                >
                <FormErrors :errors="walkover.errors" /><button
                    :class="[btnBase, btnSecondary, btnSize]"
                    class="mt-[15px]"
                    :disabled="walkover.processing"
                >
                    ثبت تصمیم و پایان رقابت
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
