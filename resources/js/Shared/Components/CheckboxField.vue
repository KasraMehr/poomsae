<script setup>
import { computed } from 'vue';

const props = defineProps({
    id: { type: String, required: true },
    label: { type: String, required: true },
    modelValue: { type: [Boolean, Array], default: false },
    value: { type: [String, Number], default: null },
    disabled: { type: Boolean, default: false },
    required: { type: Boolean, default: false },
    variant: { type: String, default: 'default', validator: value => ['default', 'chip'].includes(value) },
});

const emit = defineEmits(['update:modelValue']);
const isChecked = computed(() => Array.isArray(props.modelValue)
    ? props.modelValue.includes(props.value)
    : Boolean(props.modelValue));

function updateValue(event) {
    if (Array.isArray(props.modelValue)) {
        const nextValue = event.target.checked
            ? [...props.modelValue, props.value]
            : props.modelValue.filter(value => value !== props.value);

        emit('update:modelValue', nextValue);
        return;
    }

    emit('update:modelValue', event.target.checked);
}
</script>

<template>
    <label
        :for="id"
        class="checkbox-field"
        :class="[`checkbox-field--${variant}`, { 'checkbox-field--checked': isChecked, 'checkbox-field--disabled': disabled }]"
    >
        <input
            :id="id"
            type="checkbox"
            :checked="isChecked"
            :value="value ?? undefined"
            :disabled="disabled"
            :required="required"
            class="checkbox-field__input"
            @change="updateValue"
        >
        <span class="checkbox-field__label">{{ label }}</span>
    </label>
</template>

<style scoped>
.checkbox-field {
    display: flex;
    min-width: 0;
    align-items: center;
    gap: 10px;
    padding: 11px 12px;
    border: 1px solid #d8e6d9;
    border-radius: 10px;
    background: #f7fbf7;
    color: #355b45;
    cursor: pointer;
    font-size: 12px;
    line-height: 1.7;
    transition: border-color 150ms ease, background-color 150ms ease, box-shadow 150ms ease;
}

.checkbox-field:hover {
    background: #f1f8f2;
}

.checkbox-field__input {
    width: 17px;
    height: 17px;
    flex: none;
    margin: 0;
    accent-color: #286957;
    cursor: pointer;
}

.checkbox-field__input:focus-visible {
    outline: 2px solid #5c9670;
    outline-offset: 2px;
}

.checkbox-field:focus-within {
    border-color: #d8e6d9;
    box-shadow: none;
}

.checkbox-field__label {
    min-width: 0;
}

.checkbox-field--checked {
    border-color: #d8e6d9;
    background: #edf6ee;
    color: #205b42;
}

.checkbox-field--chip {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 5px 9px;
    border-color: #d8e6d9;
    border-radius: 8px;
    background: #fff;
    font-size: 11px;
    font-weight: 650;
    line-height: 1.5;
}

.checkbox-field--chip.checkbox-field--checked {
    border-color: #d8e6d9;
}

.checkbox-field--chip .checkbox-field__input {
    width: 14px;
    height: 14px;
    margin-top: 0;
}

.checkbox-field--disabled {
    cursor: not-allowed;
    opacity: 0.58;
}

.checkbox-field--disabled .checkbox-field__input {
    cursor: not-allowed;
}
</style>
