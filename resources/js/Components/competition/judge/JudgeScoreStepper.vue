<script setup>
import { computed } from 'vue';
import { scoreHundredths, stepScore } from '../../../Shared/Services/judgeScoring';

const props = defineProps({ modelValue: [String, Number], maximum: Number, label: String, inputId: String, disabled: Boolean });
const emit = defineEmits(['update:modelValue']);
const value = computed(() => scoreHundredths(props.modelValue));
const change = delta => { if (!props.disabled) emit('update:modelValue', stepScore(props.modelValue, delta, props.maximum)); };
</script>

<template>
    <div class="score-stepper">
        <button type="button" :disabled="disabled || value === 0" :aria-label="`Decrease ${label} by 0.1`" @click="change(-10)">−</button>
        <input :id="inputId" :value="modelValue" type="number" min="0" :max="maximum / 100" step="0.01" inputmode="decimal" :aria-label="label" :disabled="disabled" placeholder="—" required @input="emit('update:modelValue', $event.target.value)">
        <button type="button" :disabled="disabled || value >= maximum" :aria-label="`Increase ${label} by 0.1`" @click="change(10)">+</button>
    </div>
</template>

<style scoped>
.score-stepper{display:grid;grid-template-columns:48px minmax(46px,1fr) 48px;gap:5px;min-width:0}
button,input{height:48px;min-width:0;border:1px solid #34465b;border-radius:10px;color:#edf4fc;touch-action:manipulation}
button{background:#26374b;font:600 25px/1 Arial;user-select:none;transition:background .12s}
button:hover:not(:disabled){background:#3a536e}button:active:not(:disabled){background:#496b8d}
button:disabled{opacity:.35;cursor:default}input{width:100%;background:#0a1422;text-align:center;font-size:22px;font-weight:750;font-variant-numeric:tabular-nums;appearance:textfield}
input::-webkit-inner-spin-button,input::-webkit-outer-spin-button{-webkit-appearance:none;margin:0}
:is(button,input):focus-visible{outline:2px solid #8ed8ff;outline-offset:2px}
@media(max-height:500px) and (orientation:landscape){.score-stepper{grid-template-columns:44px minmax(40px,1fr) 44px;gap:4px}button,input{height:44px;border-radius:8px}input{font-size:20px}}
</style>
