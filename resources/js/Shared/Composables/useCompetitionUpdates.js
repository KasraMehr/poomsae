import { onMounted, onUnmounted, ref } from "vue";
import { router } from "@inertiajs/vue3";
import { getRealtime } from "../Services/realtime";

export function useCompetitionUpdates(tournamentId, only = ["tournament"]) {
    let echo;
    let refreshTimer;
    const state = ref("polling");
    onMounted(() => {
        echo = getRealtime();
        if (echo) {
            state.value = "connecting";
            echo.connector.onConnectionChange((status) => {
                state.value =
                    status === "connected"
                        ? "connected"
                        : status === "connecting"
                          ? "connecting"
                          : "polling";
            });
            state.value =
                echo.connectionStatus() === "connected"
                    ? "connected"
                    : "connecting";
        }
        echo?.private("tournaments." + tournamentId).listen(
            ".competition.updated",
            () => {
                clearTimeout(refreshTimer);
                refreshTimer = setTimeout(
                    () => router.reload({ only, preserveScroll: true }),
                    200,
                );
            },
        );
    });
    onUnmounted(() => {
        clearTimeout(refreshTimer);
        echo?.leave("tournaments." + tournamentId);
    });
    return state;
}
