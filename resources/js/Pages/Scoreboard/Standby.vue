<script setup>
import { computed } from "vue";
import { Head } from "@inertiajs/vue3";
import ScoreboardLayout from "../../Shared/Layouts/ScoreboardLayout.vue";
import ScoreboardHeader from "../../Components/scoreboard/ScoreboardHeader.vue";
import AthleteInfo from "../../Components/scoreboard/AthleteInfo.vue";
import FormBadge from "../../Components/scoreboard/FormBadge.vue";
import Timer from "../../Components/scoreboard/Timer.vue";

/**
 * LiveBoard — صفحهٔ مستقل broadcast برای نمایش جریان مسابقهٔ «یک زمین»:
 *
 *   PREVIOUS ↓ CURRENT ↓ NEXT
 *
 * stateهای CURRENT (طبق قرارداد):
 * - running  = اجرای فیزیکی فعلی + Timer (در حال شمارش)
 * - scoring  = اجرا تمام شده، Timer متوقف (روی زمان پایان)، در انتظار نتیجه
 * - approved = نتیجه تأیید/منتشر شده
 *
 * Timer مالکیت lifecycle اجرا را دارد؛ این صفحه فقط نمایش‌دهنده است.
 * صفحهٔ صف اجرا/زمان‌بندی نیست — فقط وضعیت یک زمین.
 *
 * TODO [Proposed Backend Contract]: انتخاب زمین (courtId) از query/param بیاید؛
 * تا آن زمان prop اختیاری است و fallback به اولین زمین snapshot می‌رود.
 */
const props = defineProps({
    display: { type: Object, default: null },
    tournament: { type: Object, default: null },
    courtId: { type: [Number, String], default: null },
    focusItem: { type: Object, default: null },
});

// Accept the production display envelope and the standalone lab snapshot.
const tournament = computed(
    () => props.tournament ?? props.display?.tournament ?? null,
);

const statusLabels = {
    draft: "DRAFT",
    ready: "READY",
    running: "IN PROGRESS",
    completed: "COMPLETED",
};

const stage = computed(() => statusLabels[tournament.value?.status] ?? "");

// زمینِ انتخابی (fallback: اولین زمین snapshot — دادهٔ واقعی، نه mock)
const court = computed(() => {
    const courts = tournament.value?.courts ?? [];
    if (props.courtId != null) {
        return (
            courts.find((c) => String(c.id) === String(props.courtId))?.name ??
            null
        );
    }
    return courts[0]?.name ?? null;
});

const courtId = computed(() => {
    const courts = tournament.value?.courts ?? [];
    if (props.courtId != null) {
        return (
            courts.find((c) => String(c.id) === String(props.courtId))?.id ??
            props.courtId
        );
    }
    return courts[0]?.id ?? null;
});

const displayCourt = computed(() =>
    props.display?.courts?.find(
        (court) => String(court.id) === String(courtId.value),
    ) ?? null,
);

// Keep the snapshot's category, round, and bout iteration order for history.
const courtBouts = computed(() => {
    if (courtId.value == null) return [];
    const out = [];
    for (const category of tournament.value?.categories ?? []) {
        for (const round of category.rounds ?? []) {
            for (const bout of round.bouts ?? []) {
                if (String(bout.court_id) !== String(courtId.value)) continue;
                out.push({ ...bout, category, round });
            }
        }
    }
    return out;
});

const findSnapshotBout = (boutId) =>
    courtBouts.value.find((bout) => String(bout.id) === String(boutId)) ?? null;

/**
 * CURRENT: اجرای در حال اجرا (running) یا اجرایی که تمام شده و منتظر نتیجه است (scoring).
 */
const current = computed(() => {
    if (displayCourt.value) {
        const item = displayCourt.value.current;
        if (item) {
            const bout = findSnapshotBout(item.bout_id);
            if (!bout) return null;

            const performance =
                item.performances?.find((p) =>
                    ["running", "scoring"].includes(p.status),
                ) ?? bout.performances[0];
            if (!performance) return null;

            return {
                bout,
                performance,
                state: ["running", "scoring"].includes(item.status)
                    ? item.status
                    : "approved",
            };
        }

        if (props.focusItem?.bout_id) {
            const bout = findSnapshotBout(props.focusItem.bout_id);
            const performance = bout?.performances?.find(
                (item) => String(item.id) === String(props.focusItem.id),
            );

            if (bout && performance) {
                return { bout, performance, state: "pending" };
            }
        }

        return null;
    }

    const bout = courtBouts.value.find(
        (b) =>
            b.status === "running" ||
            b.performances.some((p) => ["running", "scoring"].includes(p.status)),
    );
    if (!bout) return null;

    const performance =
        bout.performances.find((p) =>
            ["running", "scoring"].includes(p.status),
        ) ?? bout.performances[0];

    const state =
        performance.status === "running"
            ? "running"
            : performance.status === "scoring"
              ? "scoring"
              : "approved";

    return { bout, performance, state };
});

/**
 * PREVIOUS: آخرین اجرای خاتمه‌یافتهٔ همین زمین (bout تکمیل‌شده یا performance با نتیجه).
 * approved = نتیجه منتشرشده دارد.
 */
const previous = computed(() => {
    const exactPrevious = displayCourt.value?.previous_bout_id
        ? findSnapshotBout(displayCourt.value.previous_bout_id)
        : null;
    if (exactPrevious) {
        const winner = exactPrevious.entries.find(
            (entry) => entry.id === exactPrevious.winner_entry_id,
        );
        return {
            bout: exactPrevious,
            winner,
            approved: exactPrevious.performances.every((p) => p.result != null),
            total: winner ? exactPrevious.totals?.[winner.id] ?? null : null,
        };
    }
    const index = courtBouts.value.findIndex(
        (b) => b.id === current.value?.bout.id,
    );
    const candidates = courtBouts.value.slice(0, index === -1 ? undefined : index);
    for (let i = candidates.length - 1; i >= 0; i--) {
        const bout = candidates[i];
        if (bout.status !== "completed") continue;
        const winner = bout.entries.find(
            (entry) => entry.id === bout.winner_entry_id,
        );
        const approved = bout.performances.every((p) => p.result != null);
        return {
            bout,
            winner,
            approved,
            total: winner ? bout.totals?.[winner.id] ?? null : null,
        };
    }
    return null;
});

/**
 * NEXT: اولین bout در انتظار (pending) بعد از اجرای جاری در همین زمین.
 */
const next = computed(() => {
    if (displayCourt.value) {
        const upcoming = displayCourt.value.upcoming?.find(
            (item) => String(item.bout_id) !== String(current.value?.bout?.id),
        );

        return upcoming ? findSnapshotBout(upcoming.bout_id) : null;
    }

    const index = courtBouts.value.findIndex(
        (b) => b.id === current.value?.bout.id,
    );
    const candidates = courtBouts.value.slice(index === -1 ? 0 : index + 1);
    return candidates.find((b) => b.status === "pending") ?? null;
});

const stateLabels = {
    pending: "READY",
    running: "LIVE",
    scoring: "AWAITING SCORES",
    approved: "RESULT APPROVED",
};

const stateClasses = {
    pending: "border-rtds-success text-rtds-success",
    running: "border-rtds-blue text-rtds-blue-soft",
    scoring: "border-rtds-yellow text-rtds-yellow",
    approved: "border-rtds-success text-rtds-success",
};

const durationSeconds = computed(
    () =>
        current.value?.performance.live_timing?.duration_seconds ??
        current.value?.performance.timing?.maximum_duration_seconds ??
        null,
);

const score = (value) =>
    value == null
        ? "—"
        : Number(value).toLocaleString("en-US", {
              minimumFractionDigits: 3,
              maximumFractionDigits: 6,
          });

const formNamesOf = (bout) =>
    bout.performances.map((p) => p.form_name).filter(Boolean);

const formSymbolKeyOf = (bout, performance = bout.performances?.[0]) => {
    if (bout.category?.discipline === "freestyle") return null;

    return performance?.form_badge ?? null;
};

/*
 * TODO [Proposed Backend Contract]:
 * - courtId پارامتر رسمی صفحه (query param از Backend)
 * - previous مرتب‌شده برای همان زمین (current/upcoming از display.courts موجود است)
 * - entry.country / entry.flag_url
 * - logoUrl
 */
</script>

<template>
    <!-- <Head title="liveBoard" /> -->
    <ScoreboardLayout
        :stage="stage"
        :center="tournament?.name ?? ''"
        :category="court ?? ''"
    >
        <template #header>
            <ScoreboardHeader
                :stage="stage"
                :event-title="tournament?.name ?? ''"
                :category="court ?? ''"
            />
        </template>

        <section
            class="flex flex-1 flex-col justify-center gap-4"
            aria-label="Match flow"
        >
            <!-- PREVIOUS -->
            <article
                class="rounded-2xl border border-rtds-border bg-rtds-bg-card p-6"
            >
                <div class="mb-4 flex items-center justify-between">
                    <span
                        class="text-xs font-medium uppercase tracking-widest text-rtds-text-muted"
                        >Previous</span
                    >
                    <span
                        v-if="previous"
                        class="rounded-full border px-3 py-1 text-xs"
                        :class="
                            previous.approved
                                ? stateClasses.approved
                                : stateClasses.scoring
                        "
                    >
                        {{
                            previous.approved
                                ? stateLabels.approved
                                : stateLabels.scoring
                        }}
                    </span>
                </div>

                <template v-if="previous">
                    <div class="flex flex-wrap items-center gap-4">
                        <FormBadge
                            size="sm"
                            :round-label="previous.bout.round.name"
                            :symbol-key="formSymbolKeyOf(previous.bout)"
                            :form-name="
                                formNamesOf(previous.bout)[0] ??
                                `Match ${previous.bout.sequence}`
                            "
                        />
                        <AthleteInfo                                v-if="previous.winner"
                                layout="horizontal"
                                size="sm"
                                :name="previous.winner.name"
                                :side="previous.winner.side"
                                :country="previous.winner.country"
                                :country-code="previous.winner.country_code"
                                :flag-url="previous.winner.flag_url"
                        />
                        <b
                            v-if="previous.total != null"
                            class="mr-auto text-2xl tabular-nums text-rtds-gold-bright"
                            >{{ score(previous.total) }}</b
                        >
                    </div>
                </template>
                <p v-else class="text-sm text-rtds-text-tertiary">
                    No previous performance
                </p>
            </article>

            <!-- ↓ -->
            <div
                class="text-center text-xl text-rtds-text-tertiary"
                aria-hidden="true"
            >
                ↓
            </div>

            <!-- CURRENT -->
            <article
                class="rounded-2xl border-2 p-6"
                :class="
                    current
                        ? current.state === 'running'
                            ? 'border-rtds-blue bg-rtds-blue/5'
                            : current.state === 'pending'
                                ? 'border-rtds-success bg-rtds-success/5'
                                : 'border-rtds-yellow bg-rtds-yellow/5'
                        : 'border-rtds-border bg-rtds-bg-card'
                "
            >
                <div class="mb-4 flex items-center justify-between">
                    <span
                        class="text-xs font-medium uppercase tracking-widest text-rtds-text-muted"
                        >Current</span
                    >
                    <span
                        v-if="current"
                        class="rounded-full border px-3 py-1 text-xs"
                        :class="stateClasses[current.state]"
                    >
                        {{ stateLabels[current.state] }}
                    </span>
                </div>

                <template v-if="current">
                    <div
                        class="flex flex-wrap items-center justify-between gap-4"
                    >
                        <div class="flex flex-wrap items-center gap-4">
                            <FormBadge
                                size="md"
                                :round-label="current.bout.round.name"
                                :symbol-key="
                                    formSymbolKeyOf(current.bout, current.performance)
                                "
                                :form-name="
                                    current.performance.form_name ??
                                    `Match ${current.bout.sequence}`
                                "
                            />
                            <AthleteInfo
                                v-for="entry in current.bout.entries"
                                :key="entry.id"
                                layout="horizontal"
                                size="md"
                            :name="entry.name"
                            :side="entry.side"
                            :country="entry.country"
                            :country-code="entry.country_code"
                            :flag-url="entry.flag_url"
                            />
                        </div>

                        <!-- Timer کوچک؛ همان سه حالت Standby، کنترل از پنل اپراتور -->
                        <Timer
                            v-if="durationSeconds != null && current.state !== 'pending'"
                            size="sm"
                            :duration-seconds="durationSeconds"
                            :started-at="current.performance.started_at"
                            :ended-at="current.performance.ended_at"
                            :server-now="current.performance.server_now"
                        />
                        <span v-else-if="current.state === 'pending'" class="text-sm font-semibold text-rtds-success">
                            Awaiting operator start
                        </span>
                        <span v-else class="text-sm text-rtds-text-secondary">
                            Timer data unavailable
                        </span>
                    </div>
                </template>
                <p v-else class="text-sm text-rtds-text-tertiary">
                    No active performance
                </p>
            </article>

            <!-- ↓ -->
            <div
                class="text-center text-xl text-rtds-text-tertiary"
                aria-hidden="true"
            >
                ↓
            </div>

            <!-- NEXT -->
            <article
                class="rounded-2xl border border-rtds-border bg-rtds-bg-card p-6"
            >
                <div class="mb-4">
                    <span
                        class="text-xs font-medium uppercase tracking-widest text-rtds-text-muted"
                        >Next</span
                    >
                </div>

                <template v-if="next">
                    <div class="flex flex-wrap items-center gap-4">
                        <FormBadge
                            size="sm"
                            :round-label="next.round.name"
                            :symbol-key="formSymbolKeyOf(next)"
                            :form-name="
                                formNamesOf(next)[0] ??
                                `Match ${next.sequence}`
                            "
                        />
                        <AthleteInfo
                            v-for="entry in next.entries"
                            :key="entry.id"
                            layout="horizontal"
                            size="sm"
                                :name="entry.name"
                                :side="entry.side"
                                :country="entry.country"
                                :country-code="entry.country_code"
                                :flag-url="entry.flag_url"
                        />
                    </div>
                </template>
                <p v-else class="text-sm text-rtds-text-tertiary">
                    No next performance scheduled
                </p>
            </article>
        </section>
    </ScoreboardLayout>
</template>
