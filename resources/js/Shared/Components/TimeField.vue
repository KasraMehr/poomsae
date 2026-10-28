<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    id: { type: String, required: true },
    label: { type: String, required: true },
    modelValue: { type: String, default: '' },
    required: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);
const root = ref(null);
const trigger = ref(null);
const isOpen = ref(false);
const selectedHour = ref('08');
const selectedMinute = ref('00');
const hours = Array.from({ length: 24 }, (_, value) => String(value).padStart(2, '0'));
const minutes = Array.from({ length: 60 }, (_, value) => String(value).padStart(2, '0'));
const numberFormatter = new Intl.NumberFormat('fa-IR', { minimumIntegerDigits: 2, useGrouping: false });
const displayValue = computed(() => props.modelValue
    ? props.modelValue.split(':').map(part => numberFormatter.format(Number(part))).join(':')
    : 'انتخاب ساعت');

watch(() => props.modelValue, value => {
    const match = /^(\d{2}):(\d{2})$/.exec(value || '');
    if (match) {
        selectedHour.value = match[1];
        selectedMinute.value = match[2];
    }
}, { immediate: true });

function openPicker() {
    if (!props.disabled) isOpen.value = true;
}

function closePicker() {
    isOpen.value = false;
}

function selectHour(hour) {
    selectedHour.value = hour;
    emit('update:modelValue', `${hour}:${selectedMinute.value}`);
}

function selectMinute(minute) {
    selectedMinute.value = minute;
    emit('update:modelValue', `${selectedHour.value}:${minute}`);
    closePicker();
    trigger.value?.focus();
}

function handleKeydown(event) {
    if (event.key === 'Escape' && isOpen.value) {
        event.preventDefault();
        closePicker();
        trigger.value?.focus();
    }
}

function handleDocumentPointerDown(event) {
    if (root.value && !root.value.contains(event.target)) closePicker();
}

onMounted(() => document.addEventListener('pointerdown', handleDocumentPointerDown));
onBeforeUnmount(() => document.removeEventListener('pointerdown', handleDocumentPointerDown));
</script>

<template>
    <div ref="root" class="relative" @keydown="handleKeydown">
        <label :id="`${id}-label`" :for="`${id}-trigger`" class="mb-1.5 block text-xs font-medium text-[#63756b]">{{ label }}</label>
        <button
            :id="`${id}-trigger`"
            ref="trigger"
            type="button"
            role="combobox"
            aria-haspopup="dialog"
            :aria-labelledby="`${id}-label`"
            :aria-controls="`${id}-picker`"
            :aria-expanded="isOpen"
            :disabled="disabled"
            class="flex w-full items-center justify-between gap-3 rounded-lg border border-[#a8c6ae] bg-gradient-to-b from-white to-[#edf6ee] px-3 py-2.5 text-left text-sm font-semibold text-[#285b46] shadow-sm transition hover:border-[#7da889] focus:border-[#5c9670] focus:outline-none focus:ring-2 focus:ring-[#8bb99a]/35 disabled:cursor-not-allowed disabled:opacity-60"
            dir="ltr"
            @click="isOpen ? closePicker() : openPicker()"
            @keydown.arrow-down.prevent="openPicker"
            @keydown.enter.prevent="openPicker"
            @keydown.space.prevent="openPicker"
        >
            <span>{{ displayValue }}</span>
            <svg aria-hidden="true" viewBox="0 0 20 20" class="h-4 w-4 shrink-0 text-[#4f7c5c]" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="10" cy="10" r="7"/><path d="M10 5.8v4.6l3 1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
        <input
            type="time"
            tabindex="-1"
            aria-hidden="true"
            :value="modelValue"
            :required="required"
            :disabled="disabled"
            class="pointer-events-none absolute h-px w-px overflow-hidden opacity-0"
            @invalid.prevent="trigger?.focus()"
        >
        <div
            v-if="isOpen"
            :id="`${id}-picker`"
            role="dialog"
            :aria-labelledby="`${id}-picker-title`"
            class="absolute right-0 z-40 mt-2 w-full min-w-[18rem] rounded-xl border border-[#c8ddcb] bg-white p-4 shadow-[0_12px_28px_#1e3c2d24]"
        >
            <h3 :id="`${id}-picker-title`" class="mb-3 text-right text-sm font-bold text-[#285b46]">انتخاب ساعت</h3>
            <div class="grid grid-cols-2 gap-3" dir="rtl">
                <section aria-label="انتخاب ساعت">
                    <h4 class="mb-2 text-center text-xs font-semibold text-[#71877a]">ساعت</h4>
                    <div class="time-options">
                        <button
                            v-for="hour in hours"
                            :key="hour"
                            type="button"
                            :aria-pressed="selectedHour === hour"
                            class="time-option"
                            :class="selectedHour === hour ? 'time-option--selected' : ''"
                            @click="selectHour(hour)"
                        >{{ numberFormatter.format(Number(hour)) }}</button>
                    </div>
                </section>
                <section aria-label="انتخاب دقیقه">
                    <h4 class="mb-2 text-center text-xs font-semibold text-[#71877a]">دقیقه</h4>
                    <div class="time-options">
                        <button
                            v-for="minute in minutes"
                            :key="minute"
                            type="button"
                            :aria-pressed="selectedMinute === minute"
                            class="time-option"
                            :class="selectedMinute === minute ? 'time-option--selected' : ''"
                            @click="selectMinute(minute)"
                        >{{ numberFormatter.format(Number(minute)) }}</button>
                    </div>
                </section>
            </div>
        </div>
    </div>
</template>

<style scoped>
.time-options {
    display: flex;
    flex-direction: column;
    max-height: 12rem;
    gap: 2px;
    overflow-y: auto;
    padding: 3px;
    border: 1px solid #e2ece3;
    border-radius: 10px;
    background: #f7fbf7;
    scrollbar-color: #a8c6ae #edf6ee;
    scrollbar-width: thin;
}

.time-option {
    width: 100%;
    flex: none;
    min-height: 31px;
    border: 1px solid transparent;
    border-radius: 7px;
    background: #fff;
    color: #465d4d;
    font-size: 12px;
    transition: background-color 140ms ease, color 140ms ease;
}

.time-option:hover,
.time-option:focus-visible {
    outline: none;
    background: #eaf4ec;
    color: #205b42;
}

.time-option--selected {
    border-color: #286957;
    background: #286957;
    color: #fff;
    font-weight: 700;
}

.time-option--selected:hover,
.time-option--selected:focus-visible {
    background: #205744;
    color: #fff;
}
</style>
