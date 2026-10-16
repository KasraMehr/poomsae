<script setup>
/**
 * هدر مشترک همهٔ صفحات Scoreboard: لوگو + نام رویداد + اطلاعات دور.
 * (Ownership در Components/scoreboard است؛ Layout فقط چیدمان می‌دهد.)
 */
defineProps({
    logoUrl: { type: String, default: "" },
    stage: { type: String, default: "" },
    eventTitle: { type: String, default: "" },
    category: { type: String, default: "" },
    roundLabel: { type: String, default: "" },
});
</script>

<template>
    <header class="shrink-0 px-10 pt-8 pb-4">
        <div class="grid grid-cols-3 items-center gap-4">
            <div class="flex items-center gap-3 justify-self-start">
                <img
                    v-if="logoUrl"
                    :src="logoUrl"
                    alt=""
                    class="h-12 w-auto object-contain"
                />
                <span
                    class="text-lg uppercase tracking-wide text-rtds-text-secondary"
                    >{{ stage }}</span
                >
            </div>

            <div class="text-center">
                <h1 class="text-2xl font-semibold uppercase tracking-wider">
                    {{ eventTitle }}
                </h1>
                <p
                    v-if="roundLabel"
                    class="mt-1 text-sm uppercase tracking-wide text-rtds-text-muted"
                >
                    {{ roundLabel }}
                </p>
            </div>

            <span
                class="justify-self-end text-right text-lg uppercase tracking-wide text-rtds-text-secondary"
                >{{ category }}</span
            >
        </div>
        <div class="mt-4 h-px w-full bg-rtds-divider" />
    </header>
</template>
