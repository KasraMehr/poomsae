<script setup>
defineProps({
    stage: { type: String, default: "" },
    center: { type: String, default: "" },
    category: { type: String, default: "" },
});
</script>

<template>
    <div
        class="relative min-h-screen w-full overflow-hidden bg-rtds-bg text-rtds-text font-rtds"
    >
        <!-- Broadcast Backdrop -->
        <div class="pointer-events-none absolute inset-0" aria-hidden="true">
            <!-- Up/left for blue -->
            <div
                class="absolute inset-0"
                style="
                    background: radial-gradient(
                        circle at 0% 0%,
                        color-mix(
                                in srgb,
                                var(--color-rtds-glow-blue) 18%,
                                transparent
                            )
                            0%,
                        color-mix(
                                in srgb,
                                var(--color-rtds-glow-blue) 8%,
                                transparent
                            )
                            25%,
                        transparent 55%
                    );
                "
            />

            <!-- down/right for red-->
            <div
                class="absolute inset-0"
                style="
                    background: radial-gradient(
                        circle at 100% 100%,
                        color-mix(
                                in srgb,
                                var(--color-rtds-glow-red) 18%,
                                transparent
                            )
                            0%,
                        color-mix(
                                in srgb,
                                var(--color-rtds-glow-red) 8%,
                                transparent
                            )
                            25%,
                        transparent 55%
                    );
                "
            />
        </div>

        <!-- Content -->
        <div class="relative z-10 flex min-h-screen w-full flex-col">
            <!-- -- Header -- -->
            <!--
              Pages می‌توانند هدر خودشان (ScoreboardHeader) را از slot بدهند؛
              Shared هیچ وابستگی به Components/scoreboard نمی‌گیرد.
            -->
            <slot name="header">
                <header class="shrink-0 px-10 pt-8 pb-4">
                    <div class="grid grid-cols-3 items-center">
                    <div
                        class="justify-self-start text-left text-lg uppercase tracking-wide text-rtds-text-secondary"
                    >
                        {{ stage }}
                    </div>
                    <div
                        class="text-center text-2xl font-semibold uppercase tracking-wider"
                    >
                        {{ center }}
                    </div>
                    <div
                        class="justify-self-end text-right text-lg uppercase tracking-wide text-rtds-text-secondary"
                    >
                        {{ category }}
                    </div>
                </div>
                    <div class="mt-4 h-px w-full bg-rtds-border" />
                </header>
            </slot>
            <!-- -- Main -- -->
            <main class="flex flex-1 flex-col px-10 pb-10">
                <slot />
            </main>
        </div>
    </div>
</template>
