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
const courts = computed(() => props.tournament?.courts ?? []);
const finished = computed(() => props.tournament?.status === "completed");
</script>

<template>
    <Head title="آمادگی" />
    <ScoreboardLayout
        :stage="stage"
        :center="tournament?.name ?? ''"
        category=""
    >
        <!--
          ساختار placeholder: فقط داده‌ای که همین الان در snapshot هست.
          Proposed Contract: entry_type / discipline / photo_url
        -->
        <section
            class="flex flex-1 flex-col items-center justify-center gap-6 text-center"
        >
            <p class="text-4xl font-semibold uppercase">
                {{ finished ? "مسابقه پایان یافت" : "آمادهٔ اجرای بعدی" }}
            </p>
            <p class="text-rtds-text-secondary">
                نتایج فقط پس از تأیید اپراتور منتشر می‌شوند.
            </p>

            <div
                v-if="courts.length"
                class="mt-4 flex flex-wrap justify-center gap-3"
            >
                <span
                    v-for="court in courts"
                    :key="court.id"
                    class="rounded-lg border border-rtds-border bg-rtds-bg-card px-4 py-2 text-sm text-rtds-text-secondary"
                >
                    {{ court.name }}
                </span>
            </div>
        </section>
    </ScoreboardLayout>
</template>
