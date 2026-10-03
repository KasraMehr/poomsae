<?php

namespace App\Console\Commands;

use App\Actions\DrawRoundForms;
use App\Models\Category;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

#[Signature('poomsae:draw-forms')]
#[Description('Draw due Poomsae forms for locally managed stages')]
class DrawDueForms extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(DrawRoundForms $draw): int
    {
        $failed = false;
        Category::with('tournament')->where('discipline', 'recognized')->whereNotNull('planned_round_count')
            ->whereNull('management_source')->whereIn('form_draw_timing', ['day_before', 'morning'])
            ->whereHas('tournament', fn ($query) => $query->whereIn('status', ['draft', 'ready', 'running']))
            ->whereHas('rounds', fn ($query) => $query->whereNull('forms_drawn_at'))
            ->each(function (Category $category) use ($draw, &$failed): void {
                $tournament = $category->tournament;
                if (now()->lessThan($draw->availableAt($tournament, $category))) {
                    return;
                }
                $actor = User::find($tournament->created_by);
                if (! $actor || ! Gate::forUser($actor)->allows('update', $tournament)) {
                    return;
                }
                try {
                    $draw->handle($actor, $tournament, $category);
                    $this->info('Forms drawn for category '.$category->id);
                } catch (ValidationException $exception) {
                    $this->error($exception->getMessage());
                    $failed = true;
                }
            });

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
