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
    border: 1px solid #d7dde5;
    border-radius: 10px;
    background: #f7fbf7;
    color: #355b45;
    cursor: pointer;
    font-size: 12px;
    line-height: 1.7;
}

.checkbox-field__input {
    appearance: none;
    display: grid;
    place-content: center;
    box-sizing: border-box;
    width: 25px;
    height: 25px;
    min-height: 0;
    flex: none;
    margin: 5px;
    padding: 0;
    border: 1px solid #94a3b8;
    border-radius: 3px;
    background-color: white;
    cursor: pointer;
}

.checkbox-field__input:checked {
    border-color: transparent;
    background-color: #286957;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='none' stroke='white' stroke-linecap='round' stroke-linejoin='round' stroke-width='2.5' d='m3 8 3.3 3.3L13 4.5'/%3E%3C/svg%3E");
    background-position: center;
    background-repeat: no-repeat;
    background-size: 11px;
}

.checkbox-field__input:focus {
    outline: none;
    box-shadow: none;
}

.checkbox-field__label {
    min-width: 0;
}

.checkbox-field--chip {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 5px 9px;
    border-radius: 8px;
    background: #fff;
    font-size: 11px;
    font-weight: 650;
    line-height: 1.5;
}

.checkbox-field--chip .checkbox-field__input {
    width: 20px;
    height: 20px;
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
