<?php

namespace Database\Seeders;

use App\Actions\CompetitionSetup;
use App\Actions\CreateTournament;
use App\Actions\ScheduleRound;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CompetitionPlaygroundSeeder extends Seeder
{
    private const PASSWORD = 'demoJudge123';

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Competition playground accounts are only available in local/testing.');
        }

        $admin = User::firstOrCreate(
            ['email' => 'playground.admin@example.test'],
            ['name' => 'مدیر آزمایش', 'password' => self::PASSWORD]
        );
        $admin->forceFill(['is_admin' => true, 'is_active' => true])->save();

        $scenarios = [
            ['key' => 'one-v-one', 'name' => 'آزمایشی | انفرادی رودررو', 'discipline' => 'recognized', 'entry_type' => 'individual', 'format' => 'knockout', 'execution_mode' => 'simultaneous', 'judge_count' => 3],
            ['key' => 'individual-knockout', 'name' => 'آزمایشی | انفرادی تک‌حذفی نوبتی', 'discipline' => 'recognized', 'entry_type' => 'individual', 'format' => 'knockout', 'judge_count' => 7],
            ['key' => 'individual', 'name' => 'آزمایشی | انفرادی تک‌نفره', 'discipline' => 'recognized', 'entry_type' => 'individual', 'format' => 'round_robin', 'judge_count' => 3],
            ['key' => 'pair', 'name' => 'آزمایشی | زوجی', 'discipline' => 'recognized', 'entry_type' => 'pair', 'format' => 'knockout', 'judge_count' => 3],
            ['key' => 'pair-simultaneous', 'name' => 'آزمایشی | زوجی رودررو', 'discipline' => 'recognized', 'entry_type' => 'pair', 'format' => 'knockout', 'execution_mode' => 'simultaneous', 'judge_count' => 5],
            ['key' => 'pair-round-robin', 'name' => 'آزمایشی | زوجی تک‌نفره', 'discipline' => 'recognized', 'entry_type' => 'pair', 'format' => 'round_robin', 'judge_count' => 3],
            ['key' => 'team', 'name' => 'آزمایشی | تیمی', 'discipline' => 'recognized', 'entry_type' => 'team', 'format' => 'knockout', 'judge_count' => 3],
            ['key' => 'team-simultaneous', 'name' => 'آزمایشی | تیمی رودررو', 'discipline' => 'recognized', 'entry_type' => 'team', 'format' => 'knockout', 'execution_mode' => 'simultaneous', 'judge_count' => 5],
            ['key' => 'team-round-robin', 'name' => 'آزمایشی | تیمی تک‌نفره', 'discipline' => 'recognized', 'entry_type' => 'team', 'format' => 'round_robin', 'judge_count' => 3],
            ['key' => 'freestyle', 'name' => 'آزمایشی | ابداعی انفرادی', 'discipline' => 'freestyle', 'entry_type' => 'individual', 'format' => 'knockout', 'judge_count' => 5],
            ['key' => 'freestyle-legacy', 'name' => 'آزمایشی | ابداعی انفرادی ۶+۴', 'discipline' => 'freestyle', 'entry_type' => 'individual', 'format' => 'round_robin', 'judge_count' => 3, 'accuracy_max' => 600],
            ['key' => 'freestyle-pair', 'name' => 'آزمایشی | ابداعی زوجی', 'discipline' => 'freestyle', 'entry_type' => 'pair', 'format' => 'knockout', 'judge_count' => 5],
            ['key' => 'freestyle-pair-round-robin', 'name' => 'آزمایشی | ابداعی زوجی تک‌نفره', 'discipline' => 'freestyle', 'entry_type' => 'pair', 'format' => 'round_robin', 'judge_count' => 3],
        ];

        foreach ($scenarios as $scenario) {
            if (Tournament::where('name', $scenario['name'])->exists()) {
                $this->command?->line('Already available: '.$scenario['name']);

                continue;
            }

            DB::transaction(fn () => $this->createScenario($admin, $scenario));
        }

        $this->command?->table(['پنل', 'ایمیل', 'رمز'], [
            ['مدیر', 'playground.admin@example.test', self::PASSWORD],
            ['اپراتور', 'playground.operator@example.test', self::PASSWORD],
            ['نمایشگر', 'playground.display@example.test', self::PASSWORD],
            ...array_map(fn (int $number): array => ['داور '.sprintf('%02d', $number), sprintf('playground.judge%02d@example.test', $number), self::PASSWORD], range(1, 14)),
        ]);
        $this->command?->warn('These simple credentials are for local testing only. Existing playground tournaments are left intact on rerun.');
    }

    /** @param array<string, mixed> $scenario */
    private function createScenario(User $admin, array $scenario): void
    {
        $setup = app(CompetitionSetup::class);
        $tournament = app(CreateTournament::class)->handle($admin, [
            'name' => $scenario['name'], 'starts_on' => now()->toDateString(),
            'venue' => 'سالن آزمایش '.$scenario['key'], 'timezone' => 'Asia/Tehran',
        ]);

        foreach (['زمین یک', 'زمین دو'] as $courtName) {
            $setup->court($admin, $tournament, ['name' => $courtName]);
        }

        foreach (['operator' => 'اپراتور آزمایش', 'display' => 'نمایشگر آزمایش'] as $role => $name) {
            $setup->member($admin, $tournament, [
                'name' => $name, 'email' => 'playground.'.$role.'@example.test',
                'password' => self::PASSWORD, 'role' => $role,
            ]);
        }

        $judgeIds = [];
        for ($number = 1; $number <= 14; $number++) {
            $email = sprintf('playground.judge%02d@example.test', $number);
            $setup->member($admin, $tournament, [
                'name' => 'داور آزمایش '.sprintf('%02d', $number), 'email' => $email,
                'password' => self::PASSWORD, 'role' => 'judge',
            ]);
            $judgeIds[] = User::where('email', $email)->firstOrFail()->id;
        }

        $category = $setup->category($admin, $tournament, [
            'name' => $scenario['name'], 'gender' => 'male',
            'minimum_age' => 10, 'maximum_age' => 50,
            'discipline' => $scenario['discipline'], 'entry_type' => $scenario['entry_type'],
            'format' => $scenario['format'], 'execution_mode' => $scenario['execution_mode'] ?? 'alternating',
            'performance_order' => 'phased', 'judge_count' => $scenario['judge_count'],
            'accuracy_max' => $scenario['accuracy_max'] ?? 400, 'discard_each_end' => $scenario['judge_count'] === 3 ? 0 : 1,
            'rules_acknowledged' => true,
            'form_names' => $scenario['discipline'] === 'recognized' ? ['Taegeuk 8 Jang', 'Koryo'] : [],
        ]);

        foreach (range(1, 4) as $entryNumber) {
            $members = [];
            $memberCount = match ($scenario['entry_type']) {
                'pair' => 2, 'team' => 3, default => 1,
            };
            foreach (range(1, $memberCount) as $memberNumber) {
                $members[] = [
                    'first_name' => 'ورزشکار '.$entryNumber, 'last_name' => $scenario['key'].'-'.$memberNumber,
                    'birth_date' => '2004-05-10', 'gender' => 'male', 'club' => 'باشگاه آزمایش '.$entryNumber,
                ];
            }
            $entry = $setup->entry($admin, $tournament, $category,
                $scenario['entry_type'] === 'individual' ? $members[0] : ['members' => $members]);
            $setup->entryStatus($admin, $tournament, $entry, 'checked_in');
        }

        $courts = $tournament->courts()->orderBy('id')->get();
        $count = $scenario['judge_count'];
        app(ScheduleRound::class)->handle($admin, $tournament, $category, [
            'courts' => [
                ['court_id' => $courts[0]->id, 'judge_ids' => array_slice($judgeIds, 0, $count)],
                ['court_id' => $courts[1]->id, 'judge_ids' => array_slice($judgeIds, 7, $count)],
            ],
        ]);

        $this->command?->info($scenario['name'].' — tournament ID: '.$tournament->id);
    }
}
