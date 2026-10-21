<?php

namespace Database\Seeders;

use App\Actions\ManageCompetitionAppeals;
use App\Models\CompetitionAppeal;
use App\Models\Performance;
use App\Models\User;
use Illuminate\Database\Seeder;

class CompetitionAppealSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Appeal demo data is only allowed locally.');
        }
        $actor = User::where('is_admin', true)->where('is_active', true)->first();
        $performance = Performance::where('status', 'approved')->whereHas('bout.category.tournament', fn ($query) => $query->where('name', 'مسابقه آزمایشی پومسه'))
            ->whereNotIn('id', CompetitionAppeal::select('performance_id'))->with('bout.category.tournament')->latest('id')->first();
        if ($actor && $performance) {
            app(ManageCompetitionAppeals::class)->file($actor, $performance->bout->category->tournament, [
                'performance_id' => $performance->id, 'expected_version' => $performance->version,
                'representative_name' => 'نمایندهٔ تیم آزمایشی', 'reason' => 'درخواست بررسی اشتباه احتمالی ثبت نمرهٔ آزمایشی',
            ]);
        }
    }
}
