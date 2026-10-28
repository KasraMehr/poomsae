<?php

namespace Database\Seeders;

use App\Actions\CompetitionSetup;
use App\Actions\CreateTournament;
use App\Actions\RunCompetition;
use App\Actions\ScheduleRound;
use App\Models\Performance;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class MobileTestSeeder extends Seeder
{
    public function run(): void
    {
        config(['broadcasting.default' => 'null']);
        $defaultPassword = 'password123';

        $existing = Tournament::where('name', 'مسابقه آزمایشی تبلت')->latest()->first();
        if ($existing) {
            $performance = Performance::whereHas('bout.category', fn ($q) => $q->where('tournament_id', $existing->id))
                ->whereIn('status', ['scoring', 'running', 'pending'])
                ->orderBy('id')
                ->first();

            $this->command?->info('==================================================');
            $this->command?->info('اطلاعات مسابقه آزمایشی (آماده برای تست):');
            $this->command?->info('Tournament ID: '.$existing->id);
            $this->command?->info('Performance ID: '.($performance?->id ?? '—'));
            $this->command?->info('Performance Status: '.($performance?->status ?? '—'));
            $this->command?->info('رمز عبور کلیه کاربران: '.$defaultPassword);
            $this->command?->info('ایمیل مدیر: admin@poomsae.test');
            $this->command?->info('ایمیل اپراتور: operator@poomsae.test');
            $this->command?->info('ایمیل داوران: judge1@poomsae.test تا judge5@poomsae.test');
            $this->command?->info('ایمیل نمایشگر (RTDS): display@poomsae.test');
            $this->command?->info('==================================================');

            return;
        }

        DB::transaction(function () use ($defaultPassword): void {
            $admin = User::updateOrCreate(
                ['email' => 'admin@poomsae.test'],
                ['name' => 'ادمین تست', 'password' => Hash::make($defaultPassword), 'is_admin' => true, 'is_active' => true]
            );

            $operator = User::updateOrCreate(
                ['email' => 'operator@poomsae.test'],
                ['name' => 'اپراتور تبلت', 'password' => Hash::make($defaultPassword), 'is_admin' => false, 'is_active' => true]
            );

            $display = User::updateOrCreate(
                ['email' => 'display@poomsae.test'],
                ['name' => 'نمایشگر RTDS', 'password' => Hash::make($defaultPassword), 'is_admin' => false, 'is_active' => true]
            );

            $tournament = app(CreateTournament::class)->handle($admin, [
                'name' => 'مسابقه آزمایشی تبلت',
                'starts_on' => now()->toDateString(),
                'venue' => 'سالن مسابقات',
                'timezone' => 'Asia/Tehran',
            ]);

            $tournament->users()->syncWithoutDetaching([
                $admin->id => ['role' => 'manager'],
                $operator->id => ['role' => 'operator'],
                $display->id => ['role' => 'display'],
            ]);

            $setup = app(CompetitionSetup::class);
            $setup->court($admin, $tournament, ['name' => 'زمین ۱']);
            $court = $tournament->courts()->firstOrFail();

            $setup->category($admin, $tournament, [
                'name' => 'انفرادی مردان',
                'gender' => 'male',
                'minimum_age' => 10,
                'maximum_age' => 40,
                'format' => 'knockout',
                'judge_count' => 5,
                'accuracy_max' => 400,
                'discard_each_end' => 1,
                'rules_acknowledged' => true,
                'form_names' => ['تگوک ۱', 'تگوک ۲'],
            ]);

            $category = $tournament->categories()->firstOrFail();

            $athletes = [
                ['first_name' => 'علی', 'last_name' => 'رضایی'],
                ['first_name' => 'محمد', 'last_name' => 'حسینی'],
            ];

            foreach ($athletes as $athlete) {
                $entry = $setup->entry($admin, $tournament, $category, [
                    'first_name' => $athlete['first_name'],
                    'last_name' => $athlete['last_name'],
                    'birth_date' => '2005-01-01',
                    'gender' => 'male',
                    'club' => 'تیم آزمایشی',
                ]);
                $setup->entryStatus($admin, $tournament, $entry, 'checked_in');
            }

            $judgeIds = [];
            for ($i = 1; $i <= 5; $i++) {
                $judge = User::updateOrCreate(
                    ['email' => 'judge'.$i.'@poomsae.test'],
                    ['name' => 'داور '.$i, 'password' => Hash::make($defaultPassword), 'is_admin' => false, 'is_active' => true]
                );
                $tournament->users()->syncWithoutDetaching([$judge->id => ['role' => 'judge']]);
                $judgeIds[] = $judge->id;
            }

            app(ScheduleRound::class)->handle($admin, $tournament, $category, [
                'court_id' => $court->id,
                'judge_ids' => $judgeIds,
            ]);

            $round = $category->rounds()->firstOrFail();
            $bout = $round->bouts()->firstOrFail();
            $performance = $bout->performances()->orderBy('id')->firstOrFail();

            $run = app(RunCompetition::class);
            $run->command($admin, $tournament, $performance, ['command' => 'start', 'expected_version' => $performance->version]);
            $performance->refresh();
            $run->command($admin, $tournament, $performance, ['command' => 'finish', 'expected_version' => $performance->version]);
            $performance->refresh();

            $this->command?->info('==================================================');
            $this->command?->info('اطلاعات مسابقه آزمایشی جهت تست تبلت و اندروید:');
            $this->command?->info('Tournament ID: '.$tournament->id);
            $this->command?->info('Performance ID (آماده نمره‌دهی): '.$performance->id);
            $this->command?->info('Performance Status: '.$performance->status);
            $this->command?->info('رمز عبور کلیه کاربران: '.$defaultPassword);
            $this->command?->info('ایمیل مدیر: admin@poomsae.test');
            $this->command?->info('ایمیل اپراتور: operator@poomsae.test');
            $this->command?->info('ایمیل داوران: judge1@poomsae.test تا judge5@poomsae.test');
            $this->command?->info('ایمیل نمایشگر (RTDS): display@poomsae.test');
            $this->command?->info('==================================================');
        });
    }
}
