<script setup>
import { Link, usePage } from "@inertiajs/vue3";
defineProps({ tournament: Object });
const page = usePage();
const labels = {
    draft: "پیش‌نویس",
    ready: "آماده",
    running: "در حال برگزاری",
    completed: "پایان‌یافته",
    archived: "آرشیو",
};
const statusClasses = {
    draft: "bg-[#f2f4ed] text-[#81896b]",
    ready: "bg-[#f2f4ed] text-[#81896b]",
    running: "bg-[#e1f4e8] text-[#39795d]",
    completed: "bg-[#dfece1] text-[#416951]",
    archived: "bg-[#f2f4ed] text-[#81896b]",
};
const date = (value) =>
    new Intl.DateTimeFormat("fa-IR", {
        dateStyle: "medium",
        timeZone: "UTC",
    }).format(new Date(value));
</script>
<template>
    <Link
        :href="page.props.urls.tournaments + '/' + tournament.id"
        class="block rounded-xl border border-[#e2e9df] bg-white p-[23px] transition-colors hover:border-[#719f86]"
    >
        <div class="mb-[25px] flex items-center justify-between">
            <span
                class="grid h-[35px] w-[35px] place-items-center rounded-[9px] bg-[#f3f5ec] text-[#8a9967]"
                >▤</span
            ><span
                class="inline-flex rounded-[5px] px-[9px] py-[3px] text-[10px]"
                :class="statusClasses[tournament.status] || statusClasses.draft"
                >{{ labels[tournament.status] }}</span
            >
        </div>
        <h3 class="text-[17px] font-bold">{{ tournament.name }}</h3>
        <p class="mt-2 text-[11px] text-[#9aa49a]">
            {{ tournament.venue || "محل برگزاری تعیین نشده" }}
        </p>
        <div
            class="mt-7 flex justify-between gap-2 border-t border-[#eef1ea] pt-4 text-[10px] text-[#7e897e]"
        >
            <span>{{ date(tournament.starts_on) }}</span
            ><span
                >{{ tournament.categories_count ?? 0 }} رده ·
                {{ tournament.courts_count ?? 0 }} زمین</span
            >
        </div>
    </Link>
</template>
