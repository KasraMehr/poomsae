<script setup>
import { Link, usePage } from "@inertiajs/vue3";

const page = usePage();

defineProps({
    stage: { type: String, default: "" },
    center: { type: String, default: "" },
    category: { type: String, default: "" },
});
</script>

<template>
    <div
        dir="ltr"
        class="relative min-h-screen w-full overflow-hidden bg-rtds-bg text-rtds-text font-rtds"
    >
        <Link
            :href="page.props.urls.logout"
            method="post"
            as="button"
            aria-label="خروج از حساب کاربری"
            title="خروج از حساب کاربری"
            class="group fixed bottom-3 left-3 z-50 grid h-7 w-7 place-items-center rounded-md border border-white/80 bg-black/25 text-white opacity-0 shadow-sm transition-opacity duration-200 hover:opacity-100 focus-visible:opacity-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white"
        >
            <svg
                aria-hidden="true"
                viewBox="0 0 20 20"
                class="h-3.5 w-3.5"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
            >
                <path d="M8 4H4.8a.8.8 0 0 0-.8.8v10.4a.8.8 0 0 0 .8.8H8M12 6l4 4-4 4m4-4H7" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
        </Link>

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
