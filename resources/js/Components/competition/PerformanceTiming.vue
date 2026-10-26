<script setup>
import { computed } from 'vue';
import { elapsed } from '../../Shared/Services/display';
import { restRemaining, usePerformanceClock } from '../../Shared/Composables/usePerformanceTiming';

const props = defineProps({ performance: Object });
const now = usePerformanceClock(() => props.performance.server_now);
const timing = computed(() => props.performance.timing || {});
const seconds = computed(() => props.performance.started_at
    ? Math.max(0, (
        (props.performance.ended_at ? Date.parse(props.performance.ended_at) : now.value)
        - Date.parse(props.performance.started_at)
    ) / 1000)
    : 0);
const rest = computed(() => restRemaining(props.performance, now.value));
const overtime = computed(() => (
    timing.value.maximum_duration_seconds != null
    && seconds.value > timing.value.maximum_duration_seconds
));
</script>
<template>
    <!-- Keep timing presentation in Tailwind so it works in shared views. -->
    <div
        class="my-[.8rem] text-[.9rem] tabular-nums"
        :class="overtime || timing.violation ? 'text-rose-400' : ''"
        aria-live="off"
    >
        <p v-if="performance.started_at" class="my-[.35rem]">
            Performance time: <b dir="ltr">{{ elapsed(performance, now) }}</b>
            <span v-if="timing.maximum_duration_seconds != null">
                · Allowed range: {{ timing.minimum_duration_seconds || 0 }} to
                {{ timing.maximum_duration_seconds }} seconds
            </span>
        </p>
        <p
            v-if="performance.status === 'running' && timing.maximum_duration_seconds != null"
            class="my-[.35rem]"
        >
            {{ overtime ? 'Overtime' : 'Time remaining' }}:
            {{ Math.ceil(Math.abs(timing.maximum_duration_seconds - seconds)) }} seconds
        </p>
        <p v-if="timing.violation" class="my-[.35rem]">
            {{ timing.violation === 'too_short' ? 'Performance was shorter than allowed.' : 'Performance exceeded the allowed time.' }}
            Time penalty: {{ (timing.penalty_hundredths / 100).toFixed(2) }} points from the final score.
        </p>
        <p
            v-if="performance.status === 'pending' && rest > 0"
            class="my-[.35rem]"
        >
            Athlete rest · {{ rest }} seconds until the next start.
        </p>
    </div>
</template>
