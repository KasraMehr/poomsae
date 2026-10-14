<?php

namespace App\Actions;

use App\Models\CompetitionAppeal;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class UpdateTournamentArchive
{
    public function __construct(private CompetitionSetup $setup, private CompetitionView $view) {}

    /** @param array{expected_status:string, reason:string} $data */
    public function handle(User $actor, Tournament $tournament, bool $archive, array $data): void
    {
        Gate::forUser($actor)->authorize('update', $tournament);
        DB::transaction(function () use ($actor, $tournament, $archive, $data): void {
            $tournament = Tournament::lockForUpdate()->findOrFail($tournament->id);
            abort_unless($tournament->status === $data['expected_status'], 409, 'وضعیت مسابقه تغییر کرده؛ صفحه را تازه کنید.');
            $this->setup->check($tournament->status === ($archive ? 'completed' : 'archived'), $archive ? 'فقط مسابقهٔ پایان‌یافته قابل آرشیو است.' : 'این مسابقه در آرشیو نیست.');
            $this->setup->check(! $tournament->categories()->whereHas('rounds.bouts.performances', fn ($query) => $query->whereIn('status', ['running', 'scoring']))->exists(), 'اجرای باز وجود دارد؛ ابتدا آن را تعیین تکلیف کنید.');
            if ($archive) {
                $this->setup->check(! CompetitionAppeal::open()->where('tournament_id', $tournament->id)->exists(), 'مسابقهٔ دارای اعتراض باز قابل آرشیو نیست.');
                $categories = collect($this->view->snapshot($tournament, $actor, true)['categories']);
                $this->setup->check($categories->isNotEmpty() && $categories->every(fn (array $category): bool => $category['completed'] && ! collect($category['rounds'])->contains('advancement_review_required', true)), 'ابتدا پایان تمام رده‌ها و جدول صعود را تأیید کنید.');
            }
            $before = ['status' => $tournament->status];
            $tournament->update(['status' => $archive ? 'archived' : 'completed']);
            $this->setup->audit($actor, $tournament, $archive ? 'tournament.archived' : 'tournament.restored', 'tournament', $tournament->id, ['status' => $tournament->status, 'reason' => $data['reason']], $before);
        }, 3);
    }
}
