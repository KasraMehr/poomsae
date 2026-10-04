export const statusLabels = {
    draft: 'آماده‌سازی', ready: 'آمادهٔ شروع', pending: 'در انتظار اجرا', running: 'در حال اجرا',
    scoring: 'در حال دریافت نمره', approved: 'نتیجهٔ تأییدشده', completed: 'پایان‌یافته',
    cancelled: 'لغوشده', archived: 'آرشیو', decision: 'در انتظار تصمیم سرداور',
};

export const connectionLabels = {
    connected: 'اتصال زنده', polling: 'به‌روزرسانی نزدیک به لحظه‌ای',
    stale: 'ارتباط قطع شده؛ اطلاعات ممکن است قدیمی باشد', denied: 'دسترسی پایان یافته؛ دوباره وارد شوید',
};

export function formatScore(value) {
    return value == null ? '—' : Number(value).toLocaleString('fa-IR', { minimumFractionDigits: 3, maximumFractionDigits: 6 });
}

export function entryTotal(bout, category, entryId) {
    if (bout.totals[entryId] != null) return { score: bout.totals[entryId], temporary: false };
    const forms = bout.performances.filter(performance => performance.entry_id === entryId);
    if (forms.length !== category.forms_per_round || forms.some(performance => performance.result == null && performance.preview_result == null)) return { score: null, temporary: false };
    const sum = forms.reduce((total, performance) => total + Math.round(Number(performance.result ?? performance.preview_result) * 1000000), 0);
    const micros = category.rules?.aggregation === 'mean_two_forms' ? Math.floor((sum + 1) / 2) : sum;
    return { score: (micros / 1000000).toFixed(6), temporary: true };
}

export function elapsed(performance, now) {
    if (!performance.started_at) return '';
    const seconds = Math.max(0, Math.floor(((performance.ended_at ? Date.parse(performance.ended_at) : now) - Date.parse(performance.started_at)) / 1000));
    return `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;
}
