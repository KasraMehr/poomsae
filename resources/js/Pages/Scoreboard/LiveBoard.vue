<script setup>
import { computed } from "vue";
import { Head } from "@inertiajs/vue3";
import ScoreboardLayout from "../../Shared/Layouts/ScoreboardLayout.vue";
import ScoreboardHeader from "../../Components/scoreboard/ScoreboardHeader.vue";
import AthleteInfo from "../../Components/scoreboard/AthleteInfo.vue";
import FormBadge from "../../Components/scoreboard/FormBadge.vue";
import Timer from "../../Components/scoreboard/Timer.vue";

/**
 * LiveBoard — صفحهٔ مستقل broadcast برای نمایش جریان مسابقهٔ «یک زمین»:
 *
 *   PREVIOUS ↓ CURRENT ↓ NEXT
 *
 * stateهای CURRENT (طبق قرارداد):
 * - running  = اجرای فیزیکی فعلی + Timer (در حال شمارش)
 * - scoring  = اجرا تمام شده، Timer متوقف (روی زمان پایان)، در انتظار نتیجه
 * - approved = نتیجه تأیید/منتشر شده
 *
 * Timer مالکیت lifecycle اجرا را دارد؛ این صفحه فقط نمایش‌دهنده است.
 * صفحهٔ صف اجرا/زمان‌بندی نیست — فقط وضعیت یک زمین.
 *
 * TODO [Proposed Backend Contract]: انتخاب زمین (courtId) از query/param بیاید؛
 * تا آن زمان prop اختیاری است و fallback به اولین زمین snapshot می‌رود.
 */
const props = defineProps({
    tournament: { type: Object, default: null },
    courtId: { type: [Number, String], default: null },
});

const statusLabels = {
    draft: "پیش‌نویس",
    ready: "آماده",
    running: "در حال برگزاری",
    completed: "پایان‌یافته",
};

const stage = computed(() => statusLabels[props.tournament?.status] ?? "");

// زمینِ انتخابی (fallback: اولین زمین snapshot — دادهٔ واقعی، نه mock)
const court = computed(() => {
    const courts = props.tournament?.courts ?? [];
    if (props.courtId != null) {
        return courts.find((c) => c.id === props.courtId)?.name ?? null;
    }
    return courts[0]?.name ?? null;
});

const courtId = computed(() => {
    const courts = props.tournament?.courts ?? [];
    if (props.courtId != null) {
        return courts.find((c) => c.id === props.courtId)?.id ?? props.courtId;
    }
    return courts[0]?.id ?? null;
});

// همهٔ boutهای همین زمین، مرتب شده بر اساس ترتیب واقعی snapshot
const courtBouts = computed(() => {
    if (courtId.value == null) return [];
    const out = [];
    for (const category of props.tournament?.categories ?? []) {
        for (const round of category.rounds) {
            for (const bout of round.bouts) {
                if (bout.court_id !== courtId.value) continue;
                out.push({ ...bout, category, round });
            }
        }
    }
    return out;
});

/**
 * CURRENT: اجرای در حال اجرا (running) یا اجرایی که تمام شده و منتظر نتیجه است (scoring).
 */
const current = computed(() => {
    const bout = courtBouts.value.find(
        (b) =>
            b.status === "running" ||
            b.performances.some((p) => ["running", "scoring"].includes(p.status)),
    );
    if (!bout) return null;

    const performance =
        bout.performances.find((p) =>
            ["running", "scoring"].includes(p.status),
        ) ?? bout.performances[0];

    const state =
        performance.status === "running"
            ? "running"
            : performance.status === "scoring"
              ? "scoring"
              : "approved";

    return { bout, performance, state };
});

/**
 * PREVIOUS: آخرین اجرای خاتمه‌یافتهٔ همین زمین (bout تکمیل‌شده یا performance با نتیجه).
 * approved = نتیجه منتشرشده دارد.
 */
const previous = computed(() => {
    const index = courtBouts.value.findIndex(
        (b) => b.id === current.value?.bout.id,
    );
    const candidates = courtBouts.value.slice(0, index === -1 ? undefined : index);
    for (let i = candidates.length - 1; i >= 0; i--) {
        const bout = candidates[i];
        if (bout.status !== "completed") continue;
        const winner = bout.entries.find(
            (entry) => entry.id === bout.winner_entry_id,
        );
        const approved = bout.performances.every((p) => p.result != null);
        return {
            bout,
            winner,
            approved,
            total: winner ? bout.totals?.[winner.id] ?? null : null,
        };
    }
    return null;
});

/**
 * NEXT: اولین bout در انتظار (pending) بعد از اجرای جاری در همین زمین.
 */
const next = computed(() => {
    const index = courtBouts.value.findIndex(
        (b) => b.id === current.value?.bout.id,
    );
    const candidates = courtBouts.value.slice(index === -1 ? 0 : index + 1);
    return candidates.find((b) => b.status === "pending") ?? null;
});

const stateLabels = {
    running: "در حال اجرا",
    scoring: "اجرای پایان یافت — در انتظار نتیجه",
    approved: "نتیجه تأیید شده",
};

const stateClasses = {
    running: "border-rtds-blue text-rtds-blue-soft",
    scoring: "border-rtds-yellow text-rtds-yellow",
    approved: "border-rtds-success text-rtds-success",
};

/**
 * مدت استاندارد اجرا به ثانیه — از تنظیمات مسابقه (execution_duration_seconds).
 * تا وقتی بک‌اند فیلد را ندهد، مقدار پیش‌فرض ۹۰ ثانیه استفاده می‌شود.
 */
const DEFAULT_EXECUTION_SECONDS = 90;

const durationSeconds = computed(
    () => props.tournament?.execution_duration_seconds ?? DEFAULT_EXECUTION_SECONDS
);

const score = (value) =>
    value == null
        ? "—"
        : Number(value).toLocaleString("fa-IR", {
              minimumFractionDigits: 3,
              maximumFractionDigits: 6,
          });

const formNamesOf = (bout) =>
    bout.performances.map((p) => p.form_name).filter(Boolean);

/*
 * TODO [Proposed Backend Contract] (مرحلهٔ بعد):
 * - courtId پارامتر رسمی صفحه (query param از Backend)
 * - timeline سراسری previous/current/next (الان از روی ترتیب snapshot و یک زمین استخراج می‌شود)
 * - entry country / country_code / flag_url
 * - logoUrl
 * - execution_duration_seconds (برای نمایش نسبی Timer؛ الان فقط مطلق داریم)
 */
</script>

<template>
    <Head title="جریان زنده" />
    <ScoreboardLayout
        :stage="stage"
        :center="tournament?.name ?? ''"
        :category="court ?? ''"
    >
        <template #header>
            <ScoreboardHeader
                :stage="stage"
                :event-title="tournament?.name ?? ''"
                :category="court ?? ''"
            />
        </template>

        <section
            class="flex flex-1 flex-col justify-center gap-4"
            aria-label="جریان مسابقه"
        >
            <!-- PREVIOUS -->
            <article
                class="rounded-2xl border border-rtds-border bg-rtds-bg-card p-6"
            >
                <div class="mb-4 flex items-center justify-between">
                    <span
                        class="text-xs font-medium uppercase tracking-widest text-rtds-text-muted"
                        >Previous · اجرای قبلی</span
                    >
                    <span
                        v-if="previous"
                        class="rounded-full border px-3 py-1 text-xs"
                        :class="
                            previous.approved
                                ? stateClasses.approved
                                : stateClasses.scoring
                        "
                    >
                        {{
                            previous.approved
                                ? stateLabels.approved
                                : stateLabels.scoring
                        }}
                    </span>
                </div>

                <template v-if="previous">
                    <div class="flex flex-wrap items-center gap-4">
                        <FormBadge
                            size="sm"
                            :round-label="previous.bout.round.name"
                            :form-name="
                                formNamesOf(previous.bout)[0] ??
                                `رقابت ${previous.bout.sequence}`
                            "
                        />
                        <AthleteInfo                                v-if="previous.winner"
                                layout="horizontal"
                                size="sm"
                                :name="previous.winner.name"
                                :side="previous.winner.side"
                        />
                        <b
                            v-if="previous.total != null"
                            class="mr-auto text-2xl tabular-nums text-rtds-gold-bright"
                            >{{ score(previous.total) }}</b
                        >
                    </div>
                </template>
                <p v-else class="text-sm text-rtds-text-tertiary">
                    بدون اجرای قبلی
                </p>
            </article>

            <!-- ↓ -->
            <div
                class="text-center text-xl text-rtds-text-tertiary"
                aria-hidden="true"
            >
                ↓
            </div>

            <!-- CURRENT -->
            <article
                class="rounded-2xl border-2 p-6"
                :class="
                    current
                        ? current.state === 'running'
                            ? 'border-rtds-blue bg-rtds-blue/5'
                            : 'border-rtds-yellow bg-rtds-yellow/5'
                        : 'border-rtds-border bg-rtds-bg-card'
                "
            >
                <div class="mb-4 flex items-center justify-between">
                    <span
                        class="text-xs font-medium uppercase tracking-widest text-rtds-text-muted"
                        >Current · اجرای فعلی</span
                    >
                    <span
                        v-if="current"
                        class="rounded-full border px-3 py-1 text-xs"
                        :class="stateClasses[current.state]"
                    >
                        {{ stateLabels[current.state] }}
                    </span>
                </div>

                <template v-if="current">
                    <div
                        class="flex flex-wrap items-center justify-between gap-4"
                    >
                        <div class="flex flex-wrap items-center gap-4">
                            <FormBadge
                                size="md"
                                :round-label="current.bout.round.name"
                                :form-name="
                                    current.performance.form_name ??
                                    `رقابت ${current.bout.sequence}`
                                "
                                :form-number="current.performance.form_number ?? null"
                            />
                            <AthleteInfo
                                v-for="entry in current.bout.entries"
                                :key="entry.id"
                                layout="horizontal"
                                size="md"
                                :name="entry.name"
                                :side="entry.side"
                            />
                        </div>

                        <!-- Timer کوچک؛ همان سه حالت Standby، کنترل از پنل اپراتور -->
                        <Timer
                            size="sm"
                            :duration-seconds="durationSeconds"
                            :started-at="current.performance.started_at"
                            :ended-at="current.performance.ended_at"
                        />
                    </div>
                </template>
                <p v-else class="text-sm text-rtds-text-tertiary">
                    اجرایی در جریان نیست
                </p>
            </article>

            <!-- ↓ -->
            <div
                class="text-center text-xl text-rtds-text-tertiary"
                aria-hidden="true"
            >
                ↓
            </div>

            <!-- NEXT -->
            <article
                class="rounded-2xl border border-rtds-border bg-rtds-bg-card p-6"
            >
                <div class="mb-4">
                    <span
                        class="text-xs font-medium uppercase tracking-widest text-rtds-text-muted"
                        >Next · اجرای بعدی</span
                    >
                </div>

                <template v-if="next">
                    <div class="flex flex-wrap items-center gap-4">
                        <FormBadge
                            size="sm"
                            :round-label="next.round.name"
                            :form-name="
                                formNamesOf(next)[0] ??
                                `رقابت ${next.sequence}`
                            "
                        />
                        <AthleteInfo
                            v-for="entry in next.entries"
                            :key="entry.id"
                            layout="horizontal"
                            size="sm"
                            :name="entry.name"
                            :side="entry.side"
                        />
                    </div>
                </template>
                <p v-else class="text-sm text-rtds-text-tertiary">
                    اجرای بعدی مشخص نشده است
                </p>
            </article>
        </section>
    </ScoreboardLayout>
</template>
