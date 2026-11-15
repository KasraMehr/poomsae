<script setup>
import { computed } from "vue";
import ScoreboardLayout from "../../Shared/Layouts/ScoreboardLayout.vue";
import ScoreboardHeader from "../../Components/scoreboard/ScoreboardHeader.vue";
import StandbyCard from "../../Components/scoreboard/StandbyCard.vue";

/**
 * LiveBoard — صفحهٔ مستقل broadcast برای نمایش جریان مسابقهٔ «یک زمین»:
 *
 *   PREVIOUS ↓ CURRENT ↓ NEXT
 *
 * stateهای CURRENT (طبق قرارداد):
 * - running  = اجرای جاری
 * - scoring  = اجرا تمام شده و در انتظار نتیجه
 * - approved = نتیجه تأیید/منتشر شده
 *
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

const withEntryDetails = (bout) => {
    if (!bout) return null;

    const categoryEntries = bout.category?.entries ?? [];

    return {
        ...bout,
        entries: (bout.entries ?? []).map((entry) => {
            const details = categoryEntries.find(
                (item) => String(item.id) === String(entry.id),
            );

            return {
                ...entry,
                short_name: details?.short_name ?? entry.short_name,
                country_code: details?.country_code ?? entry.country_code,
                club: details?.club ?? entry.club,
                members: details?.members ?? entry.members,
            };
        }),
    };
};

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
                performances: item.performances ?? bout.performances,
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
                return { bout, performance, performances: [performance], state: "pending" };
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

    return { bout, performance, performances: bout.performances, state };
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
            class="flex flex-1 flex-col justify-center gap-3"
            aria-label="Match flow"
        >
            <StandbyCard
                variant="previous"
                :bout="withEntryDetails(previous?.bout)"
                :approved="previous?.approved ?? false"
                :winner-entry-id="previous?.bout?.winner_entry_id"
                :total="previous?.total"
            />

            <div class="text-center text-xl text-rtds-text-tertiary" aria-hidden="true">↓</div>

            <StandbyCard
                variant="current"
                :bout="withEntryDetails(current?.bout)"
                :performance="current?.performance"
                :performances="current?.performances ?? []"
                :state="current?.state ?? 'pending'"
            />

            <div class="text-center text-xl text-rtds-text-tertiary" aria-hidden="true">↓</div>

            <StandbyCard
                variant="next"
                :bout="withEntryDetails(next)"
                :performance="next?.performances?.[0]"
            />
        </section>
    </ScoreboardLayout>
</template>
