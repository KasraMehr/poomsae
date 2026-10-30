<script setup>
import { computed, watch } from "vue";
import { useForm } from "@inertiajs/vue3";
import { requestId } from "../../../Shared/Services/requestId";
import FormErrors from "../../../Shared/Components/FormErrors.vue";
import ScoreEntryFields from "../ScoreEntryFields.vue";
import {
    scoreComponentsComplete,
    scoreDetailFields,
    scorePayload,
} from "../../../Shared/Services/scoring";

const props = defineProps({
    performance: Object,
    rules: Object,
    endpoint: String,
    expanded: { type: Boolean, default: false },
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
    ...scoreDetailFields(props.rules.input_method),
    reason: "",
});
const canSubmit = computed(() => scoreComponentsComplete(form));
watch(
    () => props.performance.version,
    (version) => (form.expected_version = version),
);
const submit = () =>
    form.transform((data) => scorePayload(data, props.rules.input_method)).post(
        props.endpoint,
        {
            preserveScroll: true,
            onSuccess: () => {
                form.reset(
                    "judge_assignment_id",
                    "accuracy",
                    "presentation",
                    "accuracy_penalties",
                    "presentation_components",
                    "presentation_penalties",
                    "accuracy_components",
                    "reason",
                );
                form.request_id = requestId();
                form.expected_revision = 0;
                form.expected_version = props.performance.version;
            },
        },
    );
const labelClass = "mt-3 text-xs font-semibold text-[#34445c]";
const fieldClass = "min-h-11 w-full rounded-lg border border-[#c4c9d2] bg-white px-3 text-[#0d192b] focus:border-[#24466a] focus:outline-none";
const primaryButton =
    "inline-flex min-h-11 items-center justify-center rounded-lg bg-[#0d192b] px-5 py-3 text-sm font-bold text-white shadow-sm hover:bg-[#24466a] disabled:cursor-not-allowed disabled:opacity-55";
</script>

<template>
    <!-- Keep emergency scoring compatible with every configured score method. -->
    <details
        v-if="missingJudges.length"
        :open="expanded"
        class="mt-5 border-t border-[#c4c9d2] pt-4"
    >
        <summary class="cursor-pointer font-bold text-[#0d192b]">
            Manual score for a missing judge seat
        </summary>
        <form
            class="mt-3 grid gap-3 rounded-xl border border-[#c4c9d2] bg-[#f7f8fc] p-4 sm:p-5"
            @submit.prevent="submit"
        >
            <div
                class="grid gap-1 rounded-lg border border-[#e7c894] bg-[#fff7e8] px-4 py-3"
            >
                <b class="text-sm text-[#755019]">Operator responsibility</b
                ><span class="text-xs text-[#755019]"
                    >The operator, judge seat, score, and optional note are stored in the competition audit trail.</span
                >
            </div>
            <label :for="`proxy-seat-${performance.id}`" :class="labelClass"
                >Judge seat</label
            >
            <select
                :id="`proxy-seat-${performance.id}`"
                v-model.number="form.judge_assignment_id"
                :class="fieldClass"
                required
            >
                <option value="" disabled>Select judge seat</option>
                <option
                    v-for="judge in missingJudges"
                    :key="judge.id"
                    :value="judge.id"
                >
                    Seat {{ judge.seat }} · {{ judge.name }}
                </option>
            </select>
            <ScoreEntryFields
                :form="form"
                :rules="rules"
                :id-prefix="`proxy-${performance.id}`"
                :disabled="form.processing"
                :reset-key="form.request_id"
                theme="light"
            />
            <label :for="`proxy-reason-${performance.id}`" :class="labelClass"
                >Reason for manual entry <span class="font-normal text-[#64748b]">(optional)</span></label
            >
            <textarea
                :id="`proxy-reason-${performance.id}`"
                v-model="form.reason"
                :class="fieldClass"
                maxlength="500"
                rows="2"
                placeholder="For example: device issue"
            ></textarea>
            <FormErrors :errors="form.errors" />
            <button
                :class="primaryButton"
                :disabled="form.processing || !canSubmit"
            >
                Submit manual score for review
            </button>
        </form>
    </details>
</template>
