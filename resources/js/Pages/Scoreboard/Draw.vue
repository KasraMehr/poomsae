<script setup>
import { computed } from "vue";
import { Head } from "@inertiajs/vue3";
import ScoreboardLayout from "../../Shared/Layouts/ScoreboardLayout.vue";
import ScoreboardHeader from "../../Components/scoreboard/ScoreboardHeader.vue";
import FormBadge from "../../Components/scoreboard/FormBadge.vue";
import AthleteInfo from "../../Components/scoreboard/AthleteInfo.vue";

/**
 * صفحهٔ skeleton — pending backend contract.
 *
 * این Page فعلاً فقط ساختار معماری را مشخص می‌کند.
 *
 * منتظر Contract نهایی:
 * - Draw.output_snapshot
 * - entry_members[]
 * - draw_timing
 */
const props = defineProps({
    tournament: {
        type: Object,
        default: null,
    },
});

/*
 * فقط داده‌ای که امروز در snapshot هست:
 * - form_names هر دسته (ترتیب فرم‌ها)
 * - entries هر دسته
 * ترتیب واقعی قرعه (جفت‌ها/شمارهٔ اجرا) هنوز در snapshot نیست.
 */
const categories = computed(() =>
    (props.tournament?.categories ?? []).map((category) => ({
        id: category.id,
        name: category.name,
        forms: category.form_names ?? [],
        entries: category.entries ?? [],
    })),
);

/*
 * TODO / Proposed Contract (مرحلهٔ بعد):
 * - Draw.output_snapshot → ترتیب واقعی قرعه (شمارهٔ اجرا / جفت‌ها)
 * - entry_members[] → عکس و ملیت هر ورزشکار
 * - draw_timing → زمان قرعه‌کشی
 * - آیکون هر فرم (form icon)
 * - logoUrl هدر
 */
</script>

<template>
    <Head title="قرعه‌کشی" />

    <ScoreboardLayout stage="" :center="tournament?.name ?? ''" category="">
        <template #header>
            <ScoreboardHeader :event-title="tournament?.name ?? ''" />
        </template>

        <section
            v-if="categories.length"
            class="flex flex-1 flex-col justify-center gap-8"
        >
            <div
                v-for="category in categories"
                :key="category.id"
                class="rounded-2xl border border-rtds-border bg-rtds-bg-card p-6"
            >
                <h2 class="mb-4">{{ category.name }}</h2>

                <div
                    v-if="category.forms.length"
                    class="mb-6 flex flex-wrap gap-3"
                >
                    <FormBadge
                        v-for="(form, index) in category.forms"
                        :key="form"
                        size="md"
                        :round-label="`فرم ${index + 1}`"
                        :form-name="form"
                    />
                </div>

                <div
                    v-if="category.entries.length"
                    class="grid grid-cols-2 gap-4 max-[850px]:grid-cols-1"
                >
                    <AthleteInfo
                        v-for="entry in category.entries"
                        :key="entry.id"
                        orientation="horizontal"
                        size="sm"
                        :name="entry.name"
                        color="bg-rtds-bg-elevated"
                    />
                </div>
            </div>
        </section>

        <section v-else class="flex flex-1 items-center justify-center">
            <p class="text-rtds-text-secondary">
                Draw page is pending backend contract.
            </p>
        </section>
    </ScoreboardLayout>
</template>
