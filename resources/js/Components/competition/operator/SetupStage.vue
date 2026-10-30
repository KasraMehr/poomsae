<script setup>
import { computed } from 'vue';

const props = defineProps({
    match: { type: Object, default: null },
    category: { type: Object, default: null },
    round: { type: Object, default: null },
    bout: { type: Object, default: null },
    performance: { type: Object, default: null },
    judges: { type: Array, default: () => [] },
    connection: { type: String, default: 'disconnected' },
});

const emit = defineEmits(['continue']);
const performanceStatus = computed(() => props.performance?.status ?? props.match?.status ?? 'pending');
const profiles = computed(() => props.match?.entries?.map(entry => ({
    ...entry,
    profile: props.category?.entries?.find(categoryEntry => categoryEntry.id === entry.id) ?? null,
})) ?? []);
const forms = computed(() => {
    const entryIds = new Set((props.match?.entries ?? []).map(entry => entry.id));
    const uniqueForms = new Map();
    [...(props.bout?.performances ?? [])]
        .filter(item => entryIds.has(item.entry_id))
        .sort((left, right) => left.form_number - right.form_number)
        .forEach(item => {
            if (!uniqueForms.has(item.form_number)) {
                uniqueForms.set(item.form_number, item.form_name);
            }
        });

    return [...uniqueForms].map(([number, name]) => ({ number, name }));
});
const expectedFormCount = computed(() => props.category?.forms_per_round ?? 1);
const formIsReady = computed(() => props.category?.discipline === 'freestyle'
    ? forms.value.length > 0
    : props.round?.forms_ready === true && forms.value.length >= expectedFormCount.value);
const judgeDevices = computed(() => {
    const expectedCount = props.category?.judge_count ?? props.judges.length;
    const onlineCount = props.judges.filter(judge => judge.connected === true).length;
    const presenceKnown = props.connection === 'connected' && props.judges.every(judge => judge.connected !== null);
    const allOnline = presenceKnown && expectedCount > 0 && props.judges.length >= expectedCount && onlineCount === expectedCount;

    let state = 'unknown';
    if (allOnline) {
        state = 'ready';
    } else if (presenceKnown) {
        state = 'attention';
    }

    const detail = !presenceKnown
        ? `${props.judges.length} assigned · connection status unavailable`
        : `${onlineCount} / ${expectedCount} devices responding`;

    return { state, detail };
});
const checklist = computed(() => [
    {
        id: 'competition',
        title: 'Competition Data Loaded',
        detail: props.match && props.category && props.round && props.bout
            ? `${props.match.category_name} · ${props.match.round_name} · No.${props.match.sequence}`
            : 'Match category or round data is unavailable.',
        state: props.match && props.category && props.round && props.bout ? 'ready' : 'attention',
    },
    {
        id: 'athletes',
        title: 'Athletes Loaded',
        detail: `${profiles.value.filter(entry => entry.profile?.id === entry.id).length} / ${profiles.value.length} athlete profiles loaded for this match.`,
        state: profiles.value.length > 0 && profiles.value.every(entry => entry.profile?.id === entry.id) ? 'ready' : 'attention',
    },
    {
        id: 'forms',
        title: props.category?.discipline === 'freestyle' ? 'Performance Ready' : 'Poomsae Assigned',
        detail: `${forms.value.length} / ${expectedFormCount.value} forms assigned to this match.`,
        state: formIsReady.value ? 'ready' : 'attention',
    },
    {
        id: 'judges',
        title: 'Judge Tablets Connected',
        detail: judgeDevices.value.detail,
        state: judgeDevices.value.state,
    },
]);
const statusNote = computed(() => ({
    pending: 'Review the match setup before moving to the live controls.',
    running: 'This match is live. Setup details are shown for reference.',
    scoring: 'The performance has ended and its scores are being reviewed.',
    approved: 'The result has been confirmed and published.',
    completed: 'This match is complete.',
    cancelled: 'This match was cancelled.',
})[performanceStatus.value] ?? 'Review the current match setup.');
const continueStage = computed(() => ({
    pending: 'live',
    running: 'live',
    scoring: 'result',
    approved: 'confirmed',
    completed: 'confirmed',
    cancelled: 'confirmed',
})[performanceStatus.value] ?? 'live');
const continueLabel = computed(() => ({
    pending: 'CONTINUE MATCH',
    running: 'RETURN TO LIVE',
    scoring: 'CONTINUE TO RESULT',
    approved: 'VIEW CONFIRMED RESULT',
    completed: 'VIEW CONFIRMED RESULT',
    cancelled: 'VIEW MATCH SUMMARY',
})[performanceStatus.value] ?? 'CONTINUE MATCH');
const formState = number => {
    const currentNumber = props.performance?.form_number;

    if (number === currentNumber) {
        return performanceStatus.value === 'running' ? 'LIVE' : 'CURRENT';
    }

    if (currentNumber !== undefined && number < currentNumber) {
        return 'COMPLETED';
    }

    return 'NEXT';
};
</script>

<template>
    <section v-if="match" class="operator-setup" aria-label="Match setup">
        <div class="operator-setup-left">
            <header class="operator-setup-context">
                <strong>No.{{ match.sequence }}</strong>
                <span>{{ match.category_name }} · {{ match.round_name }}</span>
            </header>
            <article v-for="(entry, index) in profiles" :key="entry.id" class="operator-setup-athlete" :class="index === 0 ? 'chung' : 'hong'">
                <header>
                    <strong>{{ entry.side?.toUpperCase() ?? (index === 0 ? 'CHUNG / BLUE' : 'HONG / RED') }}</strong>
                </header>
                <div class="operator-setup-athlete-info">
                    <div>
                        <h2>{{ entry.profile?.short_name || entry.short_name || entry.name }}</h2>
                        <p>{{ entry.profile?.club || entry.profile?.country_code || 'Athlete profile loaded' }}</p>
                    </div>
                </div>
            </article>

            <section class="operator-setup-forms" aria-label="Assigned forms">
                <header>
                    <h2>{{ category?.discipline === 'freestyle' ? 'ASSIGNED PERFORMANCE' : 'ASSIGNED POOMSAE' }}</h2>
                </header>
                <div v-if="forms.length" class="operator-setup-form-list">
                    <div v-for="form in forms" :key="form.number" class="operator-setup-form" :class="{ current: form.number === performance?.form_number, upcoming: form.number > (performance?.form_number ?? 0) }">
                        <span class="operator-setup-form-number">{{ form.number }}</span>
                        <strong>{{ form.name }}</strong>
                        <small>{{ formState(form.number) }}</small>
                    </div>
                </div>
                <p v-else class="operator-setup-empty">{{ category?.discipline === 'freestyle' ? 'Performance details are not available yet.' : 'Poomsae have not been assigned yet.' }}</p>
            </section>
        </div>

        <section class="operator-setup-checklist" aria-labelledby="operator-setup-checklist-title">
            <header>
                <h2 id="operator-setup-checklist-title">PRE-MATCH CHECKLIST</h2>
            </header>
            <p class="operator-setup-status-note" :class="performanceStatus">{{ statusNote }}</p>
            <ul>
                <li v-for="item in checklist" :key="item.id" :class="item.state">
                    <span class="operator-setup-check-icon" aria-hidden="true">{{ item.state === 'ready' ? '✓' : item.state === 'unknown' ? '—' : '!' }}</span>
                    <span class="operator-setup-check-copy"><strong>{{ item.title }}</strong><small>{{ item.detail }}</small></span>
                </li>
            </ul>
            <button type="button" class="operator-setup-continue" @click="emit('continue', continueStage)">{{ continueLabel }}</button>
        </section>
    </section>
    <section v-else class="operator-setup-empty-state" aria-label="Match setup unavailable">
        <h2>No match selected</h2>
        <p>Select a match from the list to review its setup.</p>
    </section>
</template>

<style scoped>
.operator-setup {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1.12fr);
    align-items: stretch;
    gap: 12px;
    height: 100%;
    min-height: 0;
    padding: 12px;
    color: #0d192b;
    box-sizing: border-box;
}

.operator-setup-context {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr);
    align-items: center;
    gap: 12px;
    padding: 0 0 10px;
    border-bottom: 1px solid #c5c6cc;
}

.operator-setup-context strong {
    justify-self: start;
    font-size: 24px;
    font-weight: 900;
}

.operator-setup-context span {
    justify-self: end;
    color: #586478;
    font-size: 14px;
    font-weight: 700;
    text-align: right;
}

.operator-setup-left {
    display: flex;
    flex-direction: column;
    gap: 10px;
    min-width: 0;
    min-height: 0;
}

.operator-setup-athlete,
.operator-setup-forms,
.operator-setup-checklist {
    border: 1px solid #c5c6cc;
    border-radius: 2px;
    background: #eef4ff;
}

.operator-setup-athlete {
    position: relative;
    padding: 10px 14px 12px 18px;
}

.operator-setup-athlete::before {
    position: absolute;
    inset: 0 auto 0 0;
    width: 6px;
    background: #2475bf;
    content: '';
}

.operator-setup-athlete.hong::before {
    background: #c44750;
}

.operator-setup-athlete header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    min-height: 24px;
}

.operator-setup-athlete header strong {
    color: #555f6d;
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 1px;
}

.operator-setup-athlete.chung header strong {
    color: #2475bf;
}

.operator-setup-athlete.hong header strong {
    color: #c44750;
}

.operator-setup-athlete-info {
    display: flex;
    align-items: center;
    margin-top: 5px;
}

.operator-setup-athlete-info h2 {
    overflow: hidden;
    margin: 0;
    color: #0a1422;
    font-size: clamp(18px, 2vw, 24px);
    font-weight: 800;
    line-height: 1.25;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.operator-setup-athlete-info p {
    margin: 4px 0 0;
    color: #555f6d;
    font-size: 13px;
}

.operator-setup-forms {
    flex: 1;
    min-height: 0;
    padding: 12px;
}

.operator-setup-forms > header,
.operator-setup-checklist > header {
    padding-bottom: 9px;
    border-bottom: 1px solid #c5c6cc;
}

.operator-setup-forms h2 {
    margin: 0;
    color: #555f6d;
    font-size: 13px;
    font-weight: 800;
    letter-spacing: .65px;
}

.operator-setup-form-list {
    display: grid;
    gap: 10px;
    margin-top: 12px;
}

.operator-setup-form {
    display: grid;
    grid-template-columns: 32px minmax(0, 1fr) auto;
    align-items: center;
    gap: 10px;
    min-height: 56px;
    padding: 10px 12px;
    border: 1px solid #c5c6cc;
    background: #f8f9ff;
}

.operator-setup-form.upcoming {
    background: #dce3f0;
    opacity: .78;
}

.operator-setup-form-number {
    display: grid;
    width: 30px;
    height: 30px;
    place-items: center;
    background: #0a1422;
    color: #fff;
    font-weight: 800;
}

.operator-setup-form.upcoming .operator-setup-form-number {
    background: #c5c6cc;
    color: #151c25;
}

.operator-setup-form strong {
    min-width: 0;
    overflow: hidden;
    font-size: 16px;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.operator-setup-form small {
    color: #555f6d;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: .4px;
}

.operator-setup-empty {
    margin: 16px 0 0;
    color: #667184;
}

.operator-setup-checklist {
    display: flex;
    flex-direction: column;
    min-width: 0;
    min-height: 0;
    padding: 14px;
}

.operator-setup-checklist h2 {
    margin: 0;
    color: #0a1422;
    font-size: clamp(17px, 2vw, 23px);
    font-weight: 800;
    letter-spacing: 1px;
    text-align: center;
}

.operator-setup-status-note {
    margin: 8px 0 0;
    color: #667184;
    font-size: 12px;
    line-height: 1.5;
    text-align: center;
}

.operator-setup-checklist ul {
    display: grid;
    gap: 8px;
    margin: 10px 0 0;
    padding: 0;
    list-style: none;
}

.operator-setup-checklist li {
    display: flex;
    align-items: center;
    gap: 12px;
    min-height: 52px;
    padding: 8px 10px;
    border: 1px solid #c5c6cc;
    background: #f8f9ff;
}

.operator-setup-check-icon {
    display: grid;
    width: 24px;
    height: 24px;
    flex: none;
    place-items: center;
    border: 2px solid #167651;
    border-radius: 50%;
    color: #167651;
    font-size: 15px;
    font-weight: 900;
}

.operator-setup-checklist li.attention .operator-setup-check-icon {
    border-color: #ad3636;
    color: #ad3636;
}

.operator-setup-checklist li.unknown .operator-setup-check-icon {
    border-color: #a16a22;
    color: #a16a22;
}

.operator-setup-check-copy {
    display: grid;
    min-width: 0;
    gap: 3px;
}

.operator-setup-check-copy strong {
    color: #192333;
    font-size: 13px;
}

.operator-setup-check-copy small {
    color: #667184;
    font-size: 11px;
    line-height: 1.4;
}

.operator-setup-continue {
    width: 100%;
    min-height: 54px;
    margin-top: auto;
    padding: 12px 16px;
    border: 1px solid #0a1422;
    background: #0a1422;
    color: #fff;
    font-size: 12px;
    font-weight: 900;
    letter-spacing: 1px;
    cursor: pointer;
}

.operator-setup-continue:hover {
    background: #1b2b40;
}

.operator-setup-continue:focus-visible {
    outline: 2px solid #2475bf;
    outline-offset: 3px;
}

.operator-setup-empty-state {
    display: grid;
    min-height: 280px;
    align-content: center;
    justify-items: center;
    padding: 24px;
    color: #667184;
    text-align: center;
}

.operator-setup-empty-state h2 {
    margin: 0 0 8px;
    color: #0d192b;
}

.operator-setup-empty-state p {
    margin: 0;
}

@media (max-width: 1000px) {
    .operator-setup {
        grid-template-columns: minmax(0, 1fr);
        height: auto;
        min-height: 100%;
    }

    .operator-setup-forms {
        flex: initial;
    }

    .operator-setup-checklist {
        min-height: 0;
    }
}

@media (max-height: 800px) and (min-width: 1001px) {
    .operator-setup {
        gap: 8px;
        padding: 8px;
    }

    .operator-setup-context {
        padding-bottom: 6px;
    }

    .operator-setup-athlete {
        padding-top: 8px;
        padding-bottom: 9px;
    }

    .operator-setup-checklist {
        padding: 10px;
    }

    .operator-setup-checklist ul {
        gap: 6px;
        margin-top: 7px;
    }

    .operator-setup-checklist li {
        min-height: 46px;
        padding-top: 6px;
        padding-bottom: 6px;
    }

    .operator-setup-continue {
        min-height: 46px;
    }
}

@media (max-width: 500px) {
    .operator-setup {
        gap: 8px;
        padding: 8px;
    }

    .operator-setup-checklist {
        padding: 14px;
    }

    .operator-setup-context {
        gap: 6px 10px;
    }

    .operator-setup-context span {
        justify-self: end;
        text-align: right;
    }

    .operator-setup-form {
        grid-template-columns: 30px minmax(0, 1fr);
    }

    .operator-setup-form small {
        grid-column: 2;
    }
}
</style>
