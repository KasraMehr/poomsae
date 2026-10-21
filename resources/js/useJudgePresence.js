import { onMounted, onUnmounted, ref } from 'vue';
import { getRealtime } from './realtime';

export function useJudgePresence(tournamentId) {
    const onlineUserIds = ref([]);
    let echo;
    onMounted(() => {
        echo = getRealtime();
        if (!echo) return;
        echo.join(`tournaments.${tournamentId}.judges`)
            .here(users => onlineUserIds.value = users.map(user => Number(user.id)))
            .joining(user => {
                const id = Number(user.id);
                if (!onlineUserIds.value.includes(id)) onlineUserIds.value = [...onlineUserIds.value, id];
            })
            .leaving(user => onlineUserIds.value = onlineUserIds.value.filter(id => id !== Number(user.id)))
            .error(() => onlineUserIds.value = []);
    });
    onUnmounted(() => echo?.leave(`tournaments.${tournamentId}.judges`));
    return onlineUserIds;
}
