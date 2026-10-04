<script setup>
import { Head } from "@inertiajs/vue3";
import ScoreboardLayout from "../../Shared/Layouts/ScoreboardLayout.vue";

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

defineProps({
    tournament: {
        type: Object,
        default: null,
    },
});
</script>

<template>
    <Head title="قرعه‌کشی" />

    <ScoreboardLayout stage="" :center="tournament?.name ?? ''" category="">
        <section class="flex flex-1 items-center justify-center">
            <p class="text-rtds-text-secondary">
                Draw page is pending backend contract.
            </p>
        </section>
    </ScoreboardLayout>
</template>
