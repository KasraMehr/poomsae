<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, useAttrs, watch } from "vue";
import EntryAthleteInfo from "./EntryAthleteInfo.vue";

defineOptions({ inheritAttrs: false });

const props = defineProps({
    entries: { type: Array, required: true },
});

const attrs = useAttrs();
const forwardedAttrs = computed(() =>
    Object.fromEntries(
        Object.entries(attrs).filter(([key]) => key !== "class" && key !== "style"),
    ),
);
const pairRoot = ref(null);
const balancedNameRows = ref(false);
let observer;
let animationFrame;
let observedWidth;

const orderedEntries = computed(() =>
    props.entries
        .map((entry, index) => {
            const side = String(entry?.side ?? "").toLowerCase();
            const normalizedSide =
                side === "hong" || side === "red"
                    ? "hong"
                    : side === "chung" || side === "blue"
                      ? "chung"
                      : index === 1
                        ? "hong"
                        : "chung";

            return { ...entry, side: normalizedSide };
        })
        .sort((first, second) =>
            first.side === second.side ? 0 : first.side === "chung" ? -1 : 1,
        ),
);

const hasWrappedNames = () => {
    const nameGroups = pairRoot.value?.querySelectorAll(".bar__names") ?? [];

    return [...nameGroups].some((group) => {
        const names = [...group.querySelectorAll(".bar__name")];
        const rows = new Set(
            names.map((name) => Math.round(name.getBoundingClientRect().top)),
        );

        return rows.size > 1;
    });
};

const measureRows = () => {
    animationFrame = undefined;
    balancedNameRows.value = hasWrappedNames();
};

const scheduleMeasurement = async () => {
    if (animationFrame) {
        cancelAnimationFrame(animationFrame);
        animationFrame = undefined;
    }

    balancedNameRows.value = false;
    await nextTick();
    animationFrame = requestAnimationFrame(measureRows);
};

watch(() => props.entries, scheduleMeasurement, { deep: true, flush: "post" });

onMounted(async () => {
    await nextTick();
    animationFrame = requestAnimationFrame(measureRows);
    observer = new ResizeObserver(([entry]) => {
        const width = entry.contentRect.width;
        if (observedWidth === undefined || Math.abs(width - observedWidth) > 0.5) {
            observedWidth = width;
            scheduleMeasurement();
        }
    });
    if (pairRoot.value) {
        observer.observe(pairRoot.value);
    }
    window.addEventListener("resize", scheduleMeasurement);
});

onBeforeUnmount(() => {
    observer?.disconnect();
    window.removeEventListener("resize", scheduleMeasurement);
    if (animationFrame) {
        cancelAnimationFrame(animationFrame);
    }
});
</script>

<template>
    <div ref="pairRoot" :class="attrs.class" :style="attrs.style">
        <EntryAthleteInfo
            v-for="entry in orderedEntries"
            :key="entry.id"
            v-bind="forwardedAttrs"
            :entry="entry"
            :balanced-name-rows="balancedNameRows"
        />
    </div>
</template>
