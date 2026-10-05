<?php

namespace Database\Seeders;

use App\Models\Athlete;
use App\Models\Category;
use App\Models\Court;
use App\Models\Entry;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Demo data is only allowed locally.');
        }
        $admin = User::where('is_admin', true)->where('is_active', true)->first();
        if (! $admin) {
            throw new \RuntimeException('Run php artisan app:create-admin first.');
        }
        DB::transaction(function () use ($admin): void {
            $tournament = Tournament::firstOrCreate(['name' => 'مسابقه آزمایشی پومسه'], ['starts_on' => '2026-10-01', 'venue' => 'سالن تمرین', 'created_by' => $admin->id]);
            $tournament->users()->syncWithoutDetaching([$admin->id => ['role' => 'manager']]);
            Court::firstOrCreate(['tournament_id' => $tournament->id, 'name' => 'زمین یک']);
            $category = Category::firstOrCreate(['tournament_id' => $tournament->id, 'name' => 'رده آزمایشی انفرادی'], [
                'discipline' => 'recognized', 'entry_type' => 'individual', 'gender' => 'male',
                'format' => 'knockout', 'execution_mode' => 'alternating', 'judge_count' => '5',
                'forms_per_round' => 2, 'draw_timing' => 'day_start',
            ]);
            foreach ([['آرمان', 'نمونه'], ['کیان', 'آزمایشی']] as $index => [$first, $last]) {
                $athlete = Athlete::firstOrCreate(['federation_number' => 'DEMO-'.($index + 1)], ['first_name' => $first, 'last_name' => $last]);
                $entry = Entry::firstOrCreate(['category_id' => $category->id, 'display_name' => $first.' '.$last]);
                $entry->athletes()->syncWithoutDetaching([$athlete->id => ['category_id' => $category->id, 'position' => 1]]);
            }
        });
    }
}
