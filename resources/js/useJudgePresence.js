import { onMounted, onUnmounted, ref } from "vue";
import { getRealtime } from "./realtime";

export function useJudgePresence(tournamentId) {
    const onlineUserIds = ref([]);
    let echo;
    onMounted(() => {
        echo = getRealtime();
        if (!echo) return;
        echo.join(`tournaments.${tournamentId}.judges`)
            .here(
                (users) => (onlineUserIds.value = users.map((user) => user.id)),
            )
            .joining((user) => {
                if (!onlineUserIds.value.includes(user.id))
                    onlineUserIds.value = [...onlineUserIds.value, user.id];
            })
            .leaving(
                (user) =>
                    (onlineUserIds.value = onlineUserIds.value.filter(
                        (id) => id !== user.id,
                    )),
            )
            .error(() => (onlineUserIds.value = []));
    });
    onUnmounted(() => echo?.leave(`tournaments.${tournamentId}.judges`));
    return onlineUserIds;
}
