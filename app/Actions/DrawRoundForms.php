<?php

namespace App\Actions;

use App\Models\Category;
use App\Models\CompetitionRound;
use App\Models\Draw;
use App\Models\Tournament;
use App\Models\User;
use Carbon\CarbonImmutable;

class DrawRoundForms
{
    public function __construct(private CompetitionSetup $setup) {}

    public function availableAt(Tournament $tournament, Category $category): CarbonImmutable
    {
        $date = CarbonImmutable::parse($tournament->starts_on->format('Y-m-d'), $tournament->timezone);

        return $category->form_draw_timing === 'before_stage' ? $date : ($category->form_draw_timing === 'day_before' ? $date->subDay() : $date)->setTimeFromTimeString($category->form_draw_time);
    }

    public function handle(User $actor, Tournament $tournament, Category $category, ?int $roundId = null): void
    {
        $this->setup->locked($actor, $tournament, function (Tournament $tournament) use ($actor, $category, $roundId): void {
            $category = Category::findOrFail($category->id);
            abort_unless($category->tournament_id === $tournament->id, 404);
            $this->setup->check($category->management_source === null, 'قرعهٔ این رده از سیستم مدیریت مسابقات دریافت می‌شود.');
            $this->setup->check($category->discipline === 'recognized' && $category->planned_round_count !== null, 'قرعهٔ مرحله‌ای فقط برای ردهٔ استاندارد با مراحل مشخص است.');
            $this->setup->check(now()->greaterThanOrEqualTo($this->availableAt($tournament, $category)), 'زمان قرعهٔ پومسه‌های این رده هنوز نرسیده است.');
            $rounds = $category->rounds()->whereNull('forms_drawn_at')->orderBy('sequence');
            if ($category->form_draw_timing === 'before_stage') {
                $round = $roundId ? $category->rounds()->findOrFail($roundId) : $rounds->first();
                $rounds = collect($round ? [$round] : []);
            } else {
                $rounds = $rounds->get();
            }
            $this->setup->check($rounds->isNotEmpty(), 'فرم‌های این مراحل قبلاً تعیین شده‌اند.');
            foreach ($rounds as $round) {
                $this->setup->check($round->forms_drawn_at === null, 'فرم‌های این مرحله قبلاً تعیین شده‌اند.');
                $used = $category->allow_form_repetition ? [] : $category->rounds()->where('id', '!=', $round->id)->get()->flatMap(fn ($item) => $item->form_sequence ?? [])->all();
                $pool = $category->forms()->pluck('poomsae_forms.id')->map(fn ($id) => (int) $id)->all();
                $available = array_values(array_diff($pool, $used));
                $this->setup->check(count($pool) === 8 && count($available) >= 2, 'پومسهٔ کافی برای قرعهٔ غیرتکراری باقی نمانده است.');
                $seed = bin2hex(random_bytes(16));
                usort($available, fn ($left, $right) => strcmp(hash('sha256', $seed.':'.$left), hash('sha256', $seed.':'.$right)));
                $this->assign($actor, $tournament, $category, $round, array_slice($available, 0, 2), 'sha256_form_pool_v1', $seed);
            }
        });
    }

    /** @param array<int, int> $formIds */
    public function assign(User $actor, Tournament $tournament, Category $category, CompetitionRound $round, array $formIds, string $algorithm, string $seed): void
    {
        $this->setup->check($round->started_at === null && ! $round->bouts()->whereHas('performances', fn ($query) => $query->where('status', '!=', 'pending'))->exists(), 'بعد از شروع مرحله، تغییر قرعهٔ فرم‌ها مجاز نیست.');
        $this->setup->check(count($formIds) === 2 && count(array_unique($formIds)) === 2 && $category->forms()->whereIn('poomsae_forms.id', $formIds)->count() === 2, 'دو پومسهٔ متفاوت از فهرست مجاز رده انتخاب شود.');
        if ($category->form_draw_timing === 'before_stage' && $round->sequence > 1) {
            $this->setup->check($category->rounds()->where('sequence', $round->sequence - 1)->where('status', 'completed')->exists(), 'قرعهٔ این مرحله پس از پایان مرحلهٔ قبل مجاز است.');
        }
        if (! $category->allow_form_repetition) {
            $used = $category->rounds()->where('id', '!=', $round->id)->get()->flatMap(fn ($item) => $item->form_sequence ?? [])->all();
            $this->setup->check(array_intersect($used, $formIds) === [], 'در حالت غیرتکراری، فرم استفاده‌شده در مرحلهٔ دیگر مجاز نیست.');
        }
        $draw = Draw::create([
            'category_id' => $category->id, 'competition_round_id' => $round->id, 'created_by' => $actor->id,
            'kind' => 'forms', 'algorithm_version' => $algorithm, 'random_seed' => $seed,
            'input_snapshot' => ['pool' => $category->forms()->pluck('poomsae_forms.id')->all(), 'allow_repetition' => $category->allow_form_repetition],
            'output_snapshot' => ['form_ids' => $formIds],
        ]);
        $round->update(['form_sequence' => $formIds, 'forms_drawn_at' => now(), 'form_draw_id' => $draw->id]);
        foreach ($round->bouts()->with('performances')->get()->flatMap(fn ($bout) => $bout->performances) as $performance) {
            $performance->update(['poomsae_form_id' => $formIds[$performance->form_number - 1], 'draw_id' => $draw->id, 'version' => $performance->version + 1]);
        }
        $this->setup->audit($actor, $tournament, 'round.forms_drawn', 'competition_round', $round->id, ['draw_id' => $draw->id, 'form_ids' => $formIds]);
    }
}
