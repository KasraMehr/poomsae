<script setup>
import { Link, usePage } from '@inertiajs/vue3';

defineProps({ tournament: { type: Object, required: true } });
const page = usePage();

const labels = {
    draft: 'آماده‌سازی',
    ready: 'آمادهٔ شروع',
    running: 'در حال برگزاری',
    completed: 'پایان‌یافته',
    archived: 'آرشیو',
};

const statusClasses = {
    draft: 'border-slate-200 bg-slate-50 text-slate-600',
    ready: 'border-sky-200 bg-sky-50 text-sky-700',
    running: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    completed: 'border-indigo-200 bg-indigo-50 text-indigo-700',
    archived: 'border-stone-200 bg-stone-100 text-stone-600',
};

function formatDate(value) {
    if (!value) {
        return '—';
    }

    const dateOnly = /^(\d{4}-\d{2}-\d{2})/.exec(String(value))?.[1];
    const date = dateOnly ? new Date(`${dateOnly}T12:00:00Z`) : new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '—';
    }

    return new Intl.DateTimeFormat('fa-IR', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        timeZone: 'UTC',
    }).format(date);
}
</script>

<template>
    <Link
        :href="`${page.props.urls.tournaments}/${tournament.id}`"
        class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-3 rounded-xl border border-[#e2e9df] bg-white px-4 py-3 text-right transition duration-150 hover:-translate-y-px hover:border-[#8eaf98] hover:shadow-[0_5px_16px_#26452c10] min-[761px]:grid-cols-[minmax(0,1.65fr)_minmax(0,1fr)_minmax(0,.8fr)_minmax(0,.8fr)_minmax(2.5rem,.45fr)_minmax(2.5rem,.45fr)_minmax(0,.75fr)] min-[761px]:gap-3 min-[761px]:px-4"
        :aria-label="`${tournament.name}، وضعیت ${labels[tournament.status] || labels.draft}`"
    >
        <strong class="min-w-0 truncate text-sm font-bold text-[#29463b] min-[761px]:text-[13px]">{{ tournament.name }}</strong>
        <span class="hidden min-w-0 truncate text-xs text-[#687a6e] min-[761px]:block">{{ tournament.venue || 'تعیین نشده' }}</span>
        <span class="hidden whitespace-nowrap text-[11px] text-[#687a6e] min-[761px]:block">{{ formatDate(tournament.starts_on) }}</span>
        <span class="hidden whitespace-nowrap text-[11px] text-[#687a6e] min-[761px]:block">{{ formatDate(tournament.ends_on) }}</span>
        <span class="hidden text-center text-xs font-semibold tabular-nums text-[#496354] min-[761px]:block">{{ (tournament.categories_count ?? 0).toLocaleString('fa-IR') }}</span>
        <span class="hidden text-center text-xs font-semibold tabular-nums text-[#496354] min-[761px]:block">{{ (tournament.courts_count ?? 0).toLocaleString('fa-IR') }}</span>
        <span
            class="inline-flex w-fit items-center rounded-full border px-2.5 py-1 text-[10px] font-bold whitespace-nowrap"
            :class="statusClasses[tournament.status] || statusClasses.draft"
        >{{ labels[tournament.status] || labels.draft }}</span>
    </Link>
</template>
