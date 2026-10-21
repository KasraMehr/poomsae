<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\JudgeAssignment;
use App\Models\Tournament;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CompetitionReports
{
    public const ACTION_LABELS = [
        'tournament.created' => 'ساخت مسابقه', 'tournament.updated' => 'ویرایش مسابقه',
        'tournament.completed' => 'پایان مسابقه', 'tournament.archived' => 'آرشیو مسابقه', 'tournament.restored' => 'بازگرداندن از آرشیو',
        'court.created' => 'افزودن زمین', 'court.updated' => 'ویرایش زمین', 'member.added' => 'افزودن عضو برگزاری',
        'category.saved' => 'ذخیرهٔ رده و قواعد', 'entry.created' => 'ثبت ورزشکار', 'entry.updated' => 'ویرایش ورزشکار', 'entry.status' => 'تغییر وضعیت حضور',
        'round.forms_drawn' => 'قرعهٔ فرم‌ها', 'round.scheduled' => 'ساخت جدول مرحله', 'round.completed' => 'پایان مرحله',
        'performance.start' => 'شروع اجرا', 'performance.finish' => 'پایان اجرا', 'performance.approve' => 'تأیید و انتشار نتیجه',
        'score.submitted' => 'ثبت یا اصلاح نمرهٔ داور', 'score.proxy_submitted' => 'ثبت نمرهٔ جایگزین', 'score.proxy_confirmed' => 'تأیید نمرهٔ جایگزین',
        'result.corrected' => 'اصلاح نتیجهٔ منتشرشده', 'bout.walkover' => 'انصراف / عدم حضور', 'bout.tie_resolved' => 'تعیین برندهٔ تساوی',
        'bout.tie_break_resolved' => 'رفع تساوی با نمرات همهٔ داوران', 'management.snapshot_imported' => 'دریافت برنامه از مدیریت',
        'bout.completed' => 'پایان رقابت', 'bout.result_corrected' => 'اصلاح نتیجهٔ رقابت', 'bout.advancement_corrected' => 'اصلاح صعود',
        'category.stages_calculated' => 'محاسبهٔ خودکار تعداد مراحل',
        'appeal.filed' => 'ثبت اعتراض', 'appeal.review_started' => 'شروع بررسی اعتراض',
        'appeal.accepted' => 'پذیرش اعتراض و ثبت رأی', 'appeal.rejected' => 'رد اعتراض با رأی ناظر', 'appeal.applied' => 'اعمال رأی ناظر و تکمیل پرونده',
    ];

    public function __construct(private CompetitionView $view) {}

    /** @return array<string, mixed> */
    public function options(Tournament $tournament): array
    {
        $judgeIds = JudgeAssignment::whereHas('bout.category', fn ($query) => $query->where('tournament_id', $tournament->id))->distinct()->pluck('user_id');
        $actorIds = AuditLog::where('tournament_id', $tournament->id)->distinct()->pluck('user_id');

        return [
            'categories' => $tournament->categories()->orderBy('name')->get(['id', 'name']),
            'judges' => User::whereIn('id', $judgeIds)->orderBy('name')->get(['id', 'name']),
            'actors' => User::whereIn('id', $actorIds)->orderBy('name')->get(['id', 'name']),
            'actions' => AuditLog::where('tournament_id', $tournament->id)->distinct()->orderBy('action')->pluck('action')->map(fn (string $action): array => ['id' => $action, 'name' => self::ACTION_LABELS[$action] ?? $action]),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function judges(Tournament $tournament, User $actor, array $filters, bool $export = false): array
    {
        Gate::forUser($actor)->authorize('operate', $tournament);
        $components = DB::table('score_components')->select('score_sheet_id')
            ->selectRaw("MAX(CASE WHEN criterion = 'accuracy' THEN value_hundredths END) AS accuracy")
            ->selectRaw("MAX(CASE WHEN criterion = 'presentation' THEN value_hundredths END) AS presentation")
            ->selectRaw("MAX(CASE WHEN criterion = 'score' THEN value_hundredths END) AS single_score")
            ->groupBy('score_sheet_id');
        $query = DB::table('score_sheets as sheets')
            ->join('judge_assignments as judges', 'judges.id', '=', 'sheets.judge_assignment_id')
            ->join('users as judge', 'judge.id', '=', 'judges.user_id')
            ->join('users as submitter', 'submitter.id', '=', 'sheets.submitted_by')
            ->join('performances as performances', 'performances.id', '=', 'sheets.performance_id')
            ->join('entries as entries', 'entries.id', '=', 'performances.entry_id')
            ->join('bouts as bouts', 'bouts.id', '=', 'performances.bout_id')
            ->join('categories as categories', 'categories.id', '=', 'bouts.category_id')
            ->join('competition_rounds as rounds', 'rounds.id', '=', 'bouts.competition_round_id')
            ->leftJoin('courts as courts', 'courts.id', '=', 'bouts.court_id')
            ->leftJoin('poomsae_forms as forms', 'forms.id', '=', 'performances.poomsae_form_id')
            ->leftJoin('results as results', fn ($join) => $join->on('results.performance_id', '=', 'performances.id')->whereNotNull('results.published_at'))
            ->leftJoinSub($components, 'components', 'components.score_sheet_id', '=', 'sheets.id')
            ->where('categories.tournament_id', $tournament->id);
        if (! empty($filters['judge_id'])) {
            $query->where('judges.user_id', $filters['judge_id']);
        }
        if (! empty($filters['category_id'])) {
            $query->where('categories.id', $filters['category_id']);
        }
        $this->dates($query, 'sheets.submitted_at', $filters, $tournament->timezone);
        $summary = [
            'total' => (clone $query)->count(),
            'submitted' => (clone $query)->where('sheets.status', 'submitted')->count(),
            'draft' => (clone $query)->where('sheets.status', 'draft')->count(),
            'corrected' => (clone $query)->where('sheets.revision', '>', 1)->count(),
        ];
        $query->select('sheets.*', 'judges.seat', 'judge.name as judge_name', 'submitter.name as submitter_name', 'categories.name as category_name', 'rounds.name as round_name', 'courts.name as court_name', 'entries.display_name as entry_name', 'forms.name as form_name', 'results.score as result_score', 'components.accuracy', 'components.presentation', 'components.single_score')
            ->orderBy('categories.id')->orderBy('rounds.sequence')->orderBy('bouts.sequence')->orderBy('performances.id')->orderBy('judges.seat');
        $records = $export ? $query->get() : $query->paginate(50)->withQueryString();
        $offset = $export ? 0 : ($records->firstItem() ?? 1) - 1;
        $transform = fn ($sheet, int $index): array => [
            $offset + $index + 1, $sheet->judge_name, $sheet->category_name, $sheet->round_name, $sheet->court_name ?? '—',
            $sheet->entry_name, $sheet->form_name ?? 'اجرای ابداعی', $sheet->seat,
            $this->component($sheet->accuracy), $this->component($sheet->presentation),
            $this->component($sheet->single_score ?? (($sheet->accuracy ?? 0) + ($sheet->presentation ?? 0))),
            $sheet->result_score === null ? '' : number_format((float) $sheet->result_score, 6, '.', ''), $sheet->submission_mode === 'operator_proxy' ? 'جایگزین · '.$sheet->submitter_name : 'داور',
            $sheet->status === 'draft' ? 'منتظر تأیید جایگزین' : 'ثبت‌شده', $sheet->revision,
            $sheet->submitted_at ? CarbonImmutable::parse($sheet->submitted_at)->setTimezone($tournament->timezone)->format('Y-m-d H:i:s') : '',
        ];

        return [
            'headers' => ['ردیف', 'داور', 'رده', 'مرحله', 'زمین', 'ورزشکار / تیم', 'فرم / اجرا', 'صندلی', 'دقت', 'اجرا', 'جمع خام داور', 'نتیجهٔ منتشرشده', 'ثبت‌کننده', 'وضعیت نمره', 'نسخه', 'زمان ثبت'],
            'numeric_columns' => [0, 7, 8, 9, 10, 11, 14], 'decimal_columns' => [8, 9, 10, 11],
            'column_widths' => [8, 26, 30, 18, 20, 30, 24, 10, 12, 12, 16, 18, 30, 24, 10, 22],
            'rows' => $export ? $records->map($transform)->all() : $records->getCollection()->map($transform)->all(),
            'pagination' => $export ? null : $this->pagination($records), 'summary' => $summary,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function audit(Tournament $tournament, User $actor, array $filters, bool $export = false): array
    {
        Gate::forUser($actor)->authorize('operate', $tournament);
        $query = AuditLog::with('user:id,name')->where('tournament_id', $tournament->id);
        foreach (['user_id', 'action'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
        $this->dates($query, 'created_at', $filters, $tournament->timezone);
        $query->orderByDesc('id');
        $records = $export ? $query->get() : $query->paginate(50)->withQueryString();
        $collection = $export ? $records : $records->getCollection();
        $revisions = DB::table('score_revisions')->whereIn('score_sheet_id', $collection->where('subject_type', 'score_sheet')->pluck('subject_id'))->get()->groupBy('score_sheet_id');
        $offset = $export ? 0 : ($records->firstItem() ?? 1) - 1;
        $transform = function (AuditLog $log, int $index) use ($offset, $tournament, $revisions): array {
            $before = $log->before;
            $after = $log->after;
            if ($log->subject_type === 'score_sheet' && isset($after['revision'])) {
                $history = $revisions->get($log->subject_id, collect())->keyBy('revision');
                $previous = $history->get($after['revision'] - 1);
                $current = $history->get($after['revision']);
                $before = $previous ? json_decode($previous->snapshot, true, 512, JSON_THROW_ON_ERROR) : $before;
                $after = $current ? [...$after, 'score' => json_decode($current->snapshot, true, 512, JSON_THROW_ON_ERROR)] : $after;
            }
            $types = ['tournament' => 'مسابقه', 'court' => 'زمین', 'category' => 'رده', 'entry' => 'ورزشکار / تیم', 'performance' => 'اجرا', 'score_sheet' => 'نمرهٔ داور', 'result' => 'نتیجه', 'bout' => 'رقابت', 'competition_round' => 'مرحله', 'user' => 'کاربر'];

            return [$offset + $index + 1, $log->created_at->setTimezone($tournament->timezone)->format('Y-m-d H:i:s'), $log->user?->name ?? 'کاربر حذف‌شده', self::ACTION_LABELS[$log->action] ?? $log->action,
                $types[$log->subject_type] ?? $log->subject_type, $log->subject_id, $this->json($before), $this->json($after), $log->after['reason'] ?? '', $log->id];
        };

        return [
            'headers' => ['ردیف', 'زمان', 'کاربر', 'رویداد', 'نوع مورد', 'شناسهٔ مورد', 'اطلاعات قبل', 'اطلاعات بعد', 'دلیل', 'شناسهٔ سابقه'],
            'numeric_columns' => [0, 5, 9], 'decimal_columns' => [],
            'column_widths' => [8, 22, 26, 32, 20, 12, 65, 65, 40, 12], 'row_height' => 72,
            'rows' => $collection->map($transform)->all(), 'pagination' => $export ? null : $this->pagination($records),
            'summary' => ['total' => $export ? $records->count() : $records->total()],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function medals(Tournament $tournament, User $actor, array $filters): array
    {
        Gate::forUser($actor)->authorize('operate', $tournament);
        $snapshot = $this->view->snapshot($tournament, $actor, true);
        $rows = [];
        $pending = [];
        $medalCounts = ['gold' => 0, 'silver' => 0, 'bronze' => 0];
        foreach ($snapshot['categories'] as $category) {
            if (! empty($filters['category_id']) && $category['id'] !== (int) $filters['category_id']) {
                continue;
            }
            if (! $category['completed'] || $category['open_appeal_count'] > 0 || collect($category['rounds'])->contains('advancement_review_required', true)) {
                $pending[] = $category['name'];

                continue;
            }
            $entries = collect($category['entries'])->keyBy('id');
            $awards = [];
            if ($category['format'] === 'knockout') {
                $rounds = collect($category['rounds'])->sortBy('sequence')->values();
                $final = $rounds->last()['bouts'][0];
                if ($final['winner_entry_id'] === null) {
                    $pending[] = $category['name'];

                    continue;
                }
                foreach ($final['entries'] as $entry) {
                    $awards[] = ['id' => $entry['id'], 'rank' => $entry['id'] === $final['winner_entry_id'] ? 1 : 2, 'score' => $final['totals'][$entry['id']] ?? '', 'shared' => false];
                }
                if ($rounds->count() > 1) {
                    $semifinals = $rounds->get($rounds->count() - 2);
                    foreach ($semifinals['bouts'] as $bout) {
                        foreach ($bout['entries'] as $entry) {
                            if ($entry['id'] !== $bout['winner_entry_id'] && ! collect($final['entries'])->contains('id', $entry['id'])) {
                                $awards[] = ['id' => $entry['id'], 'rank' => 3, 'score' => $bout['totals'][$entry['id']] ?? '', 'shared' => true];
                            }
                        }
                    }
                }
            } else {
                foreach ($category['standings'] as $entry) {
                    if ($entry['rank'] !== null && $entry['rank'] <= 3 && $entry['played'] > 0) {
                        $awards[] = ['id' => $entry['id'], 'rank' => $entry['rank'], 'score' => $entry['score'] ?? '', 'shared' => collect($category['standings'])->where('rank', $entry['rank'])->count() > 1];
                    }
                }
            }
            foreach (collect($awards)->sortBy('rank') as $award) {
                $entry = $entries->get($award['id']);
                $medal = [1 => 'gold', 2 => 'silver', 3 => 'bronze'][$award['rank']];
                $medalCounts[$medal]++;
                $rows[] = [count($rows) + 1, $category['name'], $category['format'] === 'knockout' ? 'تک‌حذفی' : 'دوره‌ای', $award['rank'],
                    ['gold' => 'طلا', 'silver' => 'نقره', 'bronze' => 'برنز'][$medal], $entry['name'], $entry['club'] ?? '',
                    collect($entry['members'])->pluck('name')->implode('، '), $award['score'], $award['shared'] ? 'رتبهٔ مشترک' : 'رتبهٔ قطعی'];
            }
        }

        return [
            'headers' => ['ردیف', 'رده', 'نوع برگزاری', 'رتبه', 'مدال', 'ورزشکار / تیم', 'باشگاه', 'اعضای ورودی', 'امتیاز', 'وضعیت رتبه'],
            'numeric_columns' => [0, 3, 8], 'decimal_columns' => [8], 'rows' => $rows, 'pagination' => null,
            'column_widths' => [8, 30, 18, 8, 12, 30, 24, 38, 16, 20],
            'summary' => ['total' => count($rows), ...$medalCounts], 'pending_categories' => $pending,
        ];
    }

    /** @param array<string, mixed> $filters */
    private function dates(Builder|QueryBuilder $query, string $column, array $filters, string $timezone): void
    {
        if (! empty($filters['from'])) {
            $query->where($column, '>=', CarbonImmutable::parse($filters['from'], $timezone)->startOfDay()->utc());
        }
        if (! empty($filters['to'])) {
            $query->where($column, '<', CarbonImmutable::parse($filters['to'], $timezone)->addDay()->startOfDay()->utc());
        }
    }

    private function component(?int $value): string
    {
        return $value === null ? '' : number_format($value / 100, 2, '.', '');
    }

    private function json(?array $value): string
    {
        return $value === null ? '' : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /** @return array{total:int, current_page:int, last_page:int, prev_page_url:?string, next_page_url:?string} */
    private function pagination(LengthAwarePaginator $paginator): array
    {
        return ['total' => $paginator->total(), 'current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'prev_page_url' => $paginator->previousPageUrl(), 'next_page_url' => $paginator->nextPageUrl()];
    }
}
