<script setup>
import { computed, watch } from "vue";
import { useForm } from "@inertiajs/vue3";
import { requestId } from "../requestId";
import FormErrors from "./common/FormErrors.vue";

const props = defineProps({
    performance: Object,
    rules: Object,
    endpoint: String,
});
const missingJudges = computed(() =>
    props.performance.judges.filter((judge) =>
        props.performance.missing_seats.includes(judge.seat),
    ),
);
const form = useForm({
    request_id: requestId(),
    judge_assignment_id: "",
    expected_version: props.performance.version,
    expected_revision: 0,
    accuracy: "",
    presentation: "",
    reason: "",
});
watch(
    () => props.performance.version,
    (version) => (form.expected_version = version),
);
const submit = () =>
    form
        .transform((data) => ({
            ...data,
            accuracy: String(data.accuracy),
            presentation: String(data.presentation),
        }))
        .post(props.endpoint, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset(
                    "judge_assignment_id",
                    "accuracy",
                    "presentation",
                    "reason",
                );
                form.request_id = requestId();
                form.expected_revision = 0;
                form.expected_version = props.performance.version;
            },
        });
const labelClass = "mt-[13px] text-[#a8bbcc]";
const fieldClass = "border-[#30465a] bg-[#09131f] text-[#f2f7fb]";
const scoreInput =
    "h-[58px] border-2 border-[#30465a] bg-[#09131f] text-center text-[25px] text-[#f2f7fb]";
const dangerButton =
    "inline-flex min-h-11 items-center justify-center rounded-lg bg-[#d94752] px-[22px] py-[11px] text-[13px] font-bold text-white shadow-[0_7px_22px_#00a9a42e] disabled:cursor-wait disabled:opacity-55";
</script>

<template>
    <details
        v-if="missingJudges.length"
        class="mt-5 border-t border-[#2a3c4f] pt-[15px]"
    >
        <summary class="!text-[#ffbd68]">
            ثبت اضطراری به‌جای داور قطع‌شده
        </summary>
        <form
            class="rounded-xl border border-[#3c4650] bg-[#101a27] p-[18px]"
            @submit.prevent="submit"
        >
            <div
                class="mb-[15px] grid gap-[3px] rounded-[7px] border-r-4 border-[#f28350] bg-[#3b241c] px-[14px] py-3"
            >
                <b class="text-[#ffb07d]">ثبت با مسئولیت اپراتور</b
                ><span class="text-[10px] text-[#d8b6a4]"
                    >نام اپراتور، صندلی داور، دلیل و نمره در سابقهٔ غیرقابل حذف
                    مسابقه ذخیره می‌شود.</span
                >
            </div>
            <label :for="`proxy-seat-${performance.id}`" :class="labelClass"
                >صندلی قطع‌شده</label
            >
            <select
                :id="`proxy-seat-${performance.id}`"
                v-model.number="form.judge_assignment_id"
                :class="fieldClass"
                required
            >
                <option value="" disabled>انتخاب صندلی داور</option>
                <option
                    v-for="judge in missingJudges"
                    :key="judge.id"
                    :value="judge.id"
                >
                    صندلی {{ judge.seat }} · {{ judge.name }}
                </option>
            </select>
            <div class="my-[15px] grid grid-cols-2 gap-[12px]">
                <div>
                    <label
                        :for="`proxy-accuracy-${performance.id}`"
                        :class="[labelClass, 'text-[15px]']"
                        >دقت · از {{ rules.accuracy_max / 100 }}</label
                    ><input
                        :id="`proxy-accuracy-${performance.id}`"
                        v-model="form.accuracy"
                        type="number"
                        min="0"
                        :max="rules.accuracy_max / 100"
                        step="0.01"
                        inputmode="decimal"
                        :class="scoreInput"
                        required
                        dir="ltr"
                    />
                </div>
                <div>
                    <label
                        :for="`proxy-presentation-${performance.id}`"
                        :class="[labelClass, 'text-[15px]']"
                        >اجرا · از {{ rules.presentation_max / 100 }}</label
                    ><input
                        :id="`proxy-presentation-${performance.id}`"
                        v-model="form.presentation"
                        type="number"
                        min="0"
                        :max="rules.presentation_max / 100"
                        step="0.01"
                        inputmode="decimal"
                        :class="scoreInput"
                        required
                        dir="ltr"
                    />
                </div>
            </div>
            <label :for="`proxy-reason-${performance.id}`" :class="labelClass"
                >دلیل و شرح قطعی ارتباط</label
            >
            <textarea
                :id="`proxy-reason-${performance.id}`"
                v-model="form.reason"
                :class="fieldClass"
                required
                minlength="10"
                maxlength="500"
                placeholder="مثال: تبلت صندلی ۳ از شبکه خارج شد و سرداور ثبت جایگزین را تأیید کرد."
            ></textarea>
            <FormErrors :errors="form.errors" />
            <button :class="dangerButton" :disabled="form.processing">
                ثبت نمرهٔ جایگزین
            </button>
        </form>
    </details>
</template>
