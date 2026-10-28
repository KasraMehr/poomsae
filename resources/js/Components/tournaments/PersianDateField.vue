<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import {
    currentPersianDateParts,
    formatGregorianIsoAsPersian,
    gregorianIsoToPersianParts,
    persianDateToGregorianIso,
    persianMonthDays,
} from '../../Shared/Services/persianDate.js';

const props = defineProps({
    id: { type: String, required: true },
    label: { type: String, required: true },
    modelValue: { type: String, default: '' },
    minIso: { type: String, default: '' },
    required: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);
const months = [
    'فروردین',
    'اردیبهشت',
    'خرداد',
    'تیر',
    'مرداد',
    'شهریور',
    'مهر',
    'آبان',
    'آذر',
    'دی',
    'بهمن',
    'اسفند',
];
const weekdays = ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج'];
const persianMonthFormatter = new Intl.DateTimeFormat('fa-IR-u-ca-persian', {
    year: 'numeric',
    month: 'long',
    timeZone: 'UTC',
});
const persianNumberFormatter = new Intl.NumberFormat('fa-IR', { useGrouping: false });

const input = ref(null);
const root = ref(null);
const inputValue = ref(props.modelValue ? formatGregorianIsoAsPersian(props.modelValue) : '');
const isEditing = ref(false);
const isOpen = ref(false);
const initialParts = props.modelValue
    ? gregorianIsoToPersianParts(props.modelValue)
    : currentPersianDateParts();
const visibleYear = ref(initialParts.year);
const visibleMonth = ref(initialParts.month);
const monthDays = computed(() => persianMonthDays(visibleYear.value, visibleMonth.value));
const calendarCells = computed(() => {
    const firstDay = monthDays.value[0];
    const weekdayOffset = firstDay
        ? (new Date(`${firstDay.iso}T12:00:00Z`).getUTCDay() + 1) % 7
        : 0;

    return [...Array(weekdayOffset).fill(null), ...monthDays.value];
});
const calendarTitle = computed(() => {
    const firstDay = monthDays.value[0];

    return firstDay
        ? persianMonthFormatter.format(new Date(`${firstDay.iso}T12:00:00Z`))
        : `${months[visibleMonth.value - 1]} ${persianNumberFormatter.format(visibleYear.value)}`;
});

watch(
    () => props.modelValue,
    (value) => {
        const parts = value ? gregorianIsoToPersianParts(value) : null;

        if (!isEditing.value) {
            inputValue.value = value ? formatGregorianIsoAsPersian(value) : '';
        }

        if (parts) {
            visibleYear.value = parts.year;
            visibleMonth.value = parts.month;
        }
    },
    { immediate: true },
);

function toLatinDigits(value) {
    return value
        .replace(/[۰-۹]/g, (digit) => String('۰۱۲۳۴۵۶۷۸۹'.indexOf(digit)))
        .replace(/[٠-٩]/g, (digit) => String('٠١٢٣٤٥٦٧٨٩'.indexOf(digit)));
}

function validateAndEmit(value) {
    const normalized = toLatinDigits(value.trim());

    if (!normalized) {
        input.value?.setCustomValidity('');
        emit('update:modelValue', '');

        return;
    }

    const match = /^(?:(\d{4})[/.\-](\d{1,2})[/.\-](\d{1,2})|(\d{4})(\d{2})(\d{2}))$/.exec(normalized);
    const isoDate = match
        ? persianDateToGregorianIso(
              Number(match[1] ?? match[4]),
              Number(match[2] ?? match[5]),
              Number(match[3] ?? match[6]),
          )
        : null;

    if (!isoDate) {
        input.value?.setCustomValidity('تاریخ شمسی را به شکل سال/ماه/روز وارد کنید.');
        emit('update:modelValue', '');

        return;
    }

    if (props.minIso && isoDate < props.minIso) {
        input.value?.setCustomValidity('تاریخ پایان نمی‌تواند پیش از تاریخ شروع باشد.');
    } else {
        input.value?.setCustomValidity('');
    }

    emit('update:modelValue', isoDate);
}

function updateInput(event) {
    isEditing.value = true;
    inputValue.value = event.target.value;
    validateAndEmit(inputValue.value);
}

function finishEditing() {
    isEditing.value = false;

    if (props.modelValue) {
        inputValue.value = formatGregorianIsoAsPersian(props.modelValue);
    }
}

function openCalendar() {
    isOpen.value = true;

    if (!props.modelValue) {
        const current = currentPersianDateParts();
        visibleYear.value = current.year;
        visibleMonth.value = current.month;
    }
}

function handleDocumentPointerDown(event) {
    if (root.value && !root.value.contains(event.target)) {
        isOpen.value = false;
    }
}

function moveMonth(amount) {
    const next = new Date(Date.UTC(visibleYear.value, visibleMonth.value - 1 + amount, 1));
    visibleYear.value = next.getUTCFullYear();
    visibleMonth.value = next.getUTCMonth() + 1;
}

function selectDate(date) {
    if (props.minIso && date.iso < props.minIso) {
        return;
    }

    isEditing.value = false;
    inputValue.value = formatGregorianIsoAsPersian(date.iso);
    input.value?.setCustomValidity('');
    emit('update:modelValue', date.iso);
    isOpen.value = false;
}

function isDateDisabled(date) {
    return Boolean(props.minIso && date.iso < props.minIso);
}

function isDateSelected(date) {
    return date.iso === props.modelValue;
}

watch(() => props.minIso, () => {
    if (inputValue.value) {
        validateAndEmit(inputValue.value);
    }
});

onMounted(() => {
    document.addEventListener('pointerdown', handleDocumentPointerDown);
});

onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', handleDocumentPointerDown);
});
</script>

<template>
    <div ref="root" class="relative">
        <label :for="id" class="mb-1.5 block text-xs font-medium text-[#63756b]">{{ label }}</label>
        <div class="relative">
            <input
                :id="id"
                ref="input"
                :value="inputValue"
                type="text"
                inputmode="numeric"
                dir="ltr"
                :required="required"
                :placeholder="required ? '۱۴۰۵/۰۱/۰۱' : 'انتخاب تاریخ'"
                :aria-controls="isOpen ? `${id}-calendar` : undefined"
                :aria-expanded="isOpen"
                autocomplete="off"
                class="w-full rounded-lg border border-[#dce5da] bg-white py-2.5 pl-10 pr-3 text-left text-sm text-[#253c31] placeholder:text-[#a5aea6] focus:border-[#79a28b] focus:outline-2 focus:outline-[#79a28b]/30"
                @input="updateInput"
                @blur="finishEditing"
                @focus="openCalendar"
                @keydown.esc.prevent="isOpen = false"
            >
            <svg aria-hidden="true" viewBox="0 0 20 20" class="pointer-events-none absolute left-3 top-1/2 h-[18px] w-[18px] -translate-y-1/2 text-[#7c9181]" fill="none" stroke="currentColor" stroke-width="1.6">
                <rect x="3" y="4.5" width="14" height="13" rx="2" />
                <path d="M6.5 3v3M13.5 3v3M3 8.5h14" stroke-linecap="round" />
            </svg>
        </div>

        <div
            v-if="isOpen"
            :id="`${id}-calendar`"
            class="absolute right-0 z-30 mt-2 w-[min(100%,20rem)] rounded-xl border border-[#e1e9e1] bg-white p-4 shadow-[0_12px_32px_#1e3c2d24]"
            role="dialog"
            :aria-label="`انتخاب ${label}`"
            @keydown.esc.prevent="isOpen = false"
        >
            <div class="mb-3 flex items-center justify-between">
                <button type="button" class="grid h-8 w-8 place-items-center rounded-lg text-lg text-[#60796b] hover:bg-[#f1f6f1]" aria-label="ماه بعد" @click="moveMonth(1)">‹</button>
                <strong class="text-sm font-bold text-[#344b3e]">{{ calendarTitle }}</strong>
                <button type="button" class="grid h-8 w-8 place-items-center rounded-lg text-lg text-[#60796b] hover:bg-[#f1f6f1]" aria-label="ماه قبل" @click="moveMonth(-1)">›</button>
            </div>
            <div class="grid grid-cols-7 gap-1 text-center text-[10px] font-medium text-[#87948a]">
                <span v-for="(weekday, index) in weekdays" :key="index" class="py-1">{{ weekday }}</span>
            </div>
            <div class="grid grid-cols-7 gap-1 text-center">
                <span
                    v-for="(date, index) in calendarCells"
                    :key="date ? date.iso : `blank-${index}`"
                    class="grid h-9 place-items-center"
                >
                    <button
                        v-if="date"
                        type="button"
                        :disabled="isDateDisabled(date)"
                        :aria-pressed="isDateSelected(date)"
                        :aria-label="`${persianNumberFormatter.format(date.day)} ${months[visibleMonth - 1]} ${persianNumberFormatter.format(visibleYear)}`"
                        class="grid h-9 w-9 place-items-center rounded-lg text-xs text-[#465d4d] transition-colors hover:bg-[#edf4ee] disabled:cursor-not-allowed disabled:text-[#c6cec6] disabled:hover:bg-transparent"
                        :class="isDateSelected(date) ? 'bg-[#286957] font-bold text-white hover:bg-[#1b5142]' : ''"
                        @mousedown.prevent
                        @click="selectDate(date)"
                    >{{ persianNumberFormatter.format(date.day) }}</button>
                </span>
            </div>
        </div>
    </div>
</template>
