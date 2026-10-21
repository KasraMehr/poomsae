import { computed, onMounted, onUnmounted, ref } from 'vue';
import { getRealtime } from './realtime';

export function useDisplayUpdates(initialDisplay, endpoint) {
    const display = ref(initialDisplay);
    const now = ref(Date.now());
    const lastReceived = ref(Date.now());
    const accessDenied = ref(false);
    let echo;
    let timer;
    let clock;
    let controller;
    let stopped = true;
    let busy = false;
    let requested = false;

    const connection = computed(() => {
        if (accessDenied.value) return 'denied';
        if (now.value - lastReceived.value > 3000) return 'stale';
        return echo?.connectionStatus() === 'connected' ? 'connected' : 'polling';
    });

    async function refresh() {
        if (stopped || accessDenied.value) return;
        if (busy) { requested = true; return; }
        busy = true;
        requested = false;
        clearTimeout(timer);
        controller = new AbortController();
        const timeout = setTimeout(() => controller?.abort(), 3000);
        let delay = 500;
        try {
            const url = new URL(endpoint, window.location.origin);
            url.searchParams.set('revision', display.value.revision);
            const response = await fetch(url, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin', cache: 'no-store', signal: controller.signal,
            });
            if ([401, 403].includes(response.status) || response.redirected) {
                accessDenied.value = true;
                return;
            }
            if (!response.ok) throw new Error('Display update failed');
            const payload = await response.json();
            if (stopped) return;
            if (payload.changed && payload.data.revision >= display.value.revision) display.value = payload.data;
            lastReceived.value = Date.now();
        } catch {
            delay = 1000;
        } finally {
            clearTimeout(timeout);
            busy = false;
            controller = undefined;
            if (!stopped && !accessDenied.value) timer = setTimeout(refresh, requested ? 0 : delay);
        }
    }

    onMounted(() => {
        stopped = false;
        clock = setInterval(() => now.value = Date.now(), 250);
        echo = getRealtime();
        echo?.private('tournaments.' + initialDisplay.tournament.id).listen('.competition.updated', refresh);
        refresh();
    });
    onUnmounted(() => {
        stopped = true;
        clearTimeout(timer);
        clearInterval(clock);
        controller?.abort();
        echo?.leave('tournaments.' + initialDisplay.tournament.id);
    });
    return { display, now, lastReceived, connection };
}
