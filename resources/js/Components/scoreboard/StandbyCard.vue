<script setup>
import { computed } from "vue";
import EntryAthleteInfo from "./EntryAthleteInfo.vue";
import FormBadge from "./FormBadge.vue";
import { displayFormName, displayFormSymbolKey } from "../../Shared/Services/symbols";

const props = defineProps({
    variant: {
        type: String,
        default: "current",
        validator: (value) => ["previous", "current", "next"].includes(value),
    },
    bout: { type: Object, default: null },
    performance: { type: Object, default: null },
    performances: { type: Array, default: () => [] },
    state: { type: String, default: "pending" },
    approved: { type: Boolean, default: false },
    winnerEntryId: { type: [Number, String], default: null },
});

const isCurrent = computed(() => props.variant === "current");
const isPrevious = computed(() => props.variant === "previous");
const isNext = computed(() => props.variant === "next");

const performanceForForm = computed(
    () => props.performance ?? props.performances[0] ?? props.bout?.performances?.[0] ?? null,
);

const cardTitle = computed(() => ({
    previous: "PREVIOUS",
    current: "CURRENT",
    next: "NEXT",
})[props.variant]);

const roundLabel = computed(() => {
    const round = props.bout?.round;
    const number = round?.sequence ?? round?.number;

    return number != null ? `ROUND ${number}` : round?.name || "ROUND";
});

const statusLabel = computed(() => {
    if (isPrevious.value) return props.approved ? "COMPLETED" : "AWAITING RESULT";
    if (isNext.value) return "READY";
    return {
        pending: "WAITING FOR OPERATOR START",
        running: "LIVE",
        scoring: "AWAITING SCORES",
        approved: "COMPLETED",
    }[props.state] ?? "READY";
});

const formName = computed(() => {
    const performance = performanceForForm.value;
    if (!performance) return "";
    return displayFormName(props.bout?.category, performance);
});

const symbolKey = computed(() =>
    displayFormSymbolKey(props.bout?.category, performanceForForm.value),
);

const entryTotal = (entry) => props.bout?.totals?.[entry.id] ?? null;

const formattedScore = (value) => value == null
    ? "—"
    : Number(value).toLocaleString("en-US", {
          minimumFractionDigits: 3,
          maximumFractionDigits: 6,
      });

const stateColorClass = computed(() => {
    if (isPrevious.value) return "standby-status--previous";
    if (isNext.value) return "standby-status--waiting";
    return {
        pending: "standby-status--success",
        running: "standby-status--live",
        scoring: "standby-status--waiting",
        approved: "standby-status--success",
    }[props.state] ?? "standby-status--success";
});

const currentAccentClass = computed(() => ({
    pending: "standby-card--ready",
    running: "standby-card--live",
    scoring: "standby-card--scoring",
    approved: "standby-card--ready",
}[props.state] ?? "standby-card--ready"));
</script>

<template>
    <article
        class="standby-card"
        :class="[
            `standby-card--${variant}`,
            isCurrent ? currentAccentClass : 'standby-card--muted',
            { 'standby-card--empty': !bout },
        ]"
        :aria-label="cardTitle"
    >
        <span class="standby-card__title">{{ cardTitle }}</span>

        <template v-if="bout">
            <div class="standby-card__body" :class="{ 'standby-card__body--current': isCurrent }">
                <div class="standby-card__entries" :class="{ 'standby-card__entries--solo': bout.entries?.length === 1 }">
                    <section
                        v-for="entry in bout.entries ?? []"
                        :key="entry.id"
                        class="standby-entry"
                        :class="[
                            `standby-entry--${variant}`,
                            `standby-entry--${entry.side || 'neutral'}`,
                            { 'standby-entry--winner': String(entry.id) === String(winnerEntryId), 'standby-entry--solo': bout.entries?.length === 1 },
                        ]"
                    >
                        <EntryAthleteInfo
                            :entry="entry"
                            :variant="isCurrent ? 'current' : 'compact'"
                        />

                        <div
                            v-if="isPrevious && String(entry.id) === String(winnerEntryId)"
                            class="standby-entry__result"
                        >
                            <strong class="standby-entry__winner-label"></strong>
                        </div>
                        <div v-else-if="isNext" class="standby-entry__result standby-entry__result--waiting"></div>
                        <div v-else-if="!isPrevious && entryTotal(entry) != null" class="standby-entry__result">
                            <strong>{{ formattedScore(entryTotal(entry)) }}</strong>
                        </div>
                    </section>
                </div>

                <aside class="standby-card__details" :class="{ 'standby-card__details--current': isCurrent }">
                    <div class="standby-card__round-number">{{ roundLabel }}</div>
                    <FormBadge
                        v-if="performanceForForm"
                        :size="isCurrent ? 'xxl' : 'lg'"
                        :round-label="''"
                        :form-number="performanceForForm.form_number"
                        :symbol-key="symbolKey"
                        :form-name="formName"
                    />
                    <span class="standby-card__status" :class="stateColorClass">{{ statusLabel }}</span>
                </aside>
            </div>
        </template>
        <p v-else class="standby-card__empty">
            {{ isPrevious ? "No previous performance" : isNext ? "No next performance scheduled" : "No active performance" }}
        </p>
    </article>
</template>

<style scoped>
.standby-card {
    --card-accent: var(--color-rtds-border-strong);
    --card-border: color-mix(in srgb, var(--card-accent) 62%, var(--color-rtds-border));
    position: relative;
    box-sizing: border-box;
    display: flex;
    flex: 0.45 1 0;
    flex-direction: column;
    min-width: 0;
    min-height: 0;
    padding: clamp(0.35rem, 0.75vh, 0.65rem);
    border: 1px solid var(--card-border);
    border-radius: 0.8rem;
    background: var(--color-rtds-bg-card);
    color: var(--color-rtds-text);
}

.standby-card--current {
    flex: 1.5 1 0;
    padding: clamp(0.45rem, 0.9vh, 0.75rem);
    border-width: 2px;
    box-shadow: 0 0 1.6rem color-mix(in srgb, var(--card-accent) 24%, transparent);
}

.standby-card--ready {
    --card-accent: var(--color-rtds-success);
}

.standby-card--live {
    --card-accent: var(--color-rtds-blue);
}

.standby-card--scoring {
    --card-accent: var(--color-rtds-yellow);
}

.standby-card--previous {
    flex: 0.45 1 0;
    --card-accent: var(--color-rtds-blue-soft);
}

.standby-card--next {
    flex: 0.45 1 0;
    --card-accent: var(--color-rtds-yellow);
}

.standby-card--muted {
    opacity: 0.78;
}

.standby-card--empty {
    flex: 0.45 1 0;
}

.standby-card__title {
    position: absolute;
    top: 0;
    left: clamp(0.7rem, 1.2vw, 1.25rem);
    transform: translateY(-55%);
    padding: 0 0.65rem;
    background: var(--color-rtds-bg-card);
    color: var(--card-accent);
    font-size: clamp(1rem, 1.5vw, 1.45rem);
    font-weight: 800;
    letter-spacing: 0.06em;
}

.standby-card__body {
    display: grid;
    flex: 1;
    min-height: 0;
    overflow: hidden;
    grid-template-columns: minmax(0, 1.7fr) minmax(12rem, 0.78fr);
    align-items: stretch;
    gap: clamp(0.4rem, 0.8vw, 0.8rem);
    padding-top: 0.2rem;
}

.standby-card__body--current {
    grid-template-columns: minmax(0, 1.7fr) minmax(13rem, 0.78fr);
    align-items: stretch;
    gap: clamp(0.5rem, 1vw, 1rem);
    padding-top: 0.25rem;
}

.standby-card__entries {
    display: grid;
    min-width: 0;
    min-height: 0;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    grid-auto-rows: minmax(0, 1fr);
    align-items: stretch;
    gap: clamp(0.4rem, 1vw, 0.8rem);
}

.standby-entry {
    display: flex;
    min-width: 0;
    min-height: 0;
    overflow: hidden;
    flex-direction: column;
    justify-content: center;
    align-items: flex-start;
    gap: clamp(0.3rem, 0.65vw, 0.65rem);
    padding: clamp(0.35rem, 0.7vh, 0.7rem);
    border: 1px solid color-mix(in srgb, var(--entry-color) 58%, transparent);
    border-radius: 0.65rem;
    background: color-mix(in srgb, var(--entry-color) 22%, var(--color-rtds-bg-card));
}

.standby-card--current .standby-entry {
    container-type: size;
    min-height: clamp(6rem, 19vh, 13rem);
    justify-content: flex-start;
    align-items: stretch;
}

.standby-card--previous .standby-entry,
.standby-card--next .standby-entry {
    min-height: 0;
    padding: clamp(0.25rem, 0.55vh, 0.55rem);
}

.standby-entry--chung {
    --entry-color: var(--color-rtds-blue);
}

.standby-entry--hong {
    --entry-color: var(--color-rtds-red);
}

.standby-entry--neutral {
    --entry-color: var(--color-rtds-border-strong);
}

.standby-entry--winner {
    border-color: var(--color-rtds-yellow);
    box-shadow: inset 0 0 0 1px var(--color-rtds-yellow);
}

.standby-entry--hong {
    align-items: flex-end;
    text-align: right;
}

.standby-entry--solo {
    grid-column: 1 / -1;
}

.standby-entry--hong :deep(.bar__names),
.standby-entry--hong :deep(.bar) {
    text-align: right;
}

.standby-entry__result {
    display: flex;
    width: 100%;
    align-items: baseline;
    justify-content: space-between;
    gap: 0.5rem;
    color: var(--color-rtds-text-secondary);
    font-size: clamp(0.75rem, 1vw, 1rem);
}

.standby-entry--previous .standby-entry__result {
    position: absolute;
    z-index: 1;
    right: clamp(0.45rem, 0.9vw, 0.9rem);
    bottom: clamp(0.3rem, 0.6vh, 0.6rem);
    width: auto;
    flex-direction: column;
    align-items: flex-end;
    gap: 0;
}

.standby-entry--previous.standby-entry--hong .standby-entry__result {
    right: auto;
    left: clamp(0.45rem, 0.9vw, 0.9rem);
    align-items: flex-start;
}

.standby-entry--hong .standby-entry__result {
    flex-direction: row-reverse;
}

.standby-entry__result strong {
    color: var(--color-rtds-gold-bright);
    font-size: clamp(1.5rem, 2.8vw, 3rem);
    font-variant-numeric: tabular-nums;
}

.standby-entry__winner-label {
    color: var(--color-rtds-yellow) !important;
    font-size: 0.65rem !important;
}

.standby-entry__result--waiting {
    color: var(--color-rtds-text-muted);
    font-size: 0.65rem;
    font-weight: 800;
}

.standby-card__details {
    display: flex;
    min-width: 0;
    min-height: 0;
    overflow: hidden;
    flex-direction: column;
    align-items: center;
    justify-content: space-between;
    gap: clamp(0.35rem, 0.8vh, 0.8rem);
    padding: clamp(0.45rem, 0.9vh, 0.85rem);
    border: 1px solid var(--color-rtds-border);
    border-radius: 0.75rem;
    background: color-mix(in srgb, var(--color-rtds-bg-elevated) 58%, transparent);
}

.standby-card__details:not(.standby-card__details--current) {
    display: grid;
    grid-template-columns: minmax(0, 2fr) minmax(0, 1fr);
    grid-template-rows: auto minmax(0, 1fr);
    justify-content: stretch;
    align-items: center;
    gap: clamp(0.2rem, 0.55vh, 0.5rem);
}

.standby-card__round-number {
    color: var(--color-rtds-text);
    width: 100%;
    font-size: clamp(1.5rem, min(2.5vw, 4vh), 2.5rem);
    font-weight: 900;
    line-height: 1.1;
    text-align: center;
}

.standby-card__details:not(.standby-card__details--current) .standby-card__round-number {
    grid-column: 1 / -1;
}

.standby-card__details--current .standby-card__round-number {
    font-size: clamp(2rem, min(3.5vw, 5vh), 3.5rem);
}

.standby-card__details :deep(> span[dir="ltr"]) {
    display: flex;
    width: 100%;
    max-width: 100%;
    flex-wrap: wrap;
    justify-content: center;
    text-align: center;
}

.standby-card__details:not(.standby-card__details--current) :deep(> span[dir="ltr"]) {
    height: 100%;
    flex-wrap: nowrap;
    gap: clamp(0.2rem, 0.55vw, 0.5rem);
}

.standby-card__details:not(.standby-card__details--current) :deep(> span[dir="ltr"] > span:last-child) {
    font-size: clamp(1rem, min(1.7vw, 2.5vh), 1.8rem);
}

.standby-card__details :deep(> span[dir="ltr"] > span:last-child) {
    overflow-wrap: anywhere;
    white-space: normal;
    font-size: clamp(1.25rem, min(2.2vw, 3.5vh), 2.3rem);
    line-height: 1.05;
    text-align: center;
}

.standby-card__details--current :deep(> span[dir="ltr"] > span:last-child) {
    font-size: clamp(2rem, min(3.7vw, 6vh), 3.75rem);
}

.standby-card__status {
    display: inline-flex;
    max-width: 100%;
    align-items: center;
    justify-content: center;
    width: 100%;
    padding: clamp(0.45rem, 0.9vh, 0.8rem) clamp(0.55rem, 1vw, 1rem);
    border: 1px solid currentColor;
    border-radius: 0.55rem;
    background: color-mix(in srgb, currentColor 12%, transparent);
    font-size: clamp(0.75rem, min(1.1vw, 2vh), 1.25rem);
    font-weight: 900;
    line-height: 1.2;
    text-align: center;
    white-space: nowrap;
}

.standby-card__details--current .standby-card__status {
    font-size: clamp(1rem, min(1.7vw, 3vh), 1.55rem);
}

.standby-card__details:not(.standby-card__details--current) .standby-card__status {
    padding: clamp(0.25rem, 0.45vh, 0.4rem);
    font-size: clamp(0.625rem, min(0.9vw, 1.5vh), 1rem);
    white-space: normal;
}

.standby-status--success {
    color: var(--color-rtds-success);
}

.standby-status--live {
    color: var(--color-rtds-blue-soft);
}

.standby-status--waiting {
    color: var(--color-rtds-yellow);
}

.standby-status--previous {
    color: var(--card-border);
}

.standby-card__empty {
    color: var(--color-rtds-text-secondary);
    font-size: clamp(0.7rem, 0.9vw, 0.9rem);
    text-align: center;
}

.standby-card__empty {
    margin: 0;
    padding-top: 0.75rem;
}

@media (max-width: 760px) {
    .standby-card__body,
    .standby-card__body--current {
        grid-template-columns: minmax(0, 1fr);
    }

    .standby-card__details {
        grid-column: auto;
    }

    .standby-card__entries {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 520px) {
    .standby-card__body,
    .standby-card__body--current {
        grid-template-columns: minmax(0, 1fr);
    }

    .standby-card__details {
        grid-column: auto;
    }

    .standby-entry--solo {
        grid-column: auto;
    }

    .standby-card__entries {
        grid-template-columns: minmax(0, 1fr);
    }
}

@media (max-height: 800px) {
    .standby-card__details:not(.standby-card__details--current) :deep(> span[dir="ltr"] > img) {
        width: clamp(2rem, 7vh, 4.5rem);
        height: clamp(2rem, 7vh, 4.5rem);
    }

    .standby-card__details:not(.standby-card__details--current) :deep(> span[dir="ltr"] > span:last-child) {
        font-size: clamp(1rem, min(2.2vw, 3vh), 2rem);
    }
}
</style>
