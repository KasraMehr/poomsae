<script setup>
import { computed } from "vue";
import { Head } from "@inertiajs/vue3";
import ScoreboardLayout from "../../Shared/Layouts/ScoreboardLayout.vue";
import ScoreboardHeader from "../../Components/scoreboard/ScoreboardHeader.vue";
import TopFourTable from "../../Components/scoreboard/tables/TopFourTable.vue";

/**
 * صفحهٔ skeleton — pending backend contract.
 *
 * این Page فعلاً از Backend رندر نمی‌شود.
 * هیچ controller/route‌ای تغییر نکرده است.
 *
 * منتظر Contract نهایی:
 * - placements[] (مدال از روی rank نمایش داده می‌شود؛ فیلد جدا لازم نیست)
 */
const props = defineProps({
    tournament: {
        type: Object,
        default: null,
    },
});

/*
 * فقط داده‌ای که امروز در snapshot هست:
 * standings فقط برای format=round_robin وجود دارد؛ ۴ ردیف اول آن Top Four است.
 * برای knockout هنوز placements[] نداریم (TODO در Contract).
 */
const topFour = computed(() => {
    const rows =
        props.tournament?.categories?.flatMap(
            (category) => category.standings ?? [],
        ) ?? [];
    return rows.slice(0, 4);
});

/**
 * props جدید TopFourTable — تا رسیدن placements[] فقط rank/name از standings
 * می‌آید و score فعلاً همان تعداد برد است (عددی نهایی با placements[].score جایگزین می‌شود).
 */
const topFourRows = computed(() =>
    topFour.value.map((row) => ({
        rank: row.rank,
        name: row.name,
        score: row.wins ?? null,
    })),
);

/*
 * TODO / Proposed Contract (مرحلهٔ بعد):
 * - placements[] (مخصوص knockout — الان فقط round_robin پوشش داده می‌شود)
 * - country / country_code / flag هر ورزشکار
 * - logoUrl هدر
 */
</script>

<template>
    <Head title="چهار نفر برتر" />

    <ScoreboardLayout stage="" :center="tournament?.name ?? ''" category="">
        <template #header>
            <ScoreboardHeader :event-title="tournament?.name ?? ''" />
        </template>

        <section
            v-if="topFourRows.length"
            class="flex flex-1 flex-col justify-center"
        >
            <TopFourTable :rows="topFourRows" />
        </section>

        <section v-else class="flex flex-1 items-center justify-center">
            <p class="text-rtds-text-secondary">
                Top Four page is pending backend contract.
            </p>
        </section>
    </ScoreboardLayout>
</template>
