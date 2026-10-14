<?php

namespace App\Actions;

use App\Models\Category;
use App\Models\CompetitionAppeal;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class TkdhubConnection
{
    public function __construct(private ImportTkdhubDivision $import, private TkdhubResults $results, private CompetitionSetup $setup) {}

    /** @return array{category:Category,replayed:bool} */
    public function pull(User $actor, Tournament $tournament, string $eventId, string $divisionId, int $judgeCount = 5): array
    {
        Gate::forUser($actor)->authorize('update', $tournament);
        $sheet = $this->sheet($eventId, $divisionId);
        $division = $sheet['division'];
        $positions = array_flip($sheet['draw']['entryIds']);
        $athletes = [];
        foreach ($sheet['rows'] as $row) {
            $entry = $row['entry'];
            $athletes[] = [
                'entryId' => $entry['id'], 'firstName' => $entry['firstName'], 'lastName' => $entry['lastName'],
                'teamName' => $entry['teamName'] ?? null, 'birthDate' => $entry['birthDate'],
                'gender' => $entry['gender'] ?? null, 'status' => $entry['status'],
                'drawPosition' => isset($positions[$entry['id']]) ? $positions[$entry['id']] + 1 : null,
            ];
        }

        return $this->import->handle($actor, $tournament, [
            'eventId' => $eventId, 'divisionId' => $divisionId, 'divisionName' => $division['name'],
            'birthDateFrom' => $division['birthDateFrom'], 'birthDateTo' => $division['birthDateTo'],
            'allowedFormCodes' => $division['allowedFormCodes'], 'form1Code' => $division['form1Code'],
            'form2Code' => $division['form2Code'], 'gender' => $division['gender'], 'judgeCount' => $judgeCount,
            'athletes' => $athletes,
        ]);
    }

    /** @return array<string,mixed> */
    public function push(User $actor, Tournament $tournament, Category $category, ?string $reason = null): array
    {
        Gate::forUser($actor)->authorize('update', $tournament);
        abort_unless($category->tournament_id === $tournament->id && $category->management_source === 'tkdhub', 404);
        $this->setup->check(! CompetitionAppeal::open()->where('category_id', $category->id)->exists(), 'ارسال نتیجهٔ رده تا تعیین تکلیف اعتراض‌های باز مجاز نیست.');
        $lock = Cache::lock('tkdhub.push.'.$tournament->tkdhub_event_id.'.'.$category->tkdhub_division_id, 1800);
        abort_unless($lock->get(), 409, 'ارسال نتیجهٔ این رده در حال انجام است.');
        try {
            return $this->send($actor, $tournament, $category, $reason);
        } finally {
            $lock->release();
        }
    }

    /** @return array<string,mixed> */
    private function send(User $actor, Tournament $tournament, Category $category, ?string $reason): array
    {
        $output = $this->results->handle($actor, $tournament, $category);
        $sheet = $this->sheet($output['eventId'], $output['divisionId']);
        abort_if($sheet['final'], 409, 'ردهٔ tkdhub نهایی و قفل شده است؛ ابتدا در همان سیستم بازگشایی شود.');
        foreach ([1, 2] as $number) {
            abort_unless($sheet['division']['form'.$number.'Code'] === $category->management_snapshot['form'.$number.'Code'], 409, 'فرم‌های ردهٔ tkdhub تغییر کرده‌اند.');
        }
        $remoteEntries = collect($sheet['rows'])->pluck('entry')->keyBy('id');
        $localIds = array_column($output['results'], 'entryId');
        $remoteIds = $remoteEntries->keys()->all();
        sort($localIds);
        sort($remoteIds);
        abort_unless($localIds === $remoteIds, 409, 'فهرست tkdhub با ردهٔ محلی سازگار نیست.');
        $localDraw = array_column(array_values(array_filter($category->management_snapshot['athletes'], fn (array $entry): bool => $entry['status'] === 'active')), 'entryId');
        abort_unless($sheet['draw']['entryIds'] === $localDraw, 409, 'قرعهٔ فعال tkdhub با ترتیب محلی سازگار نیست.');
        $updates = [];
        $skipped = [];
        foreach ($output['results'] as $row) {
            $remote = $remoteEntries->get($row['entryId']);
            abort_unless($remote['status'] === $row['status'], 409, 'وضعیت حضور ورزشکار در دو سیستم متفاوت است.');
            if ($row['status'] !== 'active') {
                $skipped[] = $row['entryId'];

                continue;
            }
            $body = ['form1' => $remote['scores']['form1'], 'form2' => $remote['scores']['form2'], 'reason' => $reason ?? ''];
            $changed = false;
            foreach (['form1', 'form2'] as $form) {
                if ($row[$form]['accuracy'] === null || $row[$form]['presentation'] === null) {
                    continue;
                }
                foreach (['code', 'accuracy', 'presentation'] as $field) {
                    $value = $row[$form][$field];
                    $old = $body[$form][$field] ?? null;
                    if ($old !== $value) {
                        if ($field !== 'code' && $old !== null) {
                            abort_unless($reason !== null && mb_strlen($reason) >= 5, 422, 'برای اصلاح نمرهٔ قبلی tkdhub، دلیل حداقل پنج‌حرفی لازم است.');
                        }
                        $body[$form][$field] = $value;
                        $changed = true;
                    }
                }
            }
            if ($changed) {
                $updates[$row['entryId']] = $body;
            } else {
                $skipped[] = $row['entryId'];
            }
        }
        $revision = $sheet['revision'];
        $sent = [];
        $batch = array_slice($updates, 0, 50, true);
        foreach ($batch as $entryId => $body) {
            try {
                $response = $this->request()->withHeaders(['If-Match' => '"'.$revision.'"'])
                    ->put($this->path($output['eventId'], $output['divisionId']).'/entries/'.rawurlencode($entryId).'/scores', $body);
            } catch (ConnectionException) {
                $this->transferFailure(502, $entryId, $sent, 'ارتباط با tkdhub قطع شد؛ وضعیت ارسال را با دریافت مجدد بررسی کنید.');
            }
            if (! $response->successful()) {
                $this->transferFailure($response->status() === 409 ? 409 : 502, $entryId, $sent,
                    'tkdhub نتیجه را نپذیرفت. وضعیت و نسخهٔ رده را بررسی کنید.', $response->status());
            }
            $nextRevision = $response->json('revision');
            if (! is_int($nextRevision) || $nextRevision <= $revision) {
                $this->transferFailure(502, $entryId, $sent, 'پاسخ tkdhub شمارهٔ نسخهٔ معتبر ندارد؛ وضعیت این ورزشکار را بررسی کنید.');
            }
            $revision = $nextRevision;
            $sent[] = $entryId;
            $this->setup->audit($actor, $tournament, 'tkdhub.result_sent', 'category', $category->id, [
                'eventId' => $output['eventId'], 'divisionId' => $output['divisionId'], 'entryId' => $entryId,
                'revision' => $revision, 'scores' => $body,
            ]);
        }

        return [
            'eventId' => $output['eventId'], 'divisionId' => $output['divisionId'], 'revision' => $revision,
            'sentEntryIds' => $sent, 'skippedEntryIds' => $skipped,
            'pendingEntryIds' => array_keys(array_slice($updates, 50, null, true)),
        ];
    }

    /** @return array<string,mixed> */
    private function sheet(string $eventId, string $divisionId): array
    {
        try {
            $response = $this->request()->get($this->path($eventId, $divisionId).'/sheet', ['mode' => 'results']);
        } catch (ConnectionException) {
            abort(502, 'ارتباط با tkdhub برقرار نشد.');
        }
        $sheet = $this->read($response);
        $validation = Validator::make($sheet, [
            'division' => ['required', 'array'],
            'division.name' => ['required', 'string'],
            'division.gender' => ['required', 'in:male,female,mixed,open'],
            'division.birthDateFrom' => ['present', 'nullable', 'date_format:Y-m-d'],
            'division.birthDateTo' => ['present', 'nullable', 'date_format:Y-m-d'],
            'division.allowedFormCodes' => ['required', 'array', 'list', 'min:1', 'max:16'],
            'division.allowedFormCodes.*' => ['required', 'integer', 'between:1,16', 'distinct'],
            'division.form1Code' => ['required', 'integer', 'between:1,16'],
            'division.form2Code' => ['required', 'integer', 'between:1,16'],
            'rows' => ['present', 'array', 'list'],
            'rows.*.entry.id' => ['required', 'string', 'distinct:strict'],
            'rows.*.entry.firstName' => ['required', 'string'],
            'rows.*.entry.lastName' => ['required', 'string'],
            'rows.*.entry.birthDate' => ['required', 'date_format:Y-m-d'],
            'rows.*.entry.scores.form1.code' => ['present', 'nullable', 'integer', 'between:1,16'],
            'rows.*.entry.scores.form2.code' => ['present', 'nullable', 'integer', 'between:1,16'],
            'rows.*.entry.scores.form1.accuracy' => ['present', 'nullable', 'string', 'regex:/^\d\.\d{2}$/D', 'numeric', 'between:0,4'],
            'rows.*.entry.scores.form1.presentation' => ['present', 'nullable', 'string', 'regex:/^\d\.\d{2}$/D', 'numeric', 'between:0,6'],
            'rows.*.entry.scores.form2.accuracy' => ['present', 'nullable', 'string', 'regex:/^\d\.\d{2}$/D', 'numeric', 'between:0,4'],
            'rows.*.entry.scores.form2.presentation' => ['present', 'nullable', 'string', 'regex:/^\d\.\d{2}$/D', 'numeric', 'between:0,6'],
            'draw.entryIds' => ['present', 'array', 'list'],
            'draw.entryIds.*' => ['required', 'string', 'distinct:strict'],
        ]);
        abort_if($validation->fails(), 502, 'ساختار اطلاعات رده و ورزشکاران در پاسخ tkdhub معتبر نیست.');
        abort_unless(($sheet['event']['id'] ?? null) === $eventId && ($sheet['divisionId'] ?? null) === $divisionId
            && is_int($sheet['revision'] ?? null) && $sheet['revision'] >= 1
            && is_bool($sheet['final'] ?? null) && is_array($sheet['rows'] ?? null), 502, 'ساختار پاسخ tkdhub معتبر نیست.');
        $rules = $sheet['event']['rules'] ?? [];
        abort_unless(($rules['accuracyMax'] ?? null) === '4.00' && ($rules['presentationMax'] ?? null) === '6.00'
            && ($rules['precision'] ?? null) === 2 && ($rules['formSelection'] ?? null) === 'division', 422,
            'این اتصال فقط ردهٔ استاندارد با دقت ۴، اجرا ۶ و فرم‌های مشترک رده را می‌پذیرد.');
        abort_unless(($sheet['division']['competitionType'] ?? null) === 'individual', 422, 'اتصال tkdhub فقط برای ردهٔ انفرادی است.');
        abort_unless(is_array($sheet['draw']['entryIds'] ?? null) && array_is_list($sheet['draw']['entryIds'])
            && ($sheet['draw']['active'] ?? false) === true, 409, 'قرعهٔ فعال tkdhub هنوز آماده نیست.');
        foreach ($sheet['rows'] as $row) {
            abort_unless(is_array($row['entry'] ?? null) && is_string($row['entry']['id'] ?? null)
                && in_array($row['entry']['status'] ?? null, ['active', 'absent', 'withdrawn'], true)
                && is_array($row['entry']['scores']['form1'] ?? null) && is_array($row['entry']['scores']['form2'] ?? null), 502,
                'اطلاعات ورزشکار در پاسخ tkdhub کامل نیست.');
        }

        return $sheet;
    }

    private function request(): PendingRequest
    {
        $baseUrl = rtrim((string) config('tkdhub.base_url'), '/');
        abort_unless(str_starts_with($baseUrl, 'https://') && filled(config('tkdhub.organization_id')) && filled(config('tkdhub.token')), 503,
            'آدرس HTTPS، شناسهٔ سازمان و توکن tkdhub در تنظیمات سرور لازم است.');

        return Http::baseUrl($baseUrl)->acceptJson()->asJson()->withToken(config('tkdhub.token'))
            ->connectTimeout(5)->timeout(20)->withOptions(['allow_redirects' => false]);
    }

    private function path(string $eventId, string $divisionId): string
    {
        return '/organizations/'.rawurlencode((string) config('tkdhub.organization_id')).'/poomsae-events/'
            .rawurlencode($eventId).'/divisions/'.rawurlencode($divisionId);
    }

    /** @return array<string,mixed> */
    private function read(Response $response): array
    {
        abort_unless($response->successful(), $response->status() === 409 ? 409 : 502, 'tkdhub درخواست را نپذیرفت؛ دسترسی و شناسه‌ها را بررسی کنید.');
        $data = $response->json();
        abort_unless(is_array($data), 502, 'پاسخ tkdhub JSON معتبر نیست.');

        return $data;
    }

    /** @param array<int,string> $sent */
    private function transferFailure(int $status, string $entryId, array $sent, string $message, ?int $upstreamStatus = null): never
    {
        throw new HttpResponseException(response()->json([
            'message' => $message, 'sentEntryIds' => $sent, 'failedEntryId' => $entryId, 'upstreamStatus' => $upstreamStatus,
        ], $status));
    }
}
