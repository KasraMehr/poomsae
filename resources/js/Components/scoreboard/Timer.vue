<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from "vue";

/**
 * تایمر صفحه Standby — بر اساس start/stop بازی.
 * اگر running باشد از startedAt به‌روز می‌شود؛ در غیر این صورت روی durationSeconds می‌ایستد.
 */
const props = defineProps({
    running: { type: Boolean, default: false },
    startedAt: { type: [Number, String], default: null }, // epoch ms
    durationSeconds: { type: Number, default: 0 },
});

const now = ref(Date.now());
let timerId = null;

const elapsedSeconds = computed(() => {
    if (props.startedAt == null) return props.durationSeconds;
    const start = new Date(props.startedAt).getTime();
    if (Number.isNaN(start)) return props.durationSeconds;
    return Math.max(0, Math.floor((now.value - start) / 1000));
});

const remaining = computed(() => {
    if (props.durationSeconds > 0 && props.running) {
        return Math.max(0, props.durationSeconds - elapsedSeconds.value);
    }
    return props.running ? elapsedSeconds.value : props.durationSeconds;
});

const display = computed(() => {
    const total = remaining.value;
    const minutes = Math.floor(total / 60);
    const seconds = total % 60;
    return `${String(minutes).padStart(2, "0")}:${String(seconds).padStart(2, "0")}`;
});

onMounted(() => {
    timerId = setInterval(() => {
        now.value = Date.now();
    }, 1000);
});

onBeforeUnmount(() => {
    if (timerId) clearInterval(timerId);
});
</script>

<template>
    <span
        class="tabular-nums font-semibold"
        :class="running ? 'text-rtds-gold' : 'text-rtds-text-secondary'"
        >{{ display }}</span
    >
</template>
