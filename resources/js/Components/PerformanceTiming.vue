<script setup>
import { computed } from 'vue';
import { elapsed } from '../display';
import { restRemaining, usePerformanceClock } from '../usePerformanceTiming';
const props = defineProps({ performance: Object });
const now = usePerformanceClock(() => props.performance.server_now);
const timing = computed(() => props.performance.timing || {});
const seconds = computed(() => props.performance.started_at ? Math.max(0, ((props.performance.ended_at ? Date.parse(props.performance.ended_at) : now.value) - Date.parse(props.performance.started_at)) / 1000) : 0);
const rest = computed(() => restRemaining(props.performance, now.value));
const overtime = computed(() => timing.value.maximum_duration_seconds != null && seconds.value > timing.value.maximum_duration_seconds);
</script>
<template>
    <div class="performance-timing" :class="{ 'timing-warning': overtime || timing.violation }" aria-live="off">
        <p v-if="performance.started_at">زمان اجرا: <b dir="ltr">{{ elapsed(performance, now) }}</b><span v-if="timing.maximum_duration_seconds != null"> · محدودهٔ مجاز: {{ timing.minimum_duration_seconds || 0 }} تا {{ timing.maximum_duration_seconds }} ثانیه</span></p>
        <p v-if="performance.status === 'running' && timing.maximum_duration_seconds != null">{{ overtime ? 'اضافه‌زمان' : 'زمان باقی‌مانده' }}: {{ Math.ceil(Math.abs(timing.maximum_duration_seconds - seconds)) }} ثانیه</p>
        <p v-if="timing.violation">{{ timing.violation === 'too_short' ? 'زمان اجرا کمتر از حد مجاز است.' : 'زمان اجرا از حد مجاز بیشتر است.' }} جریمهٔ زمان: {{ (timing.penalty_hundredths / 100).toFixed(2) }} امتیاز از نمرهٔ نهایی.</p>
        <p v-if="performance.status === 'pending' && rest > 0">استراحت ورزشکار · {{ rest }} ثانیه تا اجازهٔ شروع.</p>
    </div>
</template>
<style scoped>
.performance-timing { margin-block: .8rem; font-size: .9rem; font-variant-numeric: tabular-nums; }
.performance-timing p { margin-block: .35rem; }
.timing-warning { color: #fda4af; }
</style>
