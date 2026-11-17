<script setup>
import { computed } from "vue";
import AthleteInfo from "./AthleteInfo.vue";

const props = defineProps({
    entry: { type: Object, required: true },
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
    badgeSize: {
        type: String,
        default: "",
        validator: (value) =>
            value === "" || ["sm", "md", "lg", "xl", "xxl", "xxxl"].includes(value),
    },
    fill: { type: Boolean, default: true },
});

const names = computed(() => {
    const memberNames = (props.entry.members ?? [])
        .map((member) => member.short_name || member.name)
        .filter(Boolean);

    return memberNames.length ? memberNames : [props.entry.name].filter(Boolean);
});

const resolvedSize = computed(
    () => props.size || (props.variant === "current" ? "lg" : "md"),
);
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
        :badge-size-override="resolvedBadgeSize"
        :fill="fill"
        :names="names"
        :side="entry.side"
        :country-code="entry.country_code"
        :country="entry.country"
        :flag-url="entry.flag_url"
    />
</template>
