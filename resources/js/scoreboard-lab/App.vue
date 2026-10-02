<script setup>
import ScoreboardHeader from "../Components/scoreboard/ScoreboardHeader.vue";
import AthleteInfo from "../Components/scoreboard/AthleteInfo.vue";
import NationalityBadge from "../Components/scoreboard/NationalityBadge.vue";
import FormBadge from "../Components/scoreboard/FormBadge.vue";
import SymbolContainer from "../Components/scoreboard/SymbolContainer.vue";
import Timer from "../Components/scoreboard/Timer.vue";
import TotalScoreArc from "../Components/scoreboard/TotalScoreArc.vue";
import ScoringTableBase from "../Components/scoreboard/tables/ScoringTableBase.vue";
import SingleScoringTable from "../Components/scoreboard/tables/SingleScoringTable.vue";
import FreestyleScoringTable from "../Components/scoreboard/tables/FreestyleScoringTable.vue";
import DoubleScoringTable from "../Components/scoreboard/tables/DoubleScoringTable.vue";
import RankingTable from "../Components/scoreboard/tables/RankingTable.vue";
import RankingRow from "../Components/scoreboard/tables/RankingRow.vue";
import {
    headerCases,
    athleteCases,
    nationalityCases,
    formBadgeCases,
    symbolCases,
    medalCases,
    formSymbolStrip,
    timerCases,
    arcCases,
    singleFive,
    singleSeven,
    singleEmpty,
    freestyleFive,
    freestyleSeven,
    doubleFive,
    doubleSeven,
    baseTable,
    placementsRoundRobin,
    placementsKnockout,
    extraRankingRows,
    contractFields,
} from "./data.js";

const sections = [
    { id: "scoreboard-header", label: "ScoreboardHeader" },
    { id: "athlete-info", label: "AthleteInfo" },
    { id: "nationality-badge", label: "NationalityBadge" },
    { id: "form-badge", label: "FormBadge" },
    { id: "symbol-container", label: "SymbolContainer / Medals" },
    { id: "timer", label: "Timer" },
    { id: "total-score-arc", label: "TotalScoreArc" },
    { id: "scoring-table-base", label: "ScoringTableBase" },
    { id: "single-scoring-table", label: "SingleScoringTable" },
    { id: "freestyle-scoring-table", label: "FreestyleScoringTable" },
    { id: "double-scoring-table", label: "DoubleScoringTable" },
    { id: "ranking", label: "RankingTable / RankingRow" },
    { id: "contract", label: "فیلدهای Proposed" },
];

/** نگاشت placements[] پیشنهادی → props های RankingRow (adapter) */
const toRankingRow = (placement) => ({
    rank: placement.rank,
    name: placement.name,
    country: placement.country,
    countryCode: placement.country_code,
    flagUrl: placement.flag_url,
    score: Number(placement.score).toLocaleString("fa-IR"),
    highlight: false,
});

const roundRobinRows = placementsRoundRobin.map(toRankingRow);
const knockoutRows = placementsKnockout.map(toRankingRow);
</script>

<template>
    <div class="min-h-screen bg-rtds-bg text-rtds-text">
        <!-- Lab Header -->
        <header
            class="sticky top-0 z-20 border-b border-rtds-border bg-rtds-bg-secondary/95 backdrop-blur"
        >
            <div class="mx-auto flex max-w-[1500px] flex-wrap items-baseline gap-x-4 gap-y-1 px-6 py-4">
                <h1 class="text-xl font-bold text-rtds-text-light">
                    Scoreboard Lab
                </h1>
                <span
                    class="rounded-full border border-rtds-gold/40 bg-rtds-gold/10 px-3 py-0.5 text-[11px] text-rtds-gold"
                    >Proposed Backend Contract</span
                >
                <p class="text-xs text-rtds-text-muted">
                    محیط ایزولهٔ نمایش کامپوننت‌ها — بدون Inertia · اجرا:
                    <code class="text-rtds-text-secondary">npm run dev</code> سپس
                    <code class="text-rtds-text-secondary"
                        >/resources/js/scoreboard-lab/index.html</code
                    >
                </p>
            </div>
        </header>

        <div class="mx-auto flex max-w-[1500px] gap-6 px-6 py-8">
            <!-- TOC -->
            <aside class="sticky top-24 hidden h-fit w-56 shrink-0 lg:block">
                <nav class="rounded-2xl border border-rtds-border bg-rtds-bg-card p-4">
                    <p class="mb-3 text-[11px] uppercase tracking-widest text-rtds-text-muted">
                        کامپوننت‌ها
                    </p>
                    <ul class="space-y-1 text-sm">
                        <li v-for="s in sections" :key="s.id">
                            <a
                                :href="`#${s.id}`"
                                class="block rounded-md px-2 py-1 text-rtds-text-secondary transition hover:bg-rtds-bg-elevated hover:text-rtds-text-light"
                                >{{ s.label }}</a
                            >
                        </li>
                    </ul>
                </nav>
            </aside>

            <!-- Content -->
            <main class="min-w-0 flex-1 space-y-14 pb-24">
                <!-- Intro -->
                <section class="rounded-2xl border border-rtds-border bg-rtds-bg-card p-6 text-sm leading-7 text-rtds-text-secondary">
                    <p>
                        این صفحه هر ۱۳ کامپوننت <code class="text-rtds-text-light">Components/scoreboard</code>
                        را در همهٔ حالت‌ها رندر می‌کند؛ داده‌ها در
                        <code class="text-rtds-text-light">data.js</code> طبق
                        <code class="text-rtds-text-light">[Proposed Backend Contract]</code>
                        سند <code class="text-rtds-text-light">docs/scoreboard-contract.md</code> ساخته شده‌اند
                        تا شکل نهایی پس از تکمیل Backend را از همین الان ببینیم.
                    </p>
                    <p class="mt-2">
                        برای هر کامپوننت، کارت‌های جدا حاوی توضیح حالت هستند؛ تصاویر به‌صورت
                        data-URI داخلی‌اند و صفحه کاملاً offline کار می‌کند.
                    </p>
                </section>

                <!-- 1. ScoreboardHeader -->
                <section id="scoreboard-header" class="scroll-mt-28 space-y-4">
                    <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-rtds-border-subtle pb-2">
                        <h2 class="text-lg font-bold text-rtds-text-light">ScoreboardHeader</h2>
                        <code class="text-xs text-rtds-text-muted">Components/scoreboard/ScoreboardHeader.vue</code>
                    </div>
                    <div class="grid gap-4 xl:grid-cols-2">
                        <div
                            v-for="c in headerCases"
                            :key="c.title"
                            class="min-w-0 overflow-hidden rounded-2xl border border-rtds-border bg-rtds-bg"
                        >
                            <p class="px-6 pt-4 text-[11px] text-rtds-text-muted">{{ c.title }}</p>
                            <ScoreboardHeader v-bind="c.props" />
                        </div>
                    </div>
                </section>

                <!-- 2. AthleteInfo -->
                <section id="athlete-info" class="scroll-mt-28 space-y-4">
                    <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-rtds-border-subtle pb-2">
                        <h2 class="text-lg font-bold text-rtds-text-light">AthleteInfo</h2>
                        <code class="text-xs text-rtds-text-muted">Components/scoreboard/AthleteInfo.vue</code>
                    </div>
                    <div class="flex flex-col gap-4">
                        <div
                            v-for="c in athleteCases"
                            :key="c.title"
                            class="min-w-0 rounded-2xl border border-rtds-border bg-rtds-bg"
                        >
                            <p class="px-6 pt-4 text-[11px] text-rtds-text-muted">{{ c.title }}</p>
                            <div class="flex min-h-40 items-center justify-center p-6">
                                <AthleteInfo v-bind="c.props" />
                            </div>
                        </div>
                    </div>
                </section>

                <!-- 3. NationalityBadge -->
                <section id="nationality-badge" class="scroll-mt-28 space-y-4">
                    <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-rtds-border-subtle pb-2">
                        <h2 class="text-lg font-bold text-rtds-text-light">NationalityBadge</h2>
                        <code class="text-xs text-rtds-text-muted">Components/scoreboard/NationalityBadge.vue</code>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <div
                            v-for="c in nationalityCases"
                            :key="c.title"
                            class="min-w-0 rounded-2xl border border-rtds-border bg-rtds-bg"
                        >
                            <p class="px-6 pt-4 text-[11px] text-rtds-text-muted">{{ c.title }}</p>
                            <div class="flex min-h-24 items-center justify-center p-6">
                                <NationalityBadge v-bind="c.props" />
                            </div>
                        </div>
                    </div>
                </section>

                <!-- 4. FormBadge -->
                <section id="form-badge" class="scroll-mt-28 space-y-4">
                    <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-rtds-border-subtle pb-2">
                        <h2 class="text-lg font-bold text-rtds-text-light">FormBadge</h2>
                        <code class="text-xs text-rtds-text-muted">Components/scoreboard/FormBadge.vue</code>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <div
                            v-for="c in formBadgeCases"
                            :key="c.title"
                            class="min-w-0 rounded-2xl border border-rtds-border bg-rtds-bg"
                        >
                            <p class="px-6 pt-4 text-[11px] text-rtds-text-muted">{{ c.title }}</p>
                            <div class="flex min-h-28 items-center justify-center p-6">
                                <FormBadge v-bind="c.props" />
                            </div>
                        </div>
                    </div>
                </section>

                <!-- 5. SymbolContainer -->
                <section id="symbol-container" class="scroll-mt-28 space-y-4">
                    <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-rtds-border-subtle pb-2">
                        <h2 class="text-lg font-bold text-rtds-text-light">SymbolContainer / Medals</h2>
                        <code class="text-xs text-rtds-text-muted">Components/scoreboard/SymbolContainer.vue · Shared/Services/symbols.js</code>
                    </div>
                    <p class="px-1 text-xs leading-6 text-rtds-text-muted">
                        مسیر تصویر از داده نمی‌آید: فقط کلید (<code>form_number</code> یا
                        <code>rank</code>) از snapshot می‌آید و map در فرانت آن را به فایل
                        <code>public/images/…</code> وصل می‌کند. تا وقتی فایل‌های webp اضافه شوند،
                        این کارت‌ها خالی می‌مانند.
                    </p>

                    <div class="flex flex-col gap-4">
                        <div
                            v-for="c in symbolCases"
                            :key="c.title"
                            class="min-w-0 rounded-2xl border border-rtds-border bg-rtds-bg"
                        >
                            <p class="px-6 pt-4 text-[11px] text-rtds-text-muted">{{ c.title }}</p>
                            <div class="flex min-h-28 items-center justify-center p-6">
                                <SymbolContainer v-bind="c.props" />
                            </div>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-rtds-border bg-rtds-bg p-6">
                        <p class="mb-4 px-2 text-[11px] text-rtds-text-muted">
                            نوار همهٔ کلیدهای فرم (۱..۱۸) — برای کنترل assetهای موجود
                        </p>
                        <div class="flex flex-wrap items-center gap-4">
                            <span
                                v-for="s in formSymbolStrip"
                                :key="s.key"
                                class="flex flex-col items-center gap-1"
                            >
                                <SymbolContainer :src="s.src" :label="`فرم ${s.key}`" size="md" />
                                <code class="text-[10px] text-rtds-text-muted">{{ s.key }}</code>
                            </span>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-rtds-border bg-rtds-bg p-6">
                        <p class="mb-4 px-2 text-[11px] text-rtds-text-muted">
                            مدال‌ها — از روی <code>rank</code> انتخاب می‌شوند (جایگزین جعبه‌های رنگی قبلی)
                        </p>
                        <div class="flex flex-wrap gap-4">
                            <div
                                v-for="c in medalCases"
                                :key="c.title"
                                class="min-w-40 flex-1 rounded-xl border border-rtds-border bg-rtds-bg-card p-4"
                            >
                                <p class="mb-3 text-[11px] text-rtds-text-muted">{{ c.title }}</p>
                                <div class="flex min-h-16 items-center justify-center">
                                    <SymbolContainer v-bind="c.props" />
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- 6. Timer -->
                <section id="timer" class="scroll-mt-28 space-y-4">
                    <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-rtds-border-subtle pb-2">
                        <h2 class="text-lg font-bold text-rtds-text-light">Timer</h2>
                        <code class="text-xs text-rtds-text-muted">Components/scoreboard/Timer.vue</code>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div
                            v-for="c in timerCases"
                            :key="c.title"
                            class="min-w-0 rounded-2xl border border-rtds-border bg-rtds-bg"
                        >
                            <p class="px-6 pt-4 text-[11px] text-rtds-text-muted">{{ c.title }}</p>
                            <div class="flex min-h-28 items-center justify-center p-6 text-5xl">
                                <Timer v-bind="c.props" />
                            </div>
                        </div>
                    </div>
                </section>

                <!-- 7. TotalScoreArc -->
                <section id="total-score-arc" class="scroll-mt-28 space-y-4">
                    <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-rtds-border-subtle pb-2">
                        <h2 class="text-lg font-bold text-rtds-text-light">TotalScoreArc</h2>
                        <code class="text-xs text-rtds-text-muted">Components/scoreboard/TotalScoreArc.vue</code>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div
                            v-for="c in arcCases"
                            :key="c.title"
                            class="min-w-0 rounded-2xl border border-rtds-border bg-rtds-bg"
                        >
                            <p class="px-6 pt-4 text-[11px] text-rtds-text-muted">{{ c.title }}</p>
                            <div class="flex items-center justify-center p-6">
                                <TotalScoreArc v-bind="c.props" />
                            </div>
                        </div>
                    </div>
                </section>

                <!-- 8. ScoringTableBase -->
                <section id="scoring-table-base" class="scroll-mt-28 space-y-4">
                    <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-rtds-border-subtle pb-2">
                        <h2 class="text-lg font-bold text-rtds-text-light">ScoringTableBase</h2>
                        <code class="text-xs text-rtds-text-muted">Components/scoreboard/tables/ScoringTableBase.vue</code>
                    </div>
                    <div class="min-w-0 rounded-2xl border border-rtds-border bg-rtds-bg p-4">
                        <p class="mb-4 px-2 text-[11px] text-rtds-text-muted">
                            ستون‌ها/ردیف‌های دلخواه از بیرون · judgeCount=7 · highlightColumn=total
                        </p>
                        <ScoringTableBase v-bind="baseTable" />
                    </div>
                </section>

                <!-- 9. SingleScoringTable -->
                <section id="single-scoring-table" class="scroll-mt-28 space-y-4">
                    <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-rtds-border-subtle pb-2">
                        <h2 class="text-lg font-bold text-rtds-text-light">SingleScoringTable</h2>
                        <code class="text-xs text-rtds-text-muted">Components/scoreboard/tables/SingleScoringTable.vue</code>
                    </div>
                    <div class="space-y-4">
                        <div class="min-w-0 rounded-2xl border border-rtds-border bg-rtds-bg p-4">
                            <p class="mb-4 px-2 text-[11px] text-rtds-text-muted">
                                execution_mode=SINGLE · ۵ قاضی — نگاشت judge_scores[] پیشنهادی به ردیف‌ها
                            </p>
                            <SingleScoringTable v-bind="singleFive" />
                        </div>
                        <div class="min-w-0 rounded-2xl border border-rtds-border bg-rtds-bg p-4">
                            <p class="mb-4 px-2 text-[11px] text-rtds-text-muted">
                                ۷ قاضی — حالت فشردهٔ ریسپانسیو
                            </p>
                            <SingleScoringTable v-bind="singleSeven" />
                        </div>
                        <div class="min-w-0 rounded-2xl border border-rtds-border bg-rtds-bg p-4">
                            <p class="mb-4 px-2 text-[11px] text-rtds-text-muted">
                                بدون judge_scores — وضعیت snapshot فعلی (display=true، سلول‌ها «—»)
                            </p>
                            <SingleScoringTable v-bind="singleEmpty" />
                        </div>
                    </div>
                </section>

                <!-- 10. FreestyleScoringTable -->
                <section id="freestyle-scoring-table" class="scroll-mt-28 space-y-4">
                    <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-rtds-border-subtle pb-2">
                        <h2 class="text-lg font-bold text-rtds-text-light">FreestyleScoringTable</h2>
                        <code class="text-xs text-rtds-text-muted">Components/scoreboard/tables/FreestyleScoringTable.vue</code>
                    </div>
                    <div class="grid gap-4">
                        <div class="min-w-0 rounded-2xl border border-rtds-border bg-rtds-bg p-4">
                            <p class="mb-4 px-2 text-[11px] text-rtds-text-muted">
                                SINGLE-FREESTYLE · ۵ قاضی + ستون «اجرا» (ردیف دوم خالی)
                            </p>
                            <FreestyleScoringTable v-bind="freestyleFive" />
                        </div>
                        <div class="min-w-0 rounded-2xl border border-rtds-border bg-rtds-bg p-4">
                            <p class="mb-4 px-2 text-[11px] text-rtds-text-muted">
                                SINGLE-FREESTYLE · ۷ قاضی + ستون «اجرا»
                            </p>
                            <FreestyleScoringTable v-bind="freestyleSeven" />
                        </div>
                    </div>
                </section>

                <!-- 11. DoubleScoringTable -->
                <section id="double-scoring-table" class="scroll-mt-28 space-y-4">
                    <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-rtds-border-subtle pb-2">
                        <h2 class="text-lg font-bold text-rtds-text-light">DoubleScoringTable</h2>
                        <code class="text-xs text-rtds-text-muted">Components/scoreboard/tables/DoubleScoringTable.vue</code>
                    </div>
                    <div class="grid gap-4">
                        <div class="min-w-0 rounded-2xl border border-rtds-border bg-rtds-bg p-4">
                            <p class="mb-4 px-2 text-[11px] text-rtds-text-muted">
                                DOUBLE · ۵ قاضی — هدر دوسطحی به ازای هر ورزشکار
                            </p>
                            <DoubleScoringTable v-bind="doubleFive" />
                        </div>
                        <div class="min-w-0 rounded-2xl border border-rtds-border bg-rtds-bg p-4">
                            <p class="mb-4 px-2 text-[11px] text-rtds-text-muted">
                                DOUBLE · ۷ قاضی
                            </p>
                            <DoubleScoringTable v-bind="doubleSeven" />
                        </div>
                    </div>
                </section>

                <!-- 12. RankingTable / RankingRow -->
                <section id="ranking" class="scroll-mt-28 space-y-4">
                    <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-rtds-border-subtle pb-2">
                        <h2 class="text-lg font-bold text-rtds-text-light">RankingTable / RankingRow</h2>
                        <code class="text-xs text-rtds-text-muted">Components/scoreboard/tables/RankingTable.vue · RankingRow.vue</code>
                    </div>
                    <div class="grid gap-4 xl:grid-cols-2">
                        <div class="min-w-0 rounded-2xl border border-rtds-border bg-rtds-bg p-4">
                            <p class="mb-4 px-2 text-[11px] text-rtds-text-muted">
                                format=round_robin — از placements[] پیشنهادی (مدال از rank مشتق می‌شود)
                            </p>
                            <RankingTable :rows="roundRobinRows" />
                        </div>
                        <div class="space-y-4">
                            <div class="min-w-0 rounded-2xl border border-rtds-border bg-rtds-bg p-4">
                                <p class="mb-4 px-2 text-[11px] text-rtds-text-muted">
                                    format=knockout — با همان placements[] یکدست
                                </p>
                                <RankingTable :rows="knockoutRows" />
                            </div>
                            <div class="min-w-0 rounded-2xl border border-rtds-border bg-rtds-bg p-4">
                                <p class="mb-4 px-2 text-[11px] text-rtds-text-muted">حالت‌های تک‌ردیف RankingRow</p>
                                <div class="space-y-3">
                                    <RankingRow
                                        v-for="c in extraRankingRows"
                                        :key="c.title"
                                        v-bind="c.props"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- 13. Contract summary -->
                <section id="contract" class="scroll-mt-28 space-y-4">
                    <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-rtds-border-subtle pb-2">
                        <h2 class="text-lg font-bold text-rtds-text-light">فیلدهای [Proposed Backend Contract]</h2>
                        <code class="text-xs text-rtds-text-muted">docs/scoreboard-contract.md — بخش ۴</code>
                    </div>
                    <div class="overflow-x-auto rounded-2xl border border-rtds-border bg-rtds-bg-card p-4">
                        <table class="w-full text-sm">
                            <thead>
                                <tr>
                                    <th class="border-b border-rtds-border bg-rtds-bg-elevated px-3 py-2 text-right text-xs text-rtds-text-muted">#</th>
                                    <th class="border-b border-rtds-border bg-rtds-bg-elevated px-3 py-2 text-right text-xs text-rtds-text-muted">فیلد</th>
                                    <th class="border-b border-rtds-border bg-rtds-bg-elevated px-3 py-2 text-right text-xs text-rtds-text-muted">شکل پیشنهادی</th>
                                    <th class="border-b border-rtds-border bg-rtds-bg-elevated px-3 py-2 text-right text-xs text-rtds-text-muted">مصرف‌کننده</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="f in contractFields" :key="f.n">
                                    <td class="border-b border-rtds-border-subtle px-3 py-2 align-top tabular-nums text-rtds-text-muted">{{ f.n }}</td>
                                    <td class="border-b border-rtds-border-subtle px-3 py-2 align-top">
                                        <code class="rounded bg-rtds-bg-elevated px-1.5 py-0.5 text-xs text-rtds-gold-bright">{{ f.field }}</code>
                                    </td>
                                    <td class="border-b border-rtds-border-subtle px-3 py-2 align-top text-rtds-text-secondary">{{ f.shape }}</td>
                                    <td class="border-b border-rtds-border-subtle px-3 py-2 align-top text-rtds-text-secondary">{{ f.consumers }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p class="text-xs leading-6 text-rtds-text-muted">
                        نکته: این صفحه فقط در dev است و به ورودی‌های
                        <code class="text-rtds-text-secondary">vite.config.js</code> اضافه نشده؛
                        هیچ فایل موجودی برای ساخت آن تغییر نکرده است.
                    </p>
                </section>
            </main>
        </div>
    </div>
</template>
