import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

// Keep performance clocks aligned with the server while the view is mounted.
export function restRemaining(performance, now) {
    return performance?.rest_ready_at ? Math.max(0, Math.ceil((Date.parse(performance.rest_ready_at) - now) / 1000)) : 0;
}

export function usePerformanceClock(serverTime) {
    const localNow = ref(Date.now());
    const offset = ref(0);
    let interval;
    watch(serverTime, value => {
        if (value) offset.value = Date.parse(value) - Date.now();
        localNow.value = Date.now();
    }, { immediate: true });
    onMounted(() => { interval = setInterval(() => { localNow.value = Date.now(); }, 250); });
    onUnmounted(() => clearInterval(interval));
    return computed(() => localNow.value + offset.value);
}
