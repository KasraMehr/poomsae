<?php

namespace App\Actions;

use App\Models\Entry;
use App\Models\JudgeAssignment;
use App\Models\Performance;
use App\Models\ScoreSheet;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class JudgeCurrentState
{
    /**
     * Build the active thin-client snapshot for the given judge.
     *
     * @return array<string, mixed>
     */
    public function snapshot(User $judge): array
    {
        $tournament = $judge->tournaments()
            ->where('tournaments.status', 'running')
            ->latest('tournaments.id')
            ->first()
            ?? $judge->tournaments()
                ->where('tournaments.status', '!=', 'archived')
                ->latest('tournaments.id')
                ->first();

        abort_unless($tournament instanceof Tournament, 404, 'هیچ مسابقهٔ فعالی برای این داور یافت نشد.');

        $assignment = JudgeAssignment::where('user_id', $judge->id)
            ->whereHas('bout.category', fn ($q) => $q->where('tournament_id', $tournament->id))
            ->latest('id')
            ->first();

        $court = $assignment?->bout?->court ?? $tournament->courts()->first();
        abort_unless($court, 404, 'هیچ زمینی برای این داور یا مسابقه تعیین نشده است.');

        $seatNumber = $assignment?->seat ?? 1;

        $activePerformance = Performance::whereHas('bout', fn ($q) => $q->where('court_id', $court->id))
            ->whereIn('status', ['running', 'scoring'])
            ->orderBy('id')
            ->first()
            ?? Performance::whereHas('bout', fn ($q) => $q->where('court_id', $court->id)->whereIn('status', ['pending', 'running', 'scoring']))
                ->where('status', 'pending')
                ->orderBy('id')
                ->first();

        $activeData = null;
        if ($activePerformance) {
            $boutAssignment = JudgeAssignment::where('bout_id', $activePerformance->bout_id)
                ->where('user_id', $judge->id)
                ->first();

            if ($boutAssignment) {
                $seatNumber = $boutAssignment->seat;
            }

            $activeData = $this->formatPerformance($activePerformance, $boutAssignment);
        }

        $channel = 'private-tournaments.'.$tournament->id;
        $hubBase = url(config('broadcasting.connections.mercure.public_url') ?: '/.well-known/mercure');

        return [
            'judge' => [
                'id' => $judge->id,
                'name' => $judge->name,
                'email' => $judge->email,
                'seat_number' => $seatNumber,
            ],
            'tournament' => [
                'id' => $tournament->id,
                'name' => $tournament->name,
                'court_id' => $court->id,
                'court_name' => $court->name,
            ],
            'realtime' => [
                'protocol' => 'mercure-sse',
                'hub_url' => $hubBase.'?topic='.urlencode($channel),
                'channel' => $channel,
                'mercure_token' => $this->generateMercureToken($judge->id, $channel),
            ],
            'active_performance' => $activeData,
        ];
    }

    /**
     * Format an active performance object.
     *
     * @return array<string, mixed>
     */
    public function formatPerformance(Performance $performance, ?JudgeAssignment $assignment = null): array
    {
        $bout = $performance->bout;
        $category = $bout->category;
        $ruleDef = $category->scoringRuleSet?->definition ?? [];

        $entry = $performance->entry;
        $athletesList = $this->formatAthletes($entry);
        $side = $entry ? $bout->entries()->where('entries.id', $entry->id)->first()?->pivot?->side : null;

        $sheet = $assignment
            ? ScoreSheet::where('performance_id', $performance->id)
                ->where('judge_assignment_id', $assignment->id)
                ->first()
            : null;

        $components = $sheet
            ? DB::table('score_components')->where('score_sheet_id', $sheet->id)->pluck('value_hundredths', 'criterion')
            : collect();

        $accuracy = isset($components['accuracy'])
            ? number_format($components['accuracy'] / 100, 2, '.', '')
            : (isset($components['score']) ? number_format($components['score'] / 100, 2, '.', '') : null);

        $presentation = isset($components['presentation'])
            ? number_format($components['presentation'] / 100, 2, '.', '')
            : null;

        $simultaneous = $category->format === 'knockout' && $category->execution_mode === 'simultaneous';
        $pairedPerformance = null;
        if ($simultaneous) {
            $paired = Performance::where('bout_id', $bout->id)
                ->where('id', '!=', $performance->id)
                ->where('form_number', $performance->form_number)
                ->first();

            if ($paired) {
                $pairedEntry = $paired->entry;
                $pairedAthletes = $this->formatAthletes($pairedEntry);
                $pairedSheet = $assignment
                    ? ScoreSheet::where('performance_id', $paired->id)
                        ->where('judge_assignment_id', $assignment->id)
                        ->first()
                    : null;

                $pairedComponents = $pairedSheet
                    ? DB::table('score_components')->where('score_sheet_id', $pairedSheet->id)->pluck('value_hundredths', 'criterion')
                    : collect();

                $pairedAccuracy = isset($pairedComponents['accuracy'])
                    ? number_format($pairedComponents['accuracy'] / 100, 2, '.', '')
                    : (isset($pairedComponents['score']) ? number_format($pairedComponents['score'] / 100, 2, '.', '') : null);

                $pairedPresentation = isset($pairedComponents['presentation'])
                    ? number_format($pairedComponents['presentation'] / 100, 2, '.', '')
                    : null;

                $pairedSide = $pairedEntry ? $bout->entries()->where('entries.id', $pairedEntry->id)->first()?->pivot?->side : null;

                $pairedPerformance = [
                    'id' => $paired->id,
                    'entry_id' => $paired->entry_id,
                    'status' => $paired->status,
                    'side' => $pairedSide,
                    'entry_display_name' => $pairedEntry?->display_name ?? '—',
                    'athlete' => $pairedAthletes[0] ?? null,
                    'athletes' => $pairedAthletes,
                    'my_submission' => [
                        'is_submitted' => $pairedSheet?->status === 'submitted',
                        'accuracy' => $pairedAccuracy,
                        'presentation' => $pairedPresentation,
                    ],
                ];
            }
        }

        return [
            'id' => $performance->id,
            'tournament_id' => $category->tournament_id,
            'status' => $performance->status,
            'started_at' => $performance->started_at?->toISOString(),
            'category_name' => $category->name,
            'entry_type' => $category->entry_type ?? 'individual',
            'entry_display_name' => $entry?->display_name ?? ($athletesList[0]['name'] ?? '—'),
            'side' => $side,
            'round_name' => $bout->round?->name ?? 'دور مسابقه',
            'poomsae_name' => $performance->form?->name ?? ('فرم '.$performance->form_number),
            'athlete' => $athletesList[0] ?? null,
            'athletes' => $athletesList,
            'paired_performance' => $pairedPerformance,
            'rules' => [
                'judge_count' => (int) ($category->judge_count ?? 5),
                'input_method' => $ruleDef['input_method'] ?? 'standard_wt',
                'accuracy_max' => number_format(($ruleDef['accuracy_max'] ?? 400) / 100, 2, '.', ''),
                'presentation_max' => number_format(($ruleDef['presentation_max'] ?? 600) / 100, 2, '.', ''),
            ],
            'my_submission' => [
                'is_submitted' => $sheet?->status === 'submitted',
                'accuracy' => $accuracy,
                'presentation' => $presentation,
            ],
        ];
    }

    /**
     * Format the list of athletes for an entry.
     *
     * @return array<int, array<string, mixed>>
     */
    public function formatAthletes(?Entry $entry): array
    {
        if (! $entry) {
            return [];
        }

        $athletes = $entry->athletes()->orderBy('entry_members.position')->get();

        if ($athletes->isEmpty()) {
            return [
                [
                    'id' => $entry->id,
                    'name' => $entry->display_name ?? '—',
                    'first_name' => '',
                    'last_name' => '',
                    'team_or_club' => $entry->display_name ?? '—',
                    'position' => 1,
                ],
            ];
        }

        return $athletes->map(function ($ath) use ($entry): array {
            return [
                'id' => $ath->id,
                'name' => trim($ath->first_name.' '.$ath->last_name),
                'first_name' => $ath->first_name,
                'last_name' => $ath->last_name,
                'team_or_club' => $ath->club ?: ($ath->country_code ?: ($entry->display_name ?: '—')),
                'position' => (int) ($ath->pivot->position ?? 1),
            ];
        })->values()->all();
    }

    /**
     * Generate a subscriber Mercure HS256 JWT.
     */
    public function generateMercureToken(int|string $userId, string $channel): string
    {
        $secret = config('broadcasting.connections.mercure.secret') ?: 'poomsae-secret-key';
        $header = rtrim(strtr(base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'])), '+/', '-_'), '=');
        $payload = rtrim(strtr(base64_encode(json_encode([
            'mercure' => [
                'subscribe' => [$channel, '*'],
            ],
            'sub' => (string) $userId,
            'exp' => now()->addHours(12)->timestamp,
        ])), '+/', '-_'), '=');

        $signature = rtrim(strtr(base64_encode(hash_hmac('sha256', "{$header}.{$payload}", $secret, true)), '+/', '-_'), '=');

        return "{$header}.{$payload}.{$signature}";
    }
}
