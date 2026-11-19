<script setup>
import { computed, provide, ref, watch } from "vue";
import { Link, useForm, usePage } from "@inertiajs/vue3";
import DropdownSelect from "../../../Shared/Components/DropdownSelect.vue";
import CourtConsole from "./CourtConsole.vue";
import ManualScorePanel from "./ManualScorePanel.vue";
import BoutPanel from "../BoutPanel.vue";
import SetupStage from "./SetupStage.vue";
import LiveStage from "./LiveStage.vue";
import ResultStage from "./ResultStage.vue";
import ConfirmedStage from "./ConfirmedStage.vue";
import { displayLabel } from "./displayLabel";

const props = defineProps({
    console: Object,
    can: Object,
    urls: Object,
    onlineJudgeIds: Array,
    connection: String,
});
const page = usePage();
const courtId = ref(props.console.courts[0]?.id ?? null);
const courtOptions = computed(() =>
    props.console.courts.map((item) => ({ value: item.id, label: item.name })),
);
const matchesOpen = ref(true);
const mobileMatchesOpen = ref(false);
const eventsOpen = ref(false);
const matchId = ref(null);
const activeStage = ref("setup");
const stageByMatch = ref({});
const stages = [
    { id: "setup", label: "SETUP" },
    { id: "live", label: "LIVE" },
    { id: "result", label: "RESULT" },
    { id: "confirmed", label: "CONFIRMED" },
];
const stageComponents = {
    setup: SetupStage,
    live: LiveStage,
    result: ResultStage,
    confirmed: ConfirmedStage,
};
const selectedStageComponent = computed(
    () => stageComponents[activeStage.value],
);
const court = computed(
    () =>
        props.console.courts.find((item) => item.id === courtId.value) ??
        props.console.courts[0],
);
const matches = computed(() => court.value?.matches ?? []);
const matchGroups = computed(() => {
    const groups = new Map();

    for (const item of matches.value) {
        const categoryLabel =
            displayLabel(item.category_name) || "Unassigned division";
        const divisionId = item.category_id ?? categoryLabel;
        const key = String(divisionId);

        if (!groups.has(key)) {
            groups.set(key, {
                key,
                categoryLabel,
                matches: [],
            });
        }

        groups.get(key).matches.push(item);
    }

    return [...groups.values()];
});
const match = computed(
    () =>
        matches.value.find((item) => item.id === matchId.value) ??
        matches.value.find(
            (item) => item.id === court.value?.active?.bout_id,
        ) ??
        matches.value.find(
            (item) => item.id === court.value?.queue?.[0]?.bout_id,
        ) ??
        matches.value[0],
);
watch(courtId, () => {
    matchId.value = null;
});
const selectMatch = (id) => {
    matchId.value = id;
    mobileMatchesOpen.value = false;
};
const category = computed(() =>
    props.console.tournament.categories.find(
        (item) => item.id === match.value?.category_id,
    ),
);
const round = computed(() =>
    category.value?.rounds.find((item) => item.id === match.value?.round_id),
);
const bout = computed(() =>
    round.value?.bouts.find((item) => item.id === match.value?.id),
);
const performance = computed(() => {
    if (court.value?.active?.bout_id === match.value?.id) {
        return court.value.active;
    }

    const queuedPerformance = court.value?.queue?.find(
        (item) => item.bout_id === match.value?.id,
    );
    if (queuedPerformance) {
        return queuedPerformance;
    }

    const orderedPerformances = [...(bout.value?.performances ?? [])].sort(
        (left, right) =>
            left.form_number - right.form_number || left.id - right.id,
    );

    return (
        orderedPerformances.find((item) =>
            ["running", "scoring"].includes(item.status),
        ) ??
        orderedPerformances.find((item) => item.status === "pending") ??
        orderedPerformances[orderedPerformances.length - 1]
    );
});
const divisionContextLabel = computed(() =>
    [displayLabel(category.value?.name), category.value?.display_label]
        .filter(Boolean)
        .join(" · "),
);
const roundContextLabel = computed(() => {
    if (!round.value) return "";

    const stage = displayLabel(round.value.stage_label || round.value.name);
    const roundNumber = round.value.sequence
        ? `ROUND ${round.value.sequence}`
        : "";

    return stage?.toUpperCase() === roundNumber
        ? stage
        : [stage, roundNumber].filter(Boolean).join(" · ");
});
const formContextLabel = computed(() => {
    if (
        category.value?.discipline === "freestyle" ||
        !["pending", "running", "scoring"].includes(performance.value?.status)
    ) {
        return "";
    }

    const formNumber = performance.value?.form_number;
    const formName = displayLabel(performance.value?.form_name);
    const numberLabel = formNumber == null
        ? ""
        : `FORM ${formNumber}${category.value?.forms_per_round > 1 ? ` / ${category.value.forms_per_round}` : ""}`;

    return [numberLabel, formName].filter(Boolean).join(" · ");
});
const stageForStatus = (status) =>
    ({
        pending: "setup",
        running: "live",
        scoring: "result",
        approved: "confirmed",
        completed: "confirmed",
        cancelled: "confirmed",
    })[status] ?? "setup";
const currentStage = computed(() => stageForStatus(performance.value?.status));
const saveStageForMatch = (matchKey, selected, lifecycle) => {
    if (!matchKey) return;
    stageByMatch.value = {
        ...stageByMatch.value,
        [matchKey]: { selected, lifecycle },
    };
};
const selectStage = (stage) => {
    activeStage.value = stage;
    saveStageForMatch(match.value?.id, stage, currentStage.value);
};
let observedMatchId = null;
watch(
    [() => match.value?.id, currentStage],
    ([matchKey, lifecycle]) => {
        if (!matchKey) return;

        const remembered = stageByMatch.value[matchKey];
        if (matchKey !== observedMatchId) {
            activeStage.value = remembered
                ? remembered.selected === remembered.lifecycle &&
                  remembered.lifecycle !== lifecycle
                    ? lifecycle
                    : remembered.selected
                : lifecycle;
        } else if (
            remembered &&
            remembered.selected === remembered.lifecycle &&
            remembered.lifecycle !== lifecycle
        ) {
            activeStage.value = lifecycle;
        }

        saveStageForMatch(matchKey, activeStage.value, lifecycle);
        observedMatchId = matchKey;
    },
    { immediate: true },
);
const action = useForm({ command: "", expected_version: 1 });
const completion = useForm({});
const operatorToast = ref(null);
let operatorToastTimer = null;
const showOperatorToast = (type, message) => {
    operatorToast.value = { type, message };
    window.clearTimeout(operatorToastTimer);
    operatorToastTimer = window.setTimeout(() => {
        operatorToast.value = null;
    }, 3600);
};
provide("operatorToast", showOperatorToast);
const detailsDialog = ref(null);
const manualDialog = ref(null);
const openDetailsDialog = () => detailsDialog.value?.showModal();
const openManualDialog = () => manualDialog.value?.showModal();
watch([courtId, matchId], () => manualDialog.value?.close());
const manualPerformance = computed(() =>
    court.value?.active?.id === performance.value?.id &&
    performance.value?.status === "scoring"
        ? performance.value
        : null,
);
const judges = computed(() =>
    (bout.value?.judges ?? []).map((judge) => ({
        ...judge,
        connected:
            props.connection === "connected"
                ? props.onlineJudgeIds.includes(judge.user_id)
                : null,
        submitted:
            performance.value?.submitted_seats?.includes(judge.seat) ?? false,
        pendingReview:
            performance.value?.pending_review_seats?.includes(judge.seat) ??
            false,
    })),
);
const judgeDevicePresence = computed(() => {
    const states = judges.value.map((judge) => judge.connected);

    if (states.length > 0 && states.every((state) => state === true)) {
        return "online";
    }

    if (states.length > 0 && states.every((state) => state === false)) {
        return "offline";
    }

    return "partial";
});
const command = (value) => {
    if (!performance.value) return;
    action.command = value;
    action.expected_version = performance.value.version;
    action.post(
        `${props.urls.base}/performances/${performance.value.id}/command`,
        { preserveScroll: true },
    );
};
const statusLabels = {
    pending: "READY",
    running: "LIVE",
    scoring: "SCORING",
    approved: "APPROVED",
    completed: "COMPLETED",
    cancelled: "CANCELLED",
};
const eventLabels = {
    "performance.start": "Performance started",
    "performance.finish": "Performance finished",
    "performance.approve": "Result approved and published",
    "score.submitted": "Judge score submitted",
    "score.proxy_submitted": "Manual score submitted for review",
    "score.proxy_confirmed": "Manual score confirmed",
    "bout.completed": "Match completed",
    "round.completed": "Round completed",
    "round.scheduled": "Round scheduled",
    "entry.created": "Athlete registered",
    "entry.status": "Athlete status changed",
    "category.saved": "Division configured",
    "court.created": "Court created",
    "member.added": "Judge assigned",
    "tournament.created": "Competition created",
};
const time = (value) =>
    value
        ? new Date(value).toLocaleTimeString("en-GB", {
              hour: "2-digit",
              minute: "2-digit",
              second: "2-digit",
          })
        : "—";
</script>

<template>
    <div class="operator-workspace" dir="ltr">
        <header class="operator-topbar">
            <strong class="operator-brand">POOMSAE<span>CONTROL</span></strong>
            <nav aria-label="Competition sections">
                <Link :href="page.props.urls.tournaments">COMPETITIONS</Link
                ><span class="operator-tab active">SCORING</span>
                <button
                    v-if="court"
                    type="button"
                    class="operator-matches-toggle"
                    :aria-expanded="mobileMatchesOpen"
                    aria-controls="matches"
                    @click="mobileMatchesOpen = !mobileMatchesOpen"
                >
                    List of matches
                </button>
            </nav>
            <div class="operator-top-actions" dir="rtl">
                <div class="operator-profile-badge" dir="ltr">
                    <span class="operator-profile-avatar" aria-hidden="true">{{
                        page.props.auth.user.name.charAt(0)
                    }}</span>
                    <span class="operator-profile-name"
                        ><strong>{{ page.props.auth.user.name }}</strong
                        ><small>OPERATOR</small></span
                    >
                    <Link
                        :href="page.props.urls.logout"
                        method="post"
                        as="button"
                        class="operator-logout"
                        aria-label="Log out"
                        title="Log out"
                        ><svg
                            aria-hidden="true"
                            viewBox="0 0 20 20"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.7"
                        >
                            <path
                                d="M8 4H4.8a.8.8 0 0 0-.8.8v10.4a.8.8 0 0 0 .8.8H8M12 6l4 4-4 4m4-4H7"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            /></svg
                    ></Link>
                </div>
                <button
                    type="button"
                    class="operator-events-toggle"
                    :aria-expanded="eventsOpen"
                    aria-controls="operator-events-panel"
                    :aria-label="
                        eventsOpen
                            ? 'Close recent events panel'
                            : 'Open recent events panel'
                    "
                    :title="
                        eventsOpen
                            ? 'Close recent events'
                            : 'Open recent events'
                    "
                    @click="eventsOpen = !eventsOpen"
                >
                    <svg
                        aria-hidden="true"
                        viewBox="0 0 20 20"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                        :class="{ reversed: !eventsOpen }"
                    >
                        <path
                            d="m7 4 6 6-6 6"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        /></svg
                    ><span>EVENTS</span>
                </button>
                <DropdownSelect
                    id="operator-court"
                    class="operator-court-select"
                    variant="operator"
                    label="COURT"
                    v-model="courtId"
                    :options="courtOptions"
                />
            </div>
        </header>

        <main
            class="operator-columns"
            :class="{
                'matches-collapsed': !matchesOpen,
                'events-collapsed': !eventsOpen,
                'mobile-match-drawer-open': mobileMatchesOpen,
            }"
            v-if="court"
        >
            <button
                v-if="mobileMatchesOpen"
                type="button"
                class="operator-match-drawer-backdrop"
                aria-label="Close list of matches"
                @click="mobileMatchesOpen = false"
            ></button>
            <aside
                id="matches"
                class="operator-panel operator-match-list"
                :class="{
                    'match-list-collapsed': !matchesOpen,
                    'mobile-drawer-open': mobileMatchesOpen,
                }"
                aria-label="Match list"
            >
                <button
                    class="operator-panel-head operator-match-head"
                    type="button"
                    :aria-expanded="matchesOpen"
                    aria-controls="operator-match-list-content"
                    :aria-label="
                        matchesOpen ? 'Collapse matches' : 'Expand matches'
                    "
                    @click="matchesOpen = !matchesOpen"
                >
                    <strong>MATCHES</strong
                    ><span class="operator-match-head-actions"
                        ><span>{{ matches.length }}</span
                        ><svg
                            aria-hidden="true"
                            viewBox="0 0 20 20"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            :class="{ reversed: matchesOpen }"
                        >
                            <path
                                d="m7 4 6 6-6 6"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            /></svg
                    ></span>
                </button>
                <div
                    id="operator-match-list-content"
                    class="operator-match-content"
                >
                    <div class="operator-match-scroll">
                        <section
                            v-for="group in matchGroups"
                            :key="group.key"
                            class="operator-match-group"
                        >
                            <h3 class="operator-match-group-title">
                                <span>{{ group.categoryLabel }}</span>
                            </h3>
                            <button
                                v-for="item in group.matches"
                                :key="item.id"
                                type="button"
                                class="operator-match"
                                :class="{
                                    selected: match?.id === item.id,
                                    ready: item.status === 'pending',
                                    live: item.status === 'running',
                                    completed: item.status === 'completed',
                                    cancelled: item.status === 'cancelled',
                                }"
                                @click="selectMatch(item.id)"
                            >
                                <span class="operator-match-number"
                                    >No.{{ item.sequence }}</span
                                ><span
                                    class="operator-match-status"
                                    :class="{
                                        ready: item.status === 'pending',
                                        live: item.status === 'running',
                                        completed: item.status === 'completed',
                                        cancelled: item.status === 'cancelled',
                                    }"
                                    ><span
                                        v-if="item.status === 'running'"
                                        class="operator-match-live-dot"
                                        aria-hidden="true"
                                    ></span
                                    >{{
                                        statusLabels[item.status] ?? item.status
                                    }}</span
                                >
                                <strong>{{
                                    item.entries
                                        .map(
                                            (entry) =>
                                                entry.short_name || entry.name,
                                        )
                                        .join("  /  ") || "No athletes assigned"
                                }}</strong>
                            </button>
                        </section>
                        <p v-if="!matches.length" class="operator-empty">
                            No matches are scheduled on this court.
                        </p>
                    </div>
                    <div class="operator-hardware">
                        <button
                            v-if="!matchesOpen"
                            type="button"
                            class="operator-judge-devices-toggle"
                            :aria-expanded="matchesOpen"
                            aria-controls="operator-match-list-content"
                            @click="matchesOpen = true"
                        >
                            <span>JUDGE DEVICES</span
                            ><span
                                class="operator-judge-devices-indicator"
                                :class="judgeDevicePresence"
                                :title="
                                    judgeDevicePresence === 'online'
                                        ? 'All judges online'
                                        : judgeDevicePresence === 'offline'
                                          ? 'All judges offline'
                                          : 'Some judges offline or presence unknown'
                                "
                            ></span
                            ><svg
                                aria-hidden="true"
                                viewBox="0 0 20 20"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                            >
                                <path
                                    d="m7 4 6 6-6 6"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                />
                            </svg></button
                        ><template v-else
                            ><div>
                                <span>JUDGE DEVICES</span
                                ><b>{{
                                    connection === "connected"
                                        ? `${judges.filter((judge) => judge.connected).length} / ${judges.length} CONNECTED`
                                        : "PRESENCE UNAVAILABLE"
                                }}</b
                                ><button
                                    class="operator-match-toggle"
                                    type="button"
                                    :aria-expanded="matchesOpen"
                                    aria-controls="operator-match-list-content"
                                    aria-label="Collapse matches"
                                    title="Collapse matches"
                                    @click="matchesOpen = false"
                                >
                                    <svg
                                        aria-hidden="true"
                                        viewBox="0 0 20 20"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                        class="reversed"
                                    >
                                        <path
                                            d="m7 4 6 6-6 6"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                        />
                                    </svg>
                                </button>
                            </div>
                            <div class="operator-judge-pills">
                                <span
                                    v-for="judge in judges"
                                    :key="judge.id"
                                    :title="`${judge.name}: ${judge.connected === null ? 'Presence unavailable' : judge.connected ? 'Connected' : 'Not connected'}`"
                                    :class="{ online: judge.connected }"
                                    >J{{ judge.seat }}</span
                                >
                            </div></template
                        >
                    </div>
                </div>
            </aside>

            <section class="operator-center" aria-label="Match control">
                <div
                    class="operator-match-meta"
                    aria-label="Selected match context"
                >
                    <div class="operator-match-meta-item operator-match-meta-bout">
                        <span>MATCH NO.</span>
                        <strong>
                            {{ match ? `NO. ${match.sequence}` : "—" }}
                        </strong>
                    </div>
                    <div class="operator-match-meta-item operator-match-meta-event">
                        <span>COMPETITION</span>
                        <strong :title="props.console?.tournament?.name || undefined">
                            {{ props.console?.tournament?.name || "—" }}
                        </strong>
                    </div>
                    <div class="operator-match-meta-item operator-match-meta-division">
                        <span>DIVISION</span>
                        <strong :title="divisionContextLabel">
                            {{ divisionContextLabel || "—" }}
                        </strong>
                    </div>
                    <div class="operator-match-meta-item operator-match-meta-round">
                        <span>STAGE / ROUND</span>
                        <strong :title="roundContextLabel">
                            {{ roundContextLabel || "—" }}
                        </strong>
                    </div>
                    <div
                        v-if="formContextLabel"
                        class="operator-match-meta-item operator-match-meta-form"
                    >
                        <span>FORM</span>
                        <strong :title="formContextLabel">
                            {{ formContextLabel }}
                        </strong>
                    </div>
                </div>
                <div class="operator-stage-head">
                    <div>
                        <h1>
                            {{ activeStage.toUpperCase() }}
                            <span
                                >/
                                {{
                                    performance?.status === "scoring"
                                        ? "RESULT READY"
                                        : (statusLabels[performance?.status] ??
                                          "MATCH CONTROL")
                                }}</span
                            >
                        </h1>
                    </div>
                    <div
                        class="operator-steps"
                        role="group"
                        aria-label="Performance stages"
                    >
                        <button
                            v-for="stage in stages"
                            :key="stage.id"
                            type="button"
                            :aria-pressed="activeStage === stage.id"
                            :aria-current="
                                currentStage === stage.id ? 'step' : undefined
                            "
                            :class="{
                                active: activeStage === stage.id,
                                current: currentStage === stage.id,
                            }"
                            @click="selectStage(stage.id)"
                        >
                            {{ stage.label }}
                        </button>
                    </div>
                </div>
                <div class="operator-stage-content">
                    <SetupStage
                        v-if="activeStage === 'setup'"
                        :match="match"
                        :category="category"
                        :round="round"
                        :bout="bout"
                        :performance="performance"
                        :judges="judges"
                        :connection="connection"
                        @continue="selectStage"
                    />
                    <LiveStage
                        v-else-if="activeStage === 'live'"
                        :match="match"
                        :category="category"
                        :performance="performance"
                        :court="court"
                        :processing="action.processing"
                        :errors="action.errors"
                        @command="command"
                        @continue="selectStage"
                    />
                    <ResultStage
                        v-else-if="activeStage === 'result'"
                        :match="match"
                        :category="category"
                        :performance="performance"
                        :bout="bout"
                        :judges="judges"
                        :manual-performance="manualPerformance"
                        :processing="action.processing"
                        :errors="action.errors"
                        @command="command"
                        @manual="openManualDialog"
                        @details="openDetailsDialog"
                        @continue="selectStage"
                    />
                    <ConfirmedStage v-else :match="match" :bout="bout" />
                </div>
            </section>

            <aside
                id="operator-events-panel"
                class="operator-panel operator-events"
                :class="{ 'events-hidden': !eventsOpen }"
                :aria-hidden="!eventsOpen"
                :inert="!eventsOpen"
                aria-label="System events"
            >
                <div class="operator-panel-head">
                    <strong>SYSTEM EVENT LOG</strong><span>▤</span>
                </div>
                <ol>
                    <li
                        v-for="event in props.console.recent_events"
                        :key="event.id"
                    >
                        <time :datetime="event.occurred_at">{{
                            time(event.occurred_at)
                        }}</time>
                        <div>
                            <strong>{{
                                eventLabels[event.action] ?? event.action
                            }}</strong
                            ><small>{{ event.actor_name ?? "System" }}</small>
                        </div>
                    </li>
                </ol>
                <p
                    v-if="!props.console.recent_events?.length"
                    class="operator-empty"
                >
                    No events recorded.
                </p>
            </aside>
        </main>
        <div v-else class="operator-empty operator-no-court">
            No court has been configured.
            <Link v-if="can.manage" :href="urls.setup">Open setup</Link>
        </div>
        <footer class="operator-footer">
            <span class="operator-footer-status"
                ><span>SYSTEM STATUS</span
                ><strong class="operator-footer-connection" :class="connection"
                    ><i aria-hidden="true"></i
                    >{{
                        connection === "connected" ? "LIVE" : "POLLING"
                    }}</strong
                ><span class="operator-footer-tournament"
                    >| {{ props.console.tournament.name }}</span
                ></span
            ><span
                ><a :href="urls.excel">EXCEL</a> &nbsp;·&nbsp;
                {{ props.console.attention.running }} LIVE &nbsp;·&nbsp;
                {{ props.console.attention.waiting_for_scores }} WAITING
                &nbsp;·&nbsp; {{ props.console.attention.ready }} READY
                <button
                    v-if="
                        can.manage &&
                        props.console.tournament.status === 'running' &&
                        props.console.tournament.categories.length &&
                        props.console.tournament.categories.every(
                            (item) => item.completed,
                        )
                    "
                    :disabled="completion.processing"
                    @click="completion.post(urls.base + '/complete')"
                >
                    Complete competition
                </button></span
            >
        </footer>
        <dialog
            ref="detailsDialog"
            class="operator-modal"
            @click="
                (event) => {
                    if (event.target === detailsDialog) detailsDialog.close();
                }
            "
        >
            <div
                v-if="operatorToast"
                class="operator-toast"
                :class="operatorToast.type"
                role="status"
                aria-live="polite"
            >
                <span>{{ operatorToast.message }}</span
                ><button
                    type="button"
                    aria-label="Dismiss notification"
                    @click="operatorToast = null"
                >
                    ×
                </button>
            </div>
            <div class="operator-modal-head">
                <div>
                    <strong
                        >MATCH {{ match?.sequence }} · ADVANCED CONTROLS</strong
                    ><small
                        >Review scores, resolve ties, and manage
                        exceptions</small
                    >
                </div>
                <button
                    type="button"
                    aria-label="Close dialog"
                    @click="detailsDialog.close()"
                >
                    ×
                </button>
            </div>
            <div class="operator-modal-body">
                <CourtConsole
                    v-if="
                        match &&
                        (court?.active?.bout_id === match.id ||
                            court?.queue?.[0]?.bout_id === match.id)
                    "
                    :court="court"
                    :rules="category?.rules ?? {}"
                    :online-judge-ids="onlineJudgeIds"
                    :base="urls.base"
                /><BoutPanel
                    v-if="bout && round && category"
                    :bout="bout"
                    :round="round"
                    :category="category"
                    :tournament="props.console.tournament"
                    :base="urls.base"
                    :can-operate="can.operate"
                />
            </div>
        </dialog>
        <dialog
            ref="manualDialog"
            class="operator-modal operator-manual-modal"
            @click="
                (event) => {
                    if (event.target === manualDialog) manualDialog.close();
                }
            "
        >
            <div
                v-if="operatorToast"
                class="operator-toast"
                :class="operatorToast.type"
                role="status"
                aria-live="polite"
            >
                <span>{{ operatorToast.message }}</span
                ><button
                    type="button"
                    aria-label="Dismiss notification"
                    @click="operatorToast = null"
                >
                    ×
                </button>
            </div>
            <div class="operator-modal-head">
                <div>
                    <strong>MANUAL JUDGE SCORE</strong
                    ><small
                        >Tablet issue · recorded with operator and reason</small
                    >
                </div>
                <button
                    type="button"
                    aria-label="Close dialog"
                    @click="manualDialog.close()"
                >
                    ×
                </button>
            </div>
            <div class="operator-modal-body">
                <ManualScorePanel
                    v-if="manualPerformance"
                    :performance="manualPerformance"
                    :rules="category?.rules ?? {}"
                    :base="urls.base"
                />
            </div>
        </dialog>
    </div>
</template>
