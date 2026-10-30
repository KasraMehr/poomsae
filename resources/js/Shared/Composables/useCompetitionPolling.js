import { onMounted, onUnmounted, watch } from 'vue';
import { usePoll } from '@inertiajs/vue3';

// Keep a slow reconciliation poll even when events are arriving, so a missed
// broadcast cannot leave a judge or operator on an old competition state.
export function useCompetitionPolling(connection, only, onSuccess) {
    const options = { only, ...(onSuccess ? { onSuccess } : {}) };
    const fast = usePoll(2000, options, { autoStart: false });
    const reconcile = usePoll(15000, options, { autoStart: false });
    let stopWatching;

    onMounted(() => {
        stopWatching = watch(connection, status => {
            if (status === 'connected') {
                fast.stop();
                reconcile.start();
            } else {
                reconcile.stop();
                fast.start();
            }
        }, { immediate: true });
    });
    onUnmounted(() => stopWatching?.());
}
