<script setup>
import { computed } from "vue";
import { Head } from "@inertiajs/vue3";
import ScoreboardLayout from "../../Shared/Layouts/ScoreboardLayout.vue";

/**
 * صفحهٔ placeholder — هنوز از Backend رندر نمی‌شود
 * (هیچ controller/route‌ای دست نخورده است؛ repoint به بعد از Contract نهایی موکول است).
 * تا آن زمان props اختیاری است و همهٔ دسترسی‌ها null-safe می‌مانند.
 */
const props = defineProps({
    tournament: { type: Object, default: null },
});

const statusLabels = {
    draft: "پیش‌نویس",
    ready: "آماده",
    running: "در حال برگزاری",
    completed: "پایان‌یافته",
};

const stage = computed(() => statusLabels[props.tournament?.status] ?? "");

/*
 * فقط داده‌ای که امروز در snapshot هست:
 * - standings فقط برای format=round_robin
 * - champion فقط برای format=knockout
 * Proposed Contract: placements[]  (برای هر قالبی رتبه‌بندی یکدست بدهد)
 */
const categories = computed(() =>
    (props.tournament?.categories ?? []).map((category) => ({
        id: category.id,
        name: category.name,
        standings: category.standings ?? [],
        champion:
            category.champion_id != null
                ? (category.entries.find(
                      (entry) => entry.id === category.champion_id,
                  )?.name ?? null)
                : null,
    })),
);
</script>

<template>
    <Head title="رتبه‌بندی" />
    <ScoreboardLayout
        :stage="stage"
        :center="tournament?.name ?? ''"
        category=""
    >
        <section
            v-for="category in categories"
            :key="category.id"
            class="mb-8 last:mb-0"
        >
            <div class="mb-4 flex items-center justify-between">
                <h2>{{ category.name }}</h2>
                <b
                    v-if="category.champion"
                    class="rounded-full border border-rtds-yellow-dark bg-rtds-bg-card px-4 py-1 text-sm text-rtds-yellow"
                >
                    قهرمان: {{ category.champion }}
                </b>
            </div>

            <table v-if="category.standings.length" class="w-full text-right">
                <thead>
                    <tr>
                        <th
                            class="bg-rtds-bg-card text-rtds-text-secondary"
                            scope="col"
                        >
                            رتبه
                        </th>
                        <th
                            class="bg-rtds-bg-card text-rtds-text-secondary"
                            scope="col"
                        >
                            ورزشکار
                        </th>
                        <th
                            class="bg-rtds-bg-card text-rtds-text-secondary"
                            scope="col"
                        >
                            برد
                        </th>
                        <th
                            class="bg-rtds-bg-card text-rtds-text-secondary"
                            scope="col"
                        >
                            بازی
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in category.standings" :key="row.id">
                        <td class="border-b border-rtds-border">
                            {{ row.rank }}
                        </td>
                        <td class="border-b border-rtds-border">
                            {{ row.name }}
                        </td>
                        <td class="border-b border-rtds-border">
                            {{ row.wins }}
                        </td>
                        <td class="border-b border-rtds-border">
                            {{ row.played }}
                        </td>
                    </tr>
                </tbody>
            </table>

            <p
                v-else-if="!category.champion"
                class="rounded-xl border border-rtds-border-subtle bg-rtds-bg-card p-6 text-center text-rtds-text-secondary"
            >
                رتبه‌بندی این دسته هنوز در دسترس نیست.
            </p>
        </section>
    </ScoreboardLayout>
</template>
