import { onMounted, onUnmounted, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { getRealtime } from './Shared/Services/realtime';

export function useCompetitionUpdates(tournamentId, only = ['tournament']) {
    let echo;
    let refreshTimer;
    let stopConnectionListener;
    const state = ref('polling');
    const refresh = () => {
        clearTimeout(refreshTimer);
        refreshTimer = setTimeout(() => router.reload({ only, preserveScroll: true }), 200);
    };
    onMounted(() => {
        echo = getRealtime();
        if (echo) {
            state.value = 'connecting';
            stopConnectionListener = echo.connector.onConnectionChange(status => {
                state.value = status === 'connected' ? 'connected' : status === 'connecting' ? 'connecting' : 'polling';
                if (status === 'connected') refresh();
            });
            state.value = echo.connectionStatus() === 'connected' ? 'connected' : 'connecting';
        }
        echo?.private('tournaments.' + tournamentId).listen('.competition.updated', refresh);
    });
    onUnmounted(() => {
        clearTimeout(refreshTimer);
        stopConnectionListener?.();
        echo?.leave('tournaments.' + tournamentId);
    });
    return state;
}
