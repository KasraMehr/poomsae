<script setup>
import { computed } from "vue";
import AthleteInfo from "./AthleteInfo.vue";
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
    total: { type: [Number, String], default: null },
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

const statusLabel = computed(() => {
    if (isPrevious.value) return props.approved ? "COMPLETED" : "AWAITING RESULT";
    if (isNext.value) return "QUEUED";
    return {
        pending: "READY",
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

const formNames = computed(() => {
    if (!props.bout) return [];
    if (props.bout.category?.discipline === "freestyle") return [formName.value];
    return props.bout.performances?.map((item) => item.form_name).filter(Boolean) ?? [];
});

const entryTotal = (entry) => {
    if (String(entry.id) === String(props.winnerEntryId) && props.total != null) {
        return props.total;
    }
    return props.bout?.totals?.[entry.id] ?? null;
};

const formattedScore = (value) => value == null
    ? "—"
    : Number(value).toLocaleString("en-US", {
          minimumFractionDigits: 3,
          maximumFractionDigits: 6,
      });

const stateColorClass = computed(() => {
    if (isPrevious.value) return props.approved ? "standby-status--success" : "standby-status--waiting";
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
        ]"
        :aria-label="cardTitle"
    >
        <header class="standby-card__header">
            <div class="standby-card__context">
                <span class="standby-card__title">{{ cardTitle }}</span>
                <span v-if="bout?.category?.name" class="standby-card__category">{{ bout.category.name }}</span>
                <span v-if="bout?.round?.stage_label || bout?.round?.name" class="standby-card__round">
                    {{ bout.round.stage_label || bout.round.name }}
                </span>
            </div>
            <span class="standby-card__status" :class="stateColorClass">{{ statusLabel }}</span>
        </header>

        <template v-if="bout">
            <div class="standby-card__body" :class="{ 'standby-card__body--current': isCurrent }">
                <div class="standby-card__entries" :class="{ 'standby-card__entries--solo': bout.entries?.length === 1 }">
                    <section
                        v-for="entry in bout.entries ?? []"
                        :key="entry.id"
                        class="standby-entry"
                        :class="[`standby-entry--${entry.side || 'neutral'}`, { 'standby-entry--winner': String(entry.id) === String(winnerEntryId) }]"
                    >
                        <div class="standby-entry__identity">
                            <AthleteInfo
                                :layout="isCurrent ? 'vertical' : 'horizontal'"
                                :size="isCurrent ? 'lg' : 'sm'"
                                :name="entry.name"
                                :side="entry.side"
                                :country-code="entry.country_code"
                                :country="entry.country"
                                :flag-url="entry.flag_url"
                            />
                            <span v-if="entry.dan" class="standby-entry__dan">DAN {{ entry.dan }}</span>
                        </div>

                        <div v-if="entry.members?.length" class="standby-entry__members">
                            <span v-for="member in entry.members" :key="member.id ?? member.name">
                                {{ member.short_name || member.name }}
                            </span>
                        </div>
                        <span
                            v-else-if="entry.short_name && entry.short_name !== entry.name"
                            class="standby-entry__club"
                        >{{ entry.short_name }}</span>
                        <span v-if="entry.club" class="standby-entry__club">{{ entry.club }}</span>

                        <div v-if="isPrevious" class="standby-entry__result">
                            <strong v-if="String(entry.id) === String(winnerEntryId)" class="standby-entry__winner-label">WINNER</strong>
                            <strong>{{ formattedScore(entryTotal(entry)) }}</strong>
                        </div>
                        <div v-else-if="isNext" class="standby-entry__result standby-entry__result--waiting">WAITING</div>
                        <div v-else-if="entryTotal(entry) != null" class="standby-entry__result">
                            <strong>{{ formattedScore(entryTotal(entry)) }}</strong>
                        </div>
                    </section>
                </div>

                <aside class="standby-card__details">
                    <div class="standby-card__round-number">
                        <span>{{ bout.round?.name || "ROUND" }}</span>
                        <strong>{{ bout.round?.sequence ?? bout.round?.number ?? "—" }}</strong>
                    </div>
                    <FormBadge
                        v-if="performanceForForm"
                        :size="isCurrent ? 'md' : 'sm'"
                        :round-label="''"
                        :form-number="performanceForForm.form_number"
                        :symbol-key="symbolKey"
                        :form-name="formName"
                    />
                    <div v-if="formNames.length > 1" class="standby-card__other-forms">
                        {{ formNames.slice(1).join(" · ") }}
                    </div>
                    <span v-if="isCurrent && state === 'pending'" class="standby-card__awaiting-start">
                        Awaiting operator start
                    </span>
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
    --card-edge: var(--color-rtds-border);
    --card-accent: var(--color-rtds-text-muted);
    min-width: 0;
    padding: clamp(0.75rem, 1.5vw, 1.5rem);
    overflow: hidden;
    border: 1px solid var(--card-edge);
    border-radius: 1rem;
    background: var(--color-rtds-bg-card);
    color: var(--color-rtds-text);
}

.standby-card--current {
    border-width: 2px;
    border-color: var(--card-accent);
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

.standby-card--muted {
    opacity: 0.78;
}

.standby-card__header,
.standby-card__context {
    display: flex;
    min-width: 0;
    align-items: center;
    gap: 0.5rem;
}

.standby-card__header {
    justify-content: space-between;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid var(--color-rtds-border);
}

.standby-card__context {
    flex-wrap: wrap;
}

.standby-card__title,
.standby-card__status,
.standby-card__category,
.standby-card__round {
    font-size: clamp(0.625rem, 0.8vw, 0.8rem);
}

.standby-card__title {
    color: var(--color-rtds-text-muted);
    font-weight: 800;
    letter-spacing: 0.08em;
}

.standby-card__category {
    color: var(--color-rtds-text-secondary);
    font-weight: 700;
}

.standby-card__round {
    color: var(--color-rtds-text-muted);
}

.standby-card__status {
    flex: none;
    padding: 0.25rem 0.6rem;
    border: 1px solid currentColor;
    border-radius: 0.4rem;
    font-weight: 800;
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

.standby-card__body {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    align-items: stretch;
    gap: 0.75rem;
    padding-top: 0.75rem;
}

.standby-card__body--current {
    grid-template-columns: minmax(0, 1fr) minmax(9rem, 15rem);
    align-items: center;
    gap: clamp(0.75rem, 2vw, 2rem);
    padding-top: clamp(0.75rem, 1.8vw, 1.5rem);
}

.standby-card__entries {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: clamp(0.5rem, 1.25vw, 1.25rem);
    min-width: 0;
}

.standby-card__entries--solo {
    grid-template-columns: minmax(0, 1fr);
}

.standby-entry {
    display: flex;
    min-width: 0;
    flex-direction: column;
    justify-content: center;
    gap: 0.5rem;
    padding: clamp(0.5rem, 1vw, 1rem);
    border: 1px solid color-mix(in srgb, var(--entry-color) 48%, transparent);
    border-radius: 0.75rem;
    background: color-mix(in srgb, var(--entry-color) 12%, var(--color-rtds-bg-card));
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

.standby-entry__identity {
    display: flex;
    min-width: 0;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}

.standby-entry__dan,
.standby-entry__club,
.standby-entry__members {
    color: var(--color-rtds-text-secondary);
    font-size: clamp(0.625rem, 0.8vw, 0.8rem);
}

.standby-entry__members {
    display: flex;
    flex-wrap: wrap;
    gap: 0.25rem 0.75rem;
}

.standby-entry__result {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 0.5rem;
    color: var(--color-rtds-text-secondary);
    font-size: clamp(0.75rem, 1vw, 1rem);
}

.standby-entry__result strong {
    color: var(--color-rtds-gold-bright);
    font-size: clamp(1.125rem, 2vw, 2rem);
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
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.65rem;
    padding: 0.75rem;
    border: 1px solid var(--color-rtds-border);
    border-radius: 0.75rem;
    background: color-mix(in srgb, var(--color-rtds-bg-elevated) 58%, transparent);
}

.standby-card__round-number {
    display: flex;
    align-items: baseline;
    gap: 0.5rem;
    color: var(--color-rtds-text-secondary);
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
}

.standby-card__round-number strong {
    color: var(--color-rtds-text);
    font-size: clamp(1.25rem, 2vw, 2rem);
}

.standby-card__other-forms {
    color: var(--color-rtds-text-muted);
    font-size: 0.65rem;
    text-align: center;
}

.standby-card__awaiting-start,
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
    .standby-card__body--current {
        grid-template-columns: minmax(0, 1fr);
    }

    .standby-card__details {
        flex-direction: row;
        flex-wrap: wrap;
    }
}

@media (max-width: 520px) {
    .standby-card__entries:not(.standby-card__entries--solo) {
        grid-template-columns: minmax(0, 1fr);
    }

    .standby-card__header {
        align-items: flex-start;
    }
}
</style>
