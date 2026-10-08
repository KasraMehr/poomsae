<script setup>
import { computed } from "vue";
import ScoreboardLayout from "../../Shared/Layouts/ScoreboardLayout.vue";
import ScoreboardHeader from "../../Components/scoreboard/ScoreboardHeader.vue";
import AthleteInfo from "../../Components/scoreboard/AthleteInfo.vue";
import FormBadge from "../../Components/scoreboard/FormBadge.vue";

const props = defineProps({
    draw: {
        type: Object,
        default: null,
    },
    tournament: {
        type: Object,
        default: null,
    },
});

const drawData = computed(() => {
    if (props.draw) {
        return props.draw;
    }

    const category = props.tournament?.categories?.[0] ?? null;

    if (!category) {
        return null;
    }

    return {
        mode: "single",
        stage: "",
        title: props.tournament?.name ?? "",
        category: category.name ?? "",
        entries: category.entries ?? [],
        forms: (category.form_names ?? []).map((name, index) => ({
            round_label: `R - ${index + 1}`,
            form_number: index + 1,
            symbol_key: null,
            form_name: name,
        })),
    };
});

const mode = computed(() =>
    drawData.value?.mode === "double" ? "double" : "single",
);

const entries = computed(() => drawData.value?.entries ?? []);
const forms = computed(() => drawData.value?.forms ?? []);

const singleEntry = computed(() => entries.value[0] ?? null);
const doubleEntries = computed(() => entries.value.slice(0, 2));

const stage = computed(() => drawData.value?.stage ?? "");
const title = computed(() => drawData.value?.title ?? "");
const category = computed(() => drawData.value?.category ?? "");
</script>

<template>
    <div dir="ltr">
        <ScoreboardLayout>
            <template #header>
                <ScoreboardHeader
                    size="lg"
                    :stage="stage"
                    :event-title="title"
                    :category="category"
                />
            </template>

            <main class="flex min-h-0 flex-1 flex-col items-center px-2">
                <!-- SINGLE -->
                <section
                    v-if="mode === 'single' && singleEntry"
                    class="flex w-full flex-col items-center pt-10"
                >
                    <AthleteInfo
                        layout="horizontal"
                        size="xxl"
                        :number="singleEntry.number"
                        :name="singleEntry.name"
                        :country="singleEntry.country"
                        :country-code="singleEntry.country_code"
                        :flag-url="singleEntry.flag_url"
                        side="chung"
                    />
                </section>

                <!-- DOUBLE
                     آماده برای حالت دو نفرهٔ Chung / Hong.
                     این حالت pair/team نیست.
                -->
                <section
                    v-else-if="mode === 'double'"
                    class="grid w-full grid-cols-2 gap-10 pt-10"
                >
                    <AthleteInfo
                        v-for="entry in doubleEntries"
                        :key="entry.id"
                        layout="vertical-under"
                        size="xl"
                        :number="entry.number"
                        :name="entry.name"
                        :country="entry.country"
                        :country-code="entry.country_code"
                        :flag-url="entry.flag_url"
                        :side="entry.side"
                    />
                </section>

                <!-- FORM DRAW -->
                <section
                    v-if="forms.length"
                    class="flex w-full flex-1 items-center justify-center"
                >
                    <div
                        class="flex flex-col items-center justify-center gap-12"
                    >
                        <FormBadge
                            v-for="form in forms"
                            :key="form.round_label"
                            class="w-full"
                            size="xxxl"
                            :round-label="form.round_label"
                            :form-number="form.form_number"
                            :symbol-key="form.symbol_key"
                            :form-name="form.form_name"
                        />
                    </div>
                </section>

                <section v-else class="flex flex-1 items-center justify-center">
                    <p class="text-lg text-rtds-text-secondary">
                        No draw data available.
                    </p>
                </section>
            </main>
        </ScoreboardLayout>
    </div>
</template>
