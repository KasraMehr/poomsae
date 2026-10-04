<script setup>
import { computed } from "vue";
import { Head } from "@inertiajs/vue3";
import ScoreboardLayout from "../../Shared/Layouts/ScoreboardLayout.vue";
import ScoreboardHeader from "../../Components/scoreboard/ScoreboardHeader.vue";
import AthleteInfo from "../../Components/scoreboard/AthleteInfo.vue";
import SymbolContainer from "../../Components/scoreboard/SymbolContainer.vue";
import { medalSymbol } from "../../Shared/Services/symbols";

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
            v-if="topFour.length"
            class="flex flex-1 flex-col justify-center gap-6"
        >
            <div
                v-for="(row, index) in topFour"
                :key="row.id"
                class="flex items-center gap-5 rounded-2xl border border-rtds-border bg-rtds-bg-card p-6"
            >
                <SymbolContainer
                    :src="medalSymbol(index + 1)"
                    :label="`رتبهٔ ${index + 1}`"
                    size="lg"
                />
                <AthleteInfo
                    layout="horizontal"
                    size="lg"
                    :name="row.name"
                    :number="row.rank"
                />
                <b
                    class="mr-auto text-2xl tabular-nums text-rtds-text-secondary"
                >
                    {{ row.wins }} برد
                </b>
            </div>
        </section>

        <section v-else class="flex flex-1 items-center justify-center">
            <p class="text-rtds-text-secondary">
                Top Four page is pending backend contract.
            </p>
        </section>
    </ScoreboardLayout>
</template>
