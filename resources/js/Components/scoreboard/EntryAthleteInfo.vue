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
});

const names = computed(() => {
    const memberNames = (props.entry.members ?? [])
        .map((member) => member.short_name || member.name)
        .filter(Boolean);

    return memberNames.length ? memberNames : [props.entry.name].filter(Boolean);
});

const size = computed(() => props.variant === "current" ? "lg" : "md");
const layout = "vertical";
</script>

<template>
    <AthleteInfo
        class="h-full min-h-0 w-full min-w-0 max-w-full flex-1"
        :layout="layout"
        :size="size"
        :badge-size-override="variant === 'current' ? 'xl' : 'md'"
        fill
        :names="names"
        :side="entry.side"
        :country-code="entry.country_code"
        :country="entry.country"
        :flag-url="entry.flag_url"
    />
</template>
