<script setup>
import NationalityBadge from "../NationalityBadge.vue";

/**
 * ردیف جدول رنکینگ — ارتفاع و رنگ هر ردیف بسته به رتبه متفاوت است (طبق طراحی).
 */
const props = defineProps({
    rank: { type: [Number, String], required: true },
    name: { type: String, default: "" },
    country: { type: String, default: "" },
    flagUrl: { type: String, default: "" },
    photoUrl: { type: String, default: "" },
    score: { type: [Number, String], default: "" },
    highlight: { type: Boolean, default: false },
});

// ارتفاع‌های متغیر طراحی: رتبه‌های بالاتر ردیف بلندتری دارند
const rowHeight = (rank) => {
    const heights = { 1: "min-h-[130px]", 2: "min-h-[110px]", 3: "min-h-[90px]" };
    return heights[rank] ?? "min-h-[75px]";
};

const rankClasses = (rank) => {
    if (props.highlight || rank === 1)
        return "bg-rtds-gold text-rtds-text-on-yellow";
    if (rank === 2) return "bg-rtds-surface-light text-rtds-bg";
    if (rank === 3) return "bg-rtds-yellow-dark text-rtds-text-on-yellow";
    return "bg-rtds-bg-elevated text-rtds-text-secondary";
};
</script>

<template>
    <div
        class="flex items-center gap-5 rounded-xl border px-6"
        :class="[
            rowHeight(props.rank),
            highlight
                ? 'border-rtds-gold bg-rtds-gold/10'
                : 'border-rtds-border bg-rtds-bg-card',
        ]"
    >
        <!-- مدال/شماره رتبه -->
        <span
            class="grid h-12 w-12 shrink-0 place-items-center rounded-full text-lg font-bold tabular-nums"
            :class="rankClasses(props.rank)"
            >{{ rank }}</span
        >

        <img
            v-if="photoUrl"
            :src="photoUrl"
            :alt="name"
            class="shrink-0 rounded-lg object-cover"
            :class="props.rank === 1 ? 'h-20 w-20' : 'h-14 w-14'"
        />

        <div class="min-w-0 flex-1">
            <p
                class="truncate font-semibold text-rtds-text-light"
                :class="props.rank === 1 ? 'text-2xl' : 'text-lg'"
            >
                {{ name }}
            </p>
            <NationalityBadge
                :country="country"
                :flag-url="flagUrl"
                size="sm"
                class="mt-1"
            />
        </div>

        <b
            class="shrink-0 tabular-nums"
            :class="[
                props.rank === 1 ? 'text-3xl text-rtds-gold-bright' : 'text-xl text-rtds-text-light',
            ]"
            >{{ score }}</b
        >
    </div>
</template>
