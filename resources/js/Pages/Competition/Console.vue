<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import OperatorWorkspace from '../../Components/competition/operator/OperatorWorkspace.vue';
import { useCompetitionUpdates } from '../../Shared/Composables/useCompetitionUpdates';
import { useCompetitionPolling } from '../../Shared/Composables/useCompetitionPolling';
import { useJudgePresence } from '../../Shared/Composables/useJudgePresence';

const props = defineProps({ console: Object, can: Object, competitionUrls: Object });
const tournament = computed(() => props.console.tournament);
const connection = useCompetitionUpdates(tournament.value.id, ['console']);
const onlineJudgeIds = useJudgePresence(tournament.value.id);
useCompetitionPolling(connection, ['console', 'can']);
</script>

<template>
    <Head :title="`Operator workspace · ${tournament.name}`" />
    <OperatorWorkspace :console="props.console" :can="can" :urls="competitionUrls" :online-judge-ids="onlineJudgeIds" :connection="connection" />
</template>
