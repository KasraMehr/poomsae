<?php

namespace Database\Seeders;

use App\Actions\CompetitionSetup;
use App\Actions\CreateTournament;
use App\Actions\ScheduleRound;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PilotSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Pilot data is restricted to local/testing.');
        }
        $credentials = [];
        DB::transaction(function () use (&$credentials): void {
            $suffix = strtolower(Str::random(8));
            $password = Str::password(20, symbols: false);
            $admin = new User(['name' => 'مدیر آزمایش', 'email' => 'pilot-'.$suffix.'@example.test', 'password' => $password]);
            $admin->is_admin = true;
            $admin->save();
            $credentials[] = [$admin->email, $password];
            $tournament = app(CreateTournament::class)->handle($admin, ['name' => 'پایلوت استاندارد انفرادی '.$suffix, 'starts_on' => '2026-10-01', 'venue' => 'سالن آزمایش', 'timezone' => 'Asia/Tehran']);
            $setup = app(CompetitionSetup::class);
            $setup->court($admin, $tournament, ['name' => 'زمین یک']);
            $setup->category($admin, $tournament, ['name' => 'انفرادی آزمایشی', 'gender' => 'male', 'minimum_age' => 10, 'maximum_age' => 40, 'format' => 'knockout', 'judge_count' => 5, 'accuracy_max' => 300, 'discard_each_end' => 1, 'rules_acknowledged' => true, 'form_names' => ['فرم آزمایشی اول', 'فرم آزمایشی دوم']]);
            $category = $tournament->categories()->firstOrFail();
            foreach ([['آرمان', 'آزمایشی'], ['کیان', 'نمونه']] as [$first,$last]) {
                $setup->entry($admin, $tournament, $category, ['first_name' => $first, 'last_name' => $last, 'birth_date' => '2005-01-01', 'gender' => 'male', 'club' => 'تیم نمونه']);
            }
            foreach ($category->entries()->get() as $entry) {
                $setup->entryStatus($admin, $tournament, $entry, 'checked_in');
            }
            $judgeIds = [];
            for ($i = 1; $i <= 5; $i++) {
                $password = Str::password(20, symbols: false);
                $email = 'judge'.$i.'-'.$suffix.'@example.test';
                $setup->member($admin, $tournament, ['name' => 'داور '.$i, 'email' => $email, 'password' => $password, 'role' => 'judge']);
                $judgeIds[] = User::where('email', $email)->firstOrFail()->id;
                $credentials[] = [$email, $password];
            }
            app(ScheduleRound::class)->handle($admin, $tournament, $category, ['court_id' => $tournament->courts()->firstOrFail()->id, 'judge_ids' => $judgeIds]);
            $this->command?->info('Pilot tournament ID: '.$tournament->id);
        });
        $this->command?->table(['Local pilot account', 'Generated password'], $credentials);
        $this->command?->warn('Only use these generated accounts for local pilot testing.');
    }
}
