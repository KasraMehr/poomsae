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
        : [props.entry.name].filter(Boolean);
});

const resolvedSize = computed(
    () => props.size || (props.variant === "current" ? "lg" : "md"),
);
const nameSizeStyles = {
    sm: "clamp(0.875rem, min(2.5cqi, 4cqb), 1.25rem)",
    md: "clamp(1rem, min(3.5cqi, 6cqb), 1.75rem)",
    lg: "clamp(1.25rem, min(5cqi, 8cqb), 2.5rem)",
    xl: "clamp(1.5rem, min(6cqi, 9cqb), 3rem)",
    xxl: "clamp(1.75rem, min(7cqi, 10cqb), 3.75rem)",
    xxxl: "clamp(2rem, min(8cqi, 11cqb), 4.5rem)",
};
const resolvedBadgeSize = computed(
    () => props.badgeSize || (props.variant === "current" ? "xl" : "md"),
);
</script>

<template>
    <AthleteInfo
        class="min-h-0 min-w-0 max-w-full"
        :class="fill ? 'h-full w-full flex-1' : ''"
        :layout="layout"
        :size="resolvedSize"
        :name-size-style="nameSize ? nameSizeStyles[nameSize] : ''"
        :name-fit="nameFit"
        :badge-size-override="resolvedBadgeSize"
        :fill="fill"
        :number="number || entry.number || ''"
        :names="names"
        :side="entry.side"
        :country-code="entry.country_code"
        :country="entry.country"
        :flag-url="entry.flag_url"
    />
</template>
