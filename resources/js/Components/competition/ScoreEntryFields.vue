<script setup>
import { computed, ref, watch } from 'vue';
import { adjustPenalties, hasScoreDetails } from '../../Shared/Services/scoring';

const props = defineProps({
    form: Object,
    rules: Object,
    idPrefix: String,
    disabled: Boolean,
    resetKey: String,
    theme: { type: String, default: 'light' },
});
const step = ref('accuracy');
const freestyle = computed(
    () => props.rules.input_method === 'components_and_deductions_v1',
);
const detailed = computed(
    () =>
        [
            'deductions_and_components_v1',
            'components_and_deductions_v1',
        ].includes(props.rules.input_method) &&
        hasScoreDetails(props.form, props.rules.input_method),
);
const penaltyField = computed(() =>
    freestyle.value ? 'presentation_penalties' : 'accuracy_penalties',
);
const componentField = computed(() =>
    freestyle.value ? 'accuracy_components' : 'presentation_components',
);
const penaltyCriterion = computed(() =>
    freestyle.value ? 'presentation' : 'accuracy',
);
const componentCriterion = computed(() =>
    freestyle.value ? 'accuracy' : 'presentation',
);
const criterionLabels = { accuracy: 'accuracy', presentation: 'presentation' };
const deductedHundredths = computed(
    () =>
        400 -
        (props.form[penaltyField.value] || []).reduce(
            (total, penalty) =>
                total + Math.round(Number(penalty) * 100),
            0,
        ),
);
const componentsHundredths = computed(() => {
    const components = props.form[componentField.value] || [];
    if (
        components.length !== 3 ||
        !components.every((value) => value !== '' && value !== null)
    ) {
        return null;
    }

    return components.reduce(
        (total, value) => total + Math.round(Number(value) * 100),
        0,
    );
});
const accuracyHundredths = computed(() =>
    freestyle.value ? componentsHundredths.value : deductedHundredths.value,
);
const presentationHundredths = computed(() =>
    freestyle.value ? deductedHundredths.value : componentsHundredths.value,
);
const decimal = (value) => (value / 100).toFixed(2);
const tones = computed(() =>
    props.theme === 'dark'
        ? {
              panel: 'border-slate-700 bg-slate-950/60',
              label: 'text-slate-300',
              text: 'text-slate-200',
              heading: 'text-teal-300',
              input: 'border-slate-600 bg-slate-950 text-slate-100',
              componentInput: 'border-slate-600 bg-slate-900 text-slate-100',
              button: 'border-slate-600 bg-slate-800 text-slate-200',
              penalty: 'bg-rose-950 text-rose-200',
              primary: 'bg-teal-600 text-slate-950',
              secondary: 'bg-slate-700 text-slate-100',
          }
        : {
              panel: 'border-slate-200 bg-slate-50',
              label: 'text-slate-700',
              text: 'text-slate-700',
              heading: 'text-teal-700',
              input: 'border-slate-300 bg-white text-slate-900',
              componentInput: 'border-slate-300 bg-white text-slate-900',
              button: 'border-slate-300 bg-white text-slate-700',
              penalty: 'bg-rose-100 text-rose-800',
              primary: 'bg-emerald-700 text-white',
              secondary: 'bg-slate-200 text-slate-800',
          },
);
watch(
    () => [props.form[penaltyField.value], props.form[componentField.value]],
    () => {
        if (detailed.value) {
            props.form.accuracy =
                accuracyHundredths.value === null
                    ? ''
                    : decimal(accuracyHundredths.value);
            props.form.presentation =
                presentationHundredths.value === null
                    ? ''
                    : decimal(presentationHundredths.value);
        }
    },
    { deep: true, immediate: true },
);
watch(
    () => props.resetKey,
    () => (step.value = 'accuracy'),
);
const changeDeductedScore = (amount) => {
    if (props.disabled) {
        return;
    }

    props.form[penaltyField.value] = adjustPenalties(props.form[penaltyField.value], amount);
};
const removePenalty = (index) => {
    if (props.disabled) {
        return;
    }

    props.form[penaltyField.value] = props.form[penaltyField.value].filter((_, position) => position !== index);
};
const changeComponent = (index, amount) => {
    if (props.disabled) {
        return;
    }

    const current = Math.round(Number(props.form[componentField.value][index]) * 100);
    const next = current + amount;
    if (!Number.isFinite(next) || next < 0 || next > 200) {
        return;
    }

    props.form[componentField.value][index] = decimal(next);
};
</script>

<template>
    <!-- Keep every scoring method available through utility classes. -->
    <div class="grid gap-4">
        <div
            v-if="rules.input_method === 'single_score_v1'"
            class="grid gap-3"
        >
            <label :for="`${idPrefix}-score`" class="text-xs" :class="tones.label">
                Total score · out of 10
            </label>
            <input
                :id="`${idPrefix}-score`"
                v-model="form.accuracy"
                type="number"
                :disabled="disabled"
                inputmode="decimal"
                min="0"
                max="10"
                step="0.01"
                required
                dir="ltr"
                class="min-h-12 w-full rounded-lg border px-3 text-center text-xl disabled:opacity-55"
                :class="tones.input"
            />
        </div>
        <div
            v-else-if="detailed"
            class="grid gap-4 rounded-lg border p-4"
            :class="tones.panel"
        >
            <div class="flex flex-wrap items-center justify-between gap-3">
                <b class="text-sm" :class="tones.heading">{{ step === 'accuracy' ? '1. Accuracy' : '2. Presentation' }}</b>
                <span class="text-[11px]" :class="tones.label">
                    Accuracy {{ accuracyHundredths === null ? '—' : decimal(accuracyHundredths) }} out of {{ rules.accuracy_max / 100 }}
                    · Presentation {{ presentationHundredths === null ? '—' : decimal(presentationHundredths) }} out of {{ rules.presentation_max / 100 }}
                </span>
            </div>
            <div v-if="step === penaltyCriterion" class="grid gap-3">
                <p class="text-xs" :class="tones.label">
                    Base {{ criterionLabels[penaltyCriterion] }} is 4.00; use the buttons to adjust it between zero and four.
                </p>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="amount in [-10, -30, 10, 30]"
                        :key="amount"
                        type="button"
                        class="rounded-md border px-3 py-2 text-xs disabled:opacity-50"
                        :class="tones.button"
                        :disabled="disabled || deductedHundredths + amount < 0 || deductedHundredths + amount > 400"
                        @click="changeDeductedScore(amount)"
                    >
                        {{ amount > 0 ? 'Increase' : 'Decrease' }}
                        {{ Math.abs(amount) === 10 ? '0.1' : '0.3' }}
                    </button>
                </div>
                <div v-if="form[penaltyField].length" class="flex flex-wrap gap-2">
                    <button
                        v-for="(penalty, index) in form[penaltyField]"
                        :key="index"
                        type="button"
                        class="rounded-md px-2 py-1 text-xs disabled:opacity-50"
                        :class="tones.penalty"
                        :disabled="disabled"
                        :aria-label="`Undo deduction ${penalty}`"
                        @click="removePenalty(index)"
                    >
                        −{{ penalty }} ×
                    </button>
                </div>
                <p class="text-xs" :class="tones.text">
                    {{ criterionLabels[penaltyCriterion] }} score:
                    <strong>{{ decimal(deductedHundredths) }}</strong>
                </p>
            </div>
            <div v-else class="grid gap-3">
                <p class="text-xs" :class="tones.label">
                    Enter the three {{ criterionLabels[componentCriterion] }} components separately from 0 to 2.
                </p>
                <div class="grid gap-3 sm:grid-cols-3">
                    <div v-for="index in 3" :key="index" class="grid gap-2">
                        <label
                            :for="`${idPrefix}-${componentCriterion}-${index}`"
                            class="text-[11px]"
                            :class="tones.label"
                        >Component {{ index }} · out of 2</label>
                        <input
                            :id="`${idPrefix}-${componentCriterion}-${index}`"
                            v-model="form[componentField][index - 1]"
                            type="number"
                            inputmode="decimal"
                            min="0"
                            max="2"
                            step="0.01"
                            :disabled="disabled"
                            required
                            dir="ltr"
                            class="min-h-11 w-full rounded-md border px-2 text-center disabled:opacity-55"
                            :class="tones.componentInput"
                        />
                        <div class="flex flex-wrap gap-1">
                            <button
                                v-for="amount in [-10, -30, 10, 30]"
                                :key="amount"
                                type="button"
                                class="rounded border px-2 py-1 text-[10px] disabled:opacity-50"
                                :class="tones.button"
                                :disabled="disabled || Number(form[componentField][index - 1]) * 100 + amount < 0 || Number(form[componentField][index - 1]) * 100 + amount > 200"
                                :aria-label="`${amount > 0 ? 'Increase' : 'Decrease'} component ${index} by ${Math.abs(amount) / 100}`"
                                @click="changeComponent(index - 1, amount)"
                            >
                                {{ amount > 0 ? '+' : '−' }}{{ Math.abs(amount) === 10 ? '0.1' : '0.3' }}
                            </button>
                        </div>
                    </div>
                </div>
                <p class="text-xs" :class="tones.text">
                    {{ criterionLabels[componentCriterion] }} total:
                    <strong>{{ componentsHundredths === null ? '—' : decimal(componentsHundredths) }}</strong>
                </p>
            </div>
            <button
                v-if="step === 'accuracy'"
                type="button"
                class="w-fit rounded-lg px-4 py-2 text-xs font-bold disabled:opacity-50"
                :class="tones.primary"
                :disabled="disabled"
                @click="step = 'presentation'"
            >Save accuracy and continue to presentation</button>
            <button
                v-else
                type="button"
                class="w-fit rounded-lg px-4 py-2 text-xs disabled:opacity-50"
                :class="tones.secondary"
                :disabled="disabled"
                @click="step = 'accuracy'"
            >Back to accuracy</button>
        </div>
        <div v-else class="grid gap-3 sm:grid-cols-2">
            <div class="grid gap-2">
                <label :for="`${idPrefix}-accuracy`" class="text-xs" :class="tones.label">
                    Accuracy · out of {{ rules.accuracy_max / 100 }}
                </label>
                <input
                    :id="`${idPrefix}-accuracy`"
                    v-model="form.accuracy"
                    type="number"
                    :disabled="disabled"
                    inputmode="decimal"
                    min="0"
                    :max="rules.accuracy_max / 100"
                    step="0.01"
                    required
                    dir="ltr"
                    class="min-h-12 w-full rounded-lg border px-3 text-center text-xl disabled:opacity-55"
                    :class="tones.input"
                />
            </div>
            <div class="grid gap-2">
                <label :for="`${idPrefix}-presentation`" class="text-xs" :class="tones.label">
                    Presentation · out of {{ rules.presentation_max / 100 }}
                </label>
                <input
                    :id="`${idPrefix}-presentation`"
                    v-model="form.presentation"
                    type="number"
                    :disabled="disabled"
                    inputmode="decimal"
                    min="0"
                    :max="rules.presentation_max / 100"
                    step="0.01"
                    required
                    dir="ltr"
                    class="min-h-12 w-full rounded-lg border px-3 text-center text-xl disabled:opacity-55"
                    :class="tones.input"
                />
            </div>
        </div>
    </div>
</template>
