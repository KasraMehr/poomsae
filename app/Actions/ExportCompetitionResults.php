<?php

namespace App\Actions;

class ExportCompetitionResults
{
    /**
     * @param  array<string, mixed>  $snapshot
     * @return array{headers: list<string>, rows: list<list<int|string>>}
     */
    public function table(array $snapshot): array
    {
        $headers = [
            'ردیف', 'رده', 'شیوه برگزاری', 'مرحله', 'شماره رقابت', 'زمین', 'ورزشکار / تیم', 'باشگاه',
            'فرم اول', 'نمره فرم اول', 'فرم دوم', 'نمره فرم دوم', 'امتیاز نهایی', 'رتبه', 'وضعیت نتیجه', 'دلیل تعیین نتیجه',
        ];
        $rows = [];
        $courts = collect($snapshot['courts'])->keyBy('id');
        foreach (collect($snapshot['categories'])->sortBy('id') as $category) {
            $entries = collect($category['entries'])->keyBy('id');
            $ranks = collect($category['standings'])->pluck('rank', 'id');
            foreach (collect($category['rounds'])->sortBy('sequence') as $round) {
                foreach (collect($round['bouts'])->sortBy('sequence') as $bout) {
                    foreach (collect($bout['performances'])->groupBy('entry_id') as $entryId => $performances) {
                        $performances = $performances->sortBy('form_number')->values();
                        $hasWithdrawal = $bout['resolution_reason'] !== null && str_starts_with($bout['resolution_reason'], 'انصراف');
                        if ($performances->every(fn (array $performance): bool => $performance['result'] === null) && ! $hasWithdrawal) {
                            continue;
                        }

                        $entry = collect($bout['entries'])->firstWhere('id', $entryId);
                        $firstForm = $performances->get(0);
                        $secondForm = $performances->get(1);
                        $total = $bout['totals'][$entryId] ?? null;
                        $resultStatus = match (true) {
                            collect($bout['performances'])->contains(fn (array $performance): bool => $performance['appeal_pending'] ?? false) => 'تحت بررسی اعتراض',
                            $bout['winner_entry_id'] === (int) $entryId => 'برنده',
                            $bout['winner_entry_id'] !== null => 'بازنده',
                            $hasWithdrawal => 'انصراف / عدم حضور',
                            $category['score_based'] && $total !== null => 'ثبت‌شده',
                            $bout['status'] === 'running' && $total !== null => 'در انتظار تعیین برنده',
                            default => 'در حال تکمیل',
                        };
                        $cells = [
                            count($rows) + 1,
                            $category['name'],
                            $category['format'] === 'knockout' ? 'تک‌حذفی' : 'دوره‌ای',
                            $round['name'],
                            $bout['sequence'],
                            $courts->get($bout['court_id'])['name'] ?? '—',
                            $entry['name'] ?? '—',
                            $entries->get($entryId)['club'] ?? '',
                            $firstForm['form_name'] ?? '',
                            $firstForm['result'] ?? '',
                            $secondForm['form_name'] ?? '',
                            $secondForm['result'] ?? '',
                            $total ?? '',
                            $round['id'] === $category['standings_round_id'] ? ($ranks->get($entryId) ?? '') : '',
                            $resultStatus,
                            $bout['resolution_reason'] ?? '',
                        ];
                        $rows[] = array_map(fn (int|string $value): int|string => is_string($value) ? trim(preg_replace('/[\r\n\t]+/u', ' ', $value)) : $value, $cells);
                    }
                }
            }
        }

        return compact('headers', 'rows');
    }
}
