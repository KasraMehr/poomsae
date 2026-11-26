<script setup>
import { computed } from "vue";
import AthleteInfo from "./AthleteInfo.vue";

const props = defineProps({
    entry: { type: Object, required: true },
    number: { type: [String, Number], default: "" },
    variant: {
        type: String,
        default: "current",
        validator: (value) => ["current", "compact"].includes(value),
    },
    layout: {
        type: String,
        default: "vertical",
        validator: (value) =>
            ["name-only", "horizontal", "vertical", "vertical-under"].includes(value),
    },
    size: {
        type: String,
        default: "",
        validator: (value) =>
            value === "" || ["sm", "md", "lg", "xl", "xxl", "xxxl"].includes(value),
    },
    nameSize: {
        type: String,
        default: "",
        validator: (value) =>
            value === "" || ["sm", "md", "lg", "xl", "xxl", "xxxl"].includes(value),
    },
    nameFit: { type: Boolean, default: false },
    balancedNameRows: { type: Boolean, default: false },
    nameAreaMinHeight: { type: Boolean, default: false },
    responsiveBadge: { type: Boolean, default: false },
    badgeSize: {
        type: String,
        default: "",
        validator: (value) =>
            value === "" || ["sm", "md", "lg", "xl", "xxl", "xxxl"].includes(value),
    },
    fill: { type: Boolean, default: true },
});

const names = computed(() => {
    const memberNames = (Array.isArray(props.entry.members) ? props.entry.members : [])
        .map((member) => {
            if (typeof member === "string") return member;

            return member?.short_name || member?.name ||
                [member?.first_name, member?.last_name].filter(Boolean).join(" ");
        })
        .filter((name) => typeof name === "string" && name.trim() !== "");

    return memberNames.length
        ? memberNames
        : [props.entry.short_name || props.entry.name].filter(Boolean);
});

const resolvedSize = computed(
    () => props.size || (props.variant === "current" ? "lg" : "md"),
);
const nameSizeStyles = {
    sm: "clamp(14px, min(2.5vw, 4vh), 20px)",
    md: "clamp(16px, min(3.5vw, 6vh), 28px)",
    lg: "clamp(20px, min(5vw, 8vh), 40px)",
    xl: "clamp(30px, min(6vw, 9vh), 52px)",
    xxl: "clamp(36px, min(7vw, 10vh), 64px)",
    xxxl: "clamp(40px, min(8vw, 11vh), 76px)",
};
const resolvedBadgeSize = computed(
    () => props.badgeSize || (props.variant === "current" ? "xl" : "md"),
);
const resolvedSide = computed(() => {
    const side = String(props.entry?.side ?? "").toLowerCase();

    if (side === "hong" || side === "red") return "hong";
    if (side === "chung" || side === "blue") return "chung";

    return side;
});
</script>

<template>
    <AthleteInfo
        class="min-h-0 min-w-0 max-w-full"
        :class="[
            fill ? 'h-full w-full flex-1' : '',
            { 'entry-athlete-info--balanced-rows': balancedNameRows },
        ]"
        :layout="layout"
        :size="resolvedSize"
        :name-size-style="nameSize ? nameSizeStyles[nameSize] : ''"
        :name-fit="nameFit"
        :name-area-min-height="nameAreaMinHeight"
        :responsive-badge="responsiveBadge"
        :badge-size-override="resolvedBadgeSize"
        :fill="fill"
        :number="number || entry.number || ''"
        :names="names"
        :side="resolvedSide"
        :country-code="entry.country_code"
        :country="entry.country"
        :flag-url="entry.flag_url"
    />
</template>

<style scoped>
.entry-athlete-info--balanced-rows :deep(.bar__names) {
    display: grid;
    grid-auto-flow: column;
    grid-auto-columns: minmax(0, 1fr);
    grid-template-rows: repeat(2, minmax(0, 1fr));
    align-content: center;
    align-items: center;
}

.entry-athlete-info--balanced-rows :deep(.bar__name) {
    justify-self: start;
}

.entry-athlete-info--balanced-rows :deep(.bar__names--single .bar__name) {
    grid-row: 1 / -1;
    align-self: center;
}
</style>
