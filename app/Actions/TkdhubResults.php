<?php

namespace App\Actions;

use App\Models\Category;
use App\Models\Performance;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class TkdhubResults
{
    public function __construct(private CalculateScore $calculator) {}

    /**
     * @return array{eventId:string,divisionId:string,results:array<int,array<string,mixed>>}
     */
    public function handle(User $actor, Tournament $tournament, Category $category): array
    {
        Gate::forUser($actor)->authorize('operate', $tournament);
        abort_unless($category->tournament_id === $tournament->id && $category->management_source === 'tkdhub', 404);
        $category = $category->fresh(['entries.performances.result']);
        $snapshot = $category->management_snapshot;
        $entries = $category->entries->keyBy('external_id');
        $results = [];
        foreach ($snapshot['athletes'] as $athlete) {
            $entry = $entries->get($athlete['entryId']);
            abort_unless($entry !== null, 409, 'فهرست ورزشکاران با نسخهٔ دریافتی سازگار نیست.');
            $status = $entry->status === 'checked_in' ? 'active' : ($athlete['status'] === 'absent' ? 'absent' : 'withdrawn');
            $row = ['entryId' => $entry->external_id, 'status' => $status];
            foreach ([1, 2] as $number) {
                $performance = $entry->performances->firstWhere('form_number', $number);
                $row['form'.$number] = [
                    'code' => $snapshot['form'.$number.'Code'],
                    'accuracy' => $this->component($performance, 'accuracy'),
                    'presentation' => $this->component($performance, 'presentation'),
                ];
            }
            $results[] = $row;
        }

        return ['eventId' => $snapshot['eventId'], 'divisionId' => $snapshot['divisionId'], 'results' => $results];
    }

    private function component(?Performance $performance, string $criterion): ?string
    {
        if ($performance?->status !== 'approved' || $performance->result?->published_at === null) {
            return null;
        }
        $snapshot = $performance->result->calculation_snapshot;
        $mean = $snapshot['components'][$criterion]['mean'] ?? null;
        abort_unless(is_string($mean) && preg_match('/^\d+\.\d{6}$/D', $mean)
            && ($snapshot['rules']['accuracy_max'] ?? null) === 400
            && ($snapshot['rules']['presentation_max'] ?? null) === 600, 409, 'نتیجهٔ قطعی معتبر برای خروجی موجود نیست.');
        $hundredths = intdiv($this->calculator->micros($mean) + 5000, 10000);
        abort_if($hundredths > ($criterion === 'accuracy' ? 400 : 600), 409, 'نمره از سقف مجاز بالاتر است.');

        return intdiv($hundredths, 100).'.'.str_pad((string) ($hundredths % 100), 2, '0', STR_PAD_LEFT);
    }
}
