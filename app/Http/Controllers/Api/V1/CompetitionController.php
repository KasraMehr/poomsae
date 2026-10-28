<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CompetitionDisplay;
use App\Actions\CompetitionSetup;
use App\Actions\CompetitionView;
use App\Actions\ConfirmProxyScore;
use App\Actions\ImportManagementSnapshot;
use App\Actions\RunCompetition;
use App\Actions\SubmitScore;
use App\Events\CompetitionDomainUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\BatchScoreRequest;
use App\Http\Requests\CompetitionSetupRequest;
use App\Http\Requests\ConfirmProxyScoreRequest;
use App\Http\Requests\ManagementSnapshotRequest;
use App\Http\Requests\PerformanceCommandRequest;
use App\Http\Requests\SubmitProxyScoreRequest;
use App\Http\Requests\SubmitScoreRequest;
use App\Models\Category;
use App\Models\Performance;
use App\Models\ScoreSheet;
use App\Models\Tournament;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CompetitionController extends Controller
{
    public function show(Request $request, Tournament $tournament, CompetitionView $view): JsonResponse
    {
        Gate::authorize('view', $tournament);

        return response()->json([
            'data' => $view->snapshot($tournament, $request->user()),
            'realtime' => [
                'protocol' => 'mercure-sse',
                'hub_url' => url(config('broadcasting.connections.mercure.public_url') ?: '/.well-known/mercure'),
                'auth_endpoint' => url('/api/v1/broadcasting/auth'),
                'channel' => 'private-tournaments.'.$tournament->id,
                'presence_channel' => 'presence-tournaments.'.$tournament->id.'.judges',
                'event' => 'competition.updated',
                'events' => array_map(fn (string $domain): string => $domain.'.updated', CompetitionDomainUpdated::DOMAINS),
            ],
        ]);
    }

    public function score(SubmitScoreRequest $request, Tournament $tournament, Performance $performance, SubmitScore $submit): JsonResponse
    {
        $result = $submit->handle($request->user(), $tournament, $performance, $request->validated());

        return response()->json(['data' => $result]);
    }

    public function command(PerformanceCommandRequest $request, Tournament $tournament, Performance $performance, RunCompetition $run): JsonResponse
    {
        $run->command($request->user(), $tournament, $performance, $request->validated());
        $performance->refresh();
        $performance->load('result');

        return response()->json([
            'message' => 'وضعیت اجرا به‌روزرسانی شد.',
            'data' => [
                'performance_id' => $performance->id,
                'status' => $performance->status,
                'version' => $performance->version,
                'approved_at' => $performance->approved_at?->toISOString(),
                'result' => $performance->result ? [
                    'id' => $performance->result->id,
                    'score' => $performance->result->calculation_snapshot['score'] ?? $performance->result->score,
                    'calculation' => $performance->result->calculation_snapshot,
                    'published_at' => $performance->result->published_at?->toISOString(),
                ] : null,
            ],
        ]);
    }

    public function batchScores(BatchScoreRequest $request, Tournament $tournament, Performance $performance, SubmitScore $submit, RunCompetition $run): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();
        $scores = $data['scores'];
        $defaultReason = $data['reason'] ?? 'ثبت دسته‌ای نمرات داوران توسط اپراتور';

        $results = DB::transaction(function () use ($user, $tournament, $performance, $data, $scores, $defaultReason, $submit, $run): array {
            $performance = Performance::lockForUpdate()->findOrFail($performance->id);
            $currentVersion = $performance->version;
            abort_unless($currentVersion === (int) $data['expected_version'], 409, 'وضعیت اجرا تغییر کرده؛ صفحه را تازه کنید.');

            $saved = [];
            foreach ($scores as $item) {
                $itemPayload = [
                    ...$item,
                    'expected_version' => $currentVersion,
                    'reason' => ! empty($item['reason']) ? $item['reason'] : $defaultReason,
                ];
                $sheetResult = $submit->handle($user, $tournament, $performance, $itemPayload, true);

                $sheet = ScoreSheet::findOrFail($sheetResult['score_sheet_id']);
                if ($sheet->status === 'draft') {
                    $sheet->update(['status' => 'submitted', 'confirmed_by' => $user->id, 'confirmed_at' => now()]);
                }
                $saved[] = $sheetResult;
            }

            $approved = false;
            if (! empty($data['approve'])) {
                $performance->refresh();
                $run->command($user, $tournament, $performance, [
                    'command' => 'approve',
                    'expected_version' => $performance->version,
                ]);
                $approved = true;
            }

            $performance->refresh();
            $performance->load('result');

            return [
                'performance_id' => $performance->id,
                'status' => $performance->status,
                'version' => $performance->version,
                'scores' => $saved,
                'approved' => $approved,
                'result' => $performance->result ? [
                    'id' => $performance->result->id,
                    'score' => $performance->result->calculation_snapshot['score'] ?? $performance->result->score,
                    'calculation' => $performance->result->calculation_snapshot,
                    'published_at' => $performance->result->published_at?->toISOString(),
                ] : null,
            ];
        }, 3);

        return response()->json(['data' => $results]);
    }

    public function proxyScore(SubmitProxyScoreRequest $request, Tournament $tournament, Performance $performance, SubmitScore $submit): JsonResponse
    {
        $result = $submit->handle($request->user(), $tournament, $performance, $request->validated(), true);

        return response()->json(['data' => $result]);
    }

    public function confirmProxyScore(ConfirmProxyScoreRequest $request, Tournament $tournament, Performance $performance, ScoreSheet $scoreSheet, ConfirmProxyScore $confirm): JsonResponse
    {
        $confirm->handle($request->user(), $tournament, $performance, $scoreSheet, $request->integer('expected_revision'));

        return response()->json(['message' => 'نمرهٔ دستی تأیید شد.']);
    }

    public function result(Request $request, Tournament $tournament, Performance $performance): JsonResponse
    {
        Gate::authorize('view', $tournament);
        abort_unless($performance->bout()->whereHas('category', fn ($q) => $q->where('tournament_id', $tournament->id))->exists(), 404);

        $performance->load(['result', 'scoreSheets.judgeAssignment.user', 'form']);
        $components = DB::table('score_components')->whereIn('score_sheet_id', $performance->scoreSheets->pluck('id'))->get()->groupBy('score_sheet_id');

        return response()->json([
            'data' => [
                'performance_id' => $performance->id,
                'status' => $performance->status,
                'version' => $performance->version,
                'form' => $performance->form ? ['id' => $performance->form->id, 'name' => $performance->form->name] : null,
                'result' => $performance->result ? [
                    'score' => $performance->result->calculation_snapshot['score'] ?? $performance->result->score,
                    'calculation' => $performance->result->calculation_snapshot,
                    'published_at' => $performance->result->published_at?->toISOString(),
                ] : null,
                'scores' => $performance->scoreSheets->map(fn ($sheet) => [
                    'id' => $sheet->id,
                    'seat' => $sheet->judgeAssignment?->seat,
                    'judge_name' => $sheet->judgeAssignment?->user?->name,
                    'revision' => $sheet->revision,
                    'status' => $sheet->status,
                    'submission_mode' => $sheet->submission_mode,
                    'components' => $components->get($sheet->id, collect())->pluck('value_hundredths', 'criterion')->all(),
                    'breakdown' => $sheet->breakdown,
                ]),
            ],
        ]);
    }

    public function displayData(Request $request, Tournament $tournament, CompetitionDisplay $display): JsonResponse
    {
        Gate::authorize('view', $tournament);
        $revision = $display->revision($tournament);
        if ($request->query('revision') !== null && (string) $revision === $request->query('revision')) {
            return response()->json(['changed' => false, 'revision' => $revision, 'generated_at' => now()->toISOString()])->header('Cache-Control', 'no-store, private');
        }

        return response()->json(['changed' => true, 'data' => $display->snapshot($tournament, $request->user())])->header('Cache-Control', 'no-store, private');
    }

    public function import(ManagementSnapshotRequest $request, Tournament $tournament, Category $category, ImportManagementSnapshot $import): JsonResponse
    {
        return response()->json(['data' => $import->handle($request->user(), $tournament, $category, $request->validated())]);
    }

    public function category(CompetitionSetupRequest $request, Tournament $tournament, CompetitionSetup $setup): JsonResponse
    {
        $category = $setup->category($request->user(), $tournament, $request->validated());

        return response()->json(['data' => ['id' => $category->id, 'name' => $category->name, 'planned_round_count' => $category->planned_round_count,
            'form_pool' => $category->forms()->get(['poomsae_forms.id', 'name'])->map(fn ($form) => ['id' => $form->id, 'name' => $form->name]),
            'stages' => $category->rounds()->orderBy('sequence')->get(['id', 'name', 'sequence']),
        ]], 201);
    }
}
