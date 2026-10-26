<?php

namespace App\Actions;

use App\Events\CompetitionDomainUpdated;
use App\Events\CompetitionUpdated;
use App\Models\AuditLog;
use App\Models\Bout;
use App\Models\CompetitionAppeal;
use App\Models\CompetitionRound;
use App\Models\Entry;
use App\Models\Performance;
use App\Models\Result;
use App\Models\ScoreSheet;

class PublishCompetitionUpdate
{
    public function handle(AuditLog $audit): void
    {
        $change = [
            'action' => $audit->action,
            'subject' => ['type' => $audit->subject_type, 'id' => $audit->subject_id],
            'context' => $this->context($audit),
            'occurred_at' => $audit->created_at->toISOString(),
        ];

        CompetitionUpdated::dispatch($audit->tournament_id, $audit->id, $change);
        foreach ($this->domains($audit->action) as $domain) {
            CompetitionDomainUpdated::dispatch($audit->tournament_id, $audit->id, $domain, $change);
        }
    }

    /** @return array<int, string> */
    private function domains(string $action): array
    {
        return match (true) {
            str_starts_with($action, 'appeal.') => ['appeals', 'console', 'judging', 'scoreboard', 'results', 'draw'],
            $action === 'court.created' => ['courts', 'console'],
            $action === 'court.updated' => ['courts', 'console', 'judging', 'scoreboard'],
            $action === 'member.added' => ['members', 'judging', 'console'],
            $action === 'category.saved' => ['athletes', 'draw', 'results', 'console', 'judging', 'scoreboard'],
            $action === 'category.timing_updated' => ['console', 'judging', 'scoreboard'],
            $action === 'entry.updated' => ['athletes', 'draw', 'results', 'console', 'judging', 'scoreboard'],
            str_starts_with($action, 'entry.') => ['athletes', 'draw'],
            $action === 'round.forms_drawn' => ['draw', 'judging', 'console', 'scoreboard'],
            $action === 'round.scheduled' => ['draw', 'courts', 'judging', 'console', 'scoreboard', 'results'],
            $action === 'management.snapshot_imported' => ['athletes', 'draw', 'courts', 'judging', 'console', 'scoreboard', 'results'],
            $action === 'tkdhub.division_imported' => ['athletes', 'draw', 'console', 'judging', 'scoreboard', 'results'],
            $action === 'tkdhub.result_sent' => ['results'],
            str_starts_with($action, 'score.') => ['console', 'judging', 'scoreboard'],
            $action === 'performance.approve' => ['courts', 'console', 'judging', 'scoreboard', 'results'],
            str_starts_with($action, 'performance.') => ['courts', 'console', 'judging', 'scoreboard'],
            str_starts_with($action, 'bout.'), $action === 'round.completed' => ['results', 'draw', 'courts', 'console', 'judging', 'scoreboard'],
            $action === 'result.corrected' => ['results', 'console', 'judging', 'scoreboard'],
            str_starts_with($action, 'tournament.') => CompetitionDomainUpdated::DOMAINS,
            default => [],
        };
    }

    /** @return array<string, int|array<int, int>> */
    private function context(AuditLog $audit): array
    {
        $id = $audit->subject_id;
        $performanceId = match ($audit->subject_type) {
            'performance' => $id,
            'score_sheet' => ScoreSheet::whereKey($id)->value('performance_id'),
            'result' => Result::whereKey($id)->value('performance_id'),
            default => null,
        };
        if ($performanceId !== null) {
            $performance = Performance::findOrFail($performanceId);

            return [...$this->boutContext(Bout::findOrFail($performance->bout_id)), 'performance_id' => $performance->id, 'entry_id' => $performance->entry_id];
        }

        return match ($audit->subject_type) {
            'competition_appeal' => $this->appealContext(CompetitionAppeal::findOrFail($id)),
            'bout' => $this->boutContext(Bout::findOrFail($id)),
            'competition_round' => $this->roundContext(CompetitionRound::findOrFail($id)),
            'entry' => ['category_id' => Entry::findOrFail($id)->category_id, 'entry_id' => $id],
            'category' => ['category_id' => $id, 'court_ids' => Bout::where('category_id', $id)->whereNotNull('court_id')->orderBy('court_id')->distinct()->pluck('court_id')->all()],
            'court' => ['court_ids' => [$id]],
            default => [],
        };
    }

    /** @return array{category_id:int, round_id:int, bout_id:int, court_ids:array<int, int>} */
    private function boutContext(Bout $bout): array
    {
        return ['category_id' => $bout->category_id, 'round_id' => $bout->competition_round_id, 'bout_id' => $bout->id, 'court_ids' => $bout->court_id === null ? [] : [$bout->court_id]];
    }

    /** @return array<string, int|array<int,int>> */
    private function appealContext(CompetitionAppeal $appeal): array
    {
        $performance = $appeal->performance()->firstOrFail();

        return [...$this->boutContext($performance->bout()->firstOrFail()), 'appeal_id' => $appeal->id, 'performance_id' => $performance->id, 'entry_id' => $performance->entry_id];
    }

    /** @return array{category_id:int, round_id:int, court_ids:array<int, int>} */
    private function roundContext(CompetitionRound $round): array
    {
        return ['category_id' => $round->category_id, 'round_id' => $round->id, 'court_ids' => $round->bouts()->whereNotNull('court_id')->orderBy('court_id')->distinct()->pluck('court_id')->all()];
    }
}
