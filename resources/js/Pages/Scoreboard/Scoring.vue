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

// فقط داده‌ای که امروز در snapshot هست: boutهای در حال اجرا.
const active = computed(() =>
    (props.tournament?.categories ?? []).flatMap((category) =>
        category.rounds.flatMap((round) =>
            round.bouts
                .filter((bout) => bout.status === "running")
                .map((bout) => ({
                    ...bout,
                    category: category.name,
                    round: round.name,
                })),
        ),
    ),
);

const sideLabels = { chung: "چونگ", hong: "هونگ" };

const score = (value) =>
    value == null
        ? "—"
        : Number(value).toLocaleString("fa-IR", {
              minimumFractionDigits: 3,
              maximumFractionDigits: 6,
          });
</script>

<template>
    <Head title="نمایش زنده" />
    <ScoreboardLayout
        :stage="stage"
        :center="tournament?.name ?? ''"
        category=""
    >
        <!--
          ساختار placeholder: فقط داده‌ای که همین الان در snapshot هست.
          Proposed Contract: execution_mode / discipline / photo_url
          → variantهای SINGLE | DOUBLE | SINGLE-FREESTYLE فعلاً رندر نمی‌شوند.
        -->
        <p
            v-if="!active.length"
            class="flex flex-1 items-center justify-center rounded-2xl border border-rtds-border-subtle bg-rtds-bg-card p-12 text-center text-rtds-text-secondary"
        >
            در حال حاضر رقابتی در حال اجرا نیست.
        </p>

        <section
            v-for="bout in active"
            :key="bout.id"
            class="mb-8 last:mb-0"
        >
            <div class="mb-4 flex items-center justify-between">
                <h2>
                    {{ bout.category }} · {{ bout.round }} · رقابت
                    {{ bout.sequence }}
                </h2>
            </div>

            <div class="grid grid-cols-2 gap-6 max-[850px]:grid-cols-1">
                <article
                    v-for="entry in bout.entries"
                    :key="entry.id"
                    class="rounded-2xl border border-rtds-border bg-rtds-bg-card p-6"
                    :class="
                        entry.side === 'chung'
                            ? 'border-t-4 border-t-rtds-blue'
                            : 'border-t-4 border-t-rtds-red'
                    "
                >
                    <span class="mb-2 block text-xs uppercase text-rtds-text-tertiary">
                        {{ sideLabels[entry.side] ?? entry.side }}
                    </span>
                    <h3 class="mb-4 text-2xl">{{ entry.name }}</h3>

                    <div
                        class="flex items-center justify-between border-t border-rtds-border-subtle pt-4"
                    >
                        <span class="text-rtds-text-secondary">میانگین دو فرم</span>
                        <b class="text-3xl tabular-nums">
                            {{ score(bout.totals?.[entry.id]) }}
                        </b>
                    </div>
                </article>
            </div>
        </section>
    </ScoreboardLayout>
</template>
