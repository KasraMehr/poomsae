<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    id: { type: String, required: true },
    label: { type: String, required: true },
    modelValue: { type: [String, Number], default: '' },
    options: { type: Array, required: true },
    disabled: { type: Boolean, default: false },
    placeholder: { type: String, default: 'انتخاب کنید' },
});

const emit = defineEmits(['update:modelValue']);
const root = ref(null);
const trigger = ref(null);
const isOpen = ref(false);
const activeIndex = ref(0);
const selectedOption = computed(() => props.options.find(option => option.value === props.modelValue));
const enabledIndexes = computed(() => props.options.map((option, index) => option.disabled ? -1 : index).filter(index => index >= 0));

function openList() {
    if (props.disabled || enabledIndexes.value.length === 0) return;

    const selectedIndex = props.options.findIndex(option => option.value === props.modelValue && !option.disabled);
    activeIndex.value = selectedIndex >= 0 ? selectedIndex : enabledIndexes.value[0];
    isOpen.value = true;
}

function closeList() {
    isOpen.value = false;
}

function selectOption(option) {
    if (option.disabled) return;

    emit('update:modelValue', option.value);
    closeList();
    trigger.value?.focus();
}

function moveActive(amount) {
    const indexes = enabledIndexes.value;
    if (indexes.length === 0) return;

    const currentPosition = indexes.indexOf(activeIndex.value);
    const nextPosition = (currentPosition + amount + indexes.length) % indexes.length;
    activeIndex.value = indexes[nextPosition];
}

function handleKeydown(event) {
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();
        if (!isOpen.value) {
            openList();
        } else {
            moveActive(event.key === 'ArrowDown' ? 1 : -1);
        }
    } else if (event.key === 'Home' && isOpen.value) {
        event.preventDefault();
        activeIndex.value = enabledIndexes.value[0] ?? 0;
    } else if (event.key === 'End' && isOpen.value) {
        event.preventDefault();
        activeIndex.value = enabledIndexes.value.at(-1) ?? 0;
    } else if ((event.key === 'Enter' || event.key === ' ') && isOpen.value) {
        event.preventDefault();
        const option = props.options[activeIndex.value];
        if (option) selectOption(option);
    } else if (event.key === 'Escape' && isOpen.value) {
        event.preventDefault();
        closeList();
    }
}

function handleDocumentPointerDown(event) {
    if (root.value && !root.value.contains(event.target)) closeList();
}

onMounted(() => document.addEventListener('pointerdown', handleDocumentPointerDown));
onBeforeUnmount(() => document.removeEventListener('pointerdown', handleDocumentPointerDown));
</script>

<template>
    <div ref="root" class="relative">
        <label :id="`${id}-label`" :for="`${id}-trigger`" class="mb-1.5 block text-xs font-medium text-[#63756b]">{{ label }}</label>
        <button
            :id="`${id}-trigger`"
            ref="trigger"
            type="button"
            role="combobox"
            aria-haspopup="listbox"
            :aria-labelledby="`${id}-label`"
            :aria-controls="`${id}-listbox`"
            :aria-expanded="isOpen"
            :aria-activedescendant="isOpen ? `${id}-option-${activeIndex}` : undefined"
            :disabled="disabled"
            class="flex w-full items-center justify-between gap-3 rounded-lg border border-[#a8c6ae] bg-gradient-to-b from-white to-[#edf6ee] px-3 py-2.5 text-right text-sm font-semibold text-[#285b46] shadow-sm transition hover:border-[#7da889] focus:border-[#5c9670] focus:outline-none focus:ring-2 focus:ring-[#8bb99a]/35 disabled:cursor-not-allowed disabled:opacity-60"
            @click="isOpen ? closeList() : openList()"
            @keydown="handleKeydown"
        >
            <span :class="selectedOption ? '' : 'text-[#809386]'">{{ selectedOption?.label ?? placeholder }}</span>
            <svg aria-hidden="true" viewBox="0 0 20 20" class="h-4 w-4 shrink-0 text-[#4f7c5c] transition-transform" :class="isOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m5 7.5 5 5 5-5" stroke-linecap="round" stroke-linejoin="round" /></svg>
        </button>
        <ul
            v-if="isOpen"
            :id="`${id}-listbox`"
            role="listbox"
            :aria-labelledby="`${id}-label`"
            class="absolute right-0 z-40 mt-2 max-h-60 w-full overflow-auto rounded-xl border border-[#c8ddcb] bg-white p-1.5 shadow-[0_12px_28px_#1e3c2d24]"
        >
            <li v-for="(option, index) in options" :id="`${id}-option-${index}`" :key="option.value" role="option" :aria-selected="option.value === modelValue" :aria-disabled="option.disabled || undefined">
                <button
                    type="button"
                    class="flex w-full items-center justify-between rounded-lg px-3 py-2.5 text-right text-sm transition-colors disabled:cursor-not-allowed disabled:opacity-45"
                    :class="option.value === modelValue ? 'bg-[#e3f1e5] font-bold text-[#205b42]' : index === activeIndex ? 'bg-[#f1f7f1] text-[#285b46]' : 'text-[#465d4d] hover:bg-[#f1f7f1]'"
                    :disabled="option.disabled"
                    @mouseenter="activeIndex = index"
                    @click="selectOption(option)"
                >
                    <span>{{ option.label }}</span>
                    <svg v-if="option.value === modelValue" aria-hidden="true" viewBox="0 0 20 20" class="h-4 w-4 text-[#286957]" fill="none" stroke="currentColor" stroke-width="2"><path d="m4 10 4 4 8-8" stroke-linecap="round" stroke-linejoin="round" /></svg>
                </button>
            </li>
        </ul>
    </div>
</template>
