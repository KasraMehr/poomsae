<script setup>
import { Head } from "@inertiajs/vue3";
import ScoreboardLayout from "../../Shared/Layouts/ScoreboardLayout.vue";

/**
 * صفحهٔ skeleton — pending backend contract.
 *
 * این Page فعلاً از Backend رندر نمی‌شود.
 * هیچ controller/route‌ای تغییر نکرده است.
 *
 * منتظر Contract نهایی:
 * - placements[]
 * - photo_url
 * - medal
 */

defineProps({
    tournament: {
        type: Object,
        default: null,
    },
});
</script>

<template>
    <Head title="چهار نفر برتر" />

    <ScoreboardLayout stage="" :center="tournament?.name ?? ''" category="">
        <section class="flex flex-1 items-center justify-center">
            <p class="text-rtds-text-secondary">
                Top Four page is pending backend contract.
            </p>
        </section>
    </ScoreboardLayout>
</template>
