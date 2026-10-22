<script setup>
import { computed, ref, watch } from "vue";
import { useForm, usePage } from "@inertiajs/vue3";
import FormErrors from "../../../Shared/Components/FormErrors.vue";
import ScoreEntryFields from "../../ScoreEntryFields.vue";
import { requestId } from "../../../Shared/Services/requestId";
import {
    hasScoreDetails,
    scoreComponentsComplete,
    scoreDetailFields,
    scorePayload,
} from "../../../scoring";
const props = defineProps({
    performance: Object,
    rules: Object,
    endpoint: String,
});
const page = usePage();
const key =
    "poomsae-score:" + page.props.auth.user.id + ":" + props.performance.id;
let cached;
try {
    cached = JSON.parse(localStorage.getItem(key) || "null");
} catch {
    cached = null;
}
const saved = ref(false);
let suppressDraft = false;
// Resume a draft only when its scoring method and performance version still match.
const previousBreakdown = props.performance.own_score?.breakdown;
const inputMethod =
    !props.performance.own_score ||
    previousBreakdown?.method === props.rules.input_method
        ? props.rules.input_method
        : null;
const initial = {
    request_id: requestId(),
    expected_version: props.performance.version,
    expected_revision: props.performance.own_score?.revision || 0,
    accuracy: props.performance.own_score
        ? (props.performance.own_score.values.accuracy / 100).toFixed(2)
        : "",
    presentation: props.performance.own_score
        ? (props.performance.own_score.values.presentation / 100).toFixed(2)
        : "",
    ...scoreDetailFields(inputMethod, previousBreakdown),
    reason: "",
};
const usableDraft =
    cached?.expected_version === props.performance.version &&
    hasScoreDetails(cached, inputMethod);
const form = useForm(usableDraft ? cached : initial);
const canSubmit = computed(() => scoreComponentsComplete(form));
const store = () => {
    try {
        localStorage.setItem(key, JSON.stringify(form.data()));
        saved.value = true;
    } catch {
        saved.value = false;
    }
};
watch(
    () => [
        form.accuracy,
        form.presentation,
        form.accuracy_penalties,
        form.presentation_components,
        form.presentation_penalties,
        form.accuracy_components,
        form.reason,
    ],
    () => {
        if (!suppressDraft) {
            form.request_id = requestId();
            store();
        }
    },
    { flush: "sync", deep: true },
);
const submit = () => {
    store();
    form.transform((data) => scorePayload(data, props.rules.input_method)).post(
        props.endpoint,
        {
            preserveScroll: true,
            onSuccess: () => {
                suppressDraft = true;
                try {
                    localStorage.removeItem(key);
                } catch {}
                saved.value = false;
                form.request_id = requestId();
                form.expected_revision =
                    props.performance.own_score?.revision ||
                    form.expected_revision + 1;
                form.expected_version = props.performance.version;
                form.reason = "";
                suppressDraft = false;
            },
        },
    );
};
const reloadOwn = () => {
    form.accuracy = props.performance.own_score
        ? (props.performance.own_score.values.accuracy / 100).toFixed(2)
        : "";
    form.presentation = props.performance.own_score
        ? (props.performance.own_score.values.presentation / 100).toFixed(2)
        : "";
    const breakdown = props.performance.own_score?.breakdown;
    Object.assign(
        form,
        scoreDetailFields(
            breakdown?.method ?? props.rules.input_method,
            breakdown,
        ),
    );
    form.expected_revision = props.performance.own_score?.revision || 0;
    form.expected_version = props.performance.version;
    form.reason = "";
    form.clearErrors();
};
const notice = "mb-5 rounded-lg bg-[#12382f] px-[18px] py-3 text-[#75e0b7]";
const actionButton =
    "inline-flex min-h-[58px] items-center justify-center rounded-lg bg-[#11b8b0] px-[22px] py-[11px] text-[15px] font-extrabold text-[#031817] disabled:cursor-wait disabled:opacity-55";
</script>
<template>
    <form
        class="rounded-[14px] border border-[#20364b] bg-[#08131f] p-6 max-[560px]:px-3 max-[560px]:py-4"
        @submit.prevent="submit"
    >
        <p v-if="performance.own_score" :class="notice">
            نمرهٔ شما ثبت شده است · نسخه {{ performance.own_score.revision }}.
            اصلاح با ثبت دلیل مجاز است و در تاریخچه باقی می‌ماند.
        </p>
        <ScoreEntryFields
            :form="form"
            :rules="rules"
            :id-prefix="`judge-${performance.id}`"
            :disabled="form.processing"
            :reset-key="form.request_id"
            theme="dark"
        />
        <div v-if="form.expected_revision > 0">
            <label :for="'correction-' + performance.id">دلیل اصلاح</label
            ><textarea
                :id="'correction-' + performance.id"
                v-model="form.reason"
                :disabled="form.processing"
                required
                minlength="5"
                maxlength="500"
            ></textarea>
        </div>
        <p class="mt-[15px] text-[13px] text-[#7c8984]">
            {{
                saved
                    ? "پیش‌نویس روی همین دستگاه ذخیره شد؛ هنوز به معنای ثبت روی سرور نیست."
                    : "ثبت قطعی فقط پس از پاسخ موفق سرور انجام می‌شود."
            }}
        </p>
        <FormErrors :errors="form.errors" />
        <div class="mt-5 flex flex-wrap items-center gap-[10px]">
            <button
                :class="actionButton"
                :disabled="form.processing || !canSubmit"
            >
                {{
                    form.processing
                        ? "در حال ارسال…"
                        : form.expected_revision > 0
                          ? "ثبت اصلاح نمره"
                          : "تأیید و ارسال نمره"
                }}</button
            ><button
                v-if="
                    (performance.own_score?.revision || 0) !==
                    form.expected_revision
                "
                type="button"
                :class="actionButton"
                @click="reloadOwn"
            >
                دریافت نسخهٔ جدید نمره
            </button>
        </div>
    </form>
</template>
