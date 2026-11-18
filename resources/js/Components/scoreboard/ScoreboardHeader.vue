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

const fitTextStyle = (value) => ({
    "--fit-length": Math.max(Array.from(value || "").length, 1),
});
</script>

<template>
    <header class="shrink-0 px-10 pt-8 pb-4">
        <div class="grid grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)_minmax(0,1fr)] items-center gap-4">
            <div class="scoreboard-header__left flex w-full min-w-0 items-center gap-3">
                <img
                    v-if="logoUrl"
                    :src="logoUrl"
                    alt=""
                    class="h-12 w-auto object-contain"
                />
                <span
                    class="scoreboard-header__fit min-w-0 flex-1 uppercase tracking-wide text-rtds-text-secondary"
                    :style="fitTextStyle(stage)"
                >{{ stage }}</span>
            </div>

            <div class="scoreboard-header__center min-w-0 text-center">
                <h1
                    class="scoreboard-header__fit scoreboard-header__title font-semibold uppercase tracking-wider"
                    :style="fitTextStyle(eventTitle)"
                >{{ eventTitle }}</h1>
                <p
                    class="scoreboard-header__fit scoreboard-header__round mt-1 uppercase tracking-wide text-rtds-text-muted"
                    :class="{ invisible: !roundLabel }"
                    :aria-hidden="!roundLabel"
                    :style="fitTextStyle(roundLabel)"
                >{{ roundLabel || " " }}</p>
            </div>

            <div class="scoreboard-header__right w-full min-w-0 text-right">
                <span
                    class="scoreboard-header__fit uppercase tracking-wide text-rtds-text-secondary"
                    :style="fitTextStyle(category)"
                >{{ category }}</span>
            </div>
        </div>
        <div class="mt-4 h-px w-full bg-rtds-divider" />
    </header>
</template>

<style scoped>
.scoreboard-header__left,
.scoreboard-header__center,
.scoreboard-header__right {
    container-type: inline-size;
}

.scoreboard-header__left,
.scoreboard-header__right {
    min-height: clamp(2rem, 3.2vw, 3.6rem);
}

.scoreboard-header__fit {
    display: block;
    width: 100%;
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
    font-size: clamp(8px, min(3.2vw, calc(160cqi / var(--fit-length))), 3.6rem);
    line-height: 1;
}

.scoreboard-header__title {
    display: flex;
    height: clamp(2rem, 3.2vw, 3.6rem);
    align-items: center;
    justify-content: center;
}

.scoreboard-header__round {
    height: 1rem;
    font-size: clamp(8px, min(1vw, calc(100cqi / var(--fit-length))), 14px);
    line-height: 1rem;
}
</style>
