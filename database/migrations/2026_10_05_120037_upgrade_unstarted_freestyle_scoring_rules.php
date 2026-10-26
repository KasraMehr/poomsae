<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $categoryIds = DB::table('categories')->where('discipline', 'freestyle')->pluck('id');
        foreach ($categoryIds as $categoryId) {
            DB::transaction(function () use ($categoryId): void {
                $tournamentId = DB::table('categories')->where('id', $categoryId)->value('tournament_id');
                $tournament = DB::table('tournaments')->where('id', $tournamentId)->lockForUpdate()->first();
                $category = DB::table('categories')->where('id', $categoryId)->lockForUpdate()->first();
                if (! $category || ! $tournament || ! in_array($tournament->status, ['draft', 'ready', 'running'], true)) {
                    return;
                }
                $rule = DB::table('scoring_rule_sets')->where('id', $category->scoring_rule_set_id)->first();
                $definition = $rule ? json_decode($rule->definition, true, 512, JSON_THROW_ON_ERROR) : [];
                if (($definition['input_method'] ?? null) !== 'single_score_v1') {
                    return;
                }
                $started = DB::table('competition_rounds')->where('category_id', $categoryId)
                    ->where(fn ($query) => $query->whereNotNull('started_at')->orWhere('status', '!=', 'pending'))->exists();
                $performances = DB::table('performances')->join('bouts', 'bouts.id', '=', 'performances.bout_id')->where('bouts.category_id', $categoryId);
                if ($started || (clone $performances)->where(fn ($query) => $query->whereNotNull('performances.started_at')->orWhere('performances.status', '!=', 'pending'))->exists()
                    || (clone $performances)->join('score_sheets', 'score_sheets.performance_id', '=', 'performances.id')->exists()
                    || (clone $performances)->join('results', 'results.performance_id', '=', 'performances.id')->exists()) {
                    return;
                }
                $newDefinition = [...$definition, 'accuracy_max' => 600, 'presentation_max' => 400,
                    'accuracy_component_max' => 200, 'presentation_deduction_options' => [10, 30],
                    'input_method' => 'components_and_deductions_v1'];
                $newRuleId = DB::table('scoring_rule_sets')->insertGetId([
                    'name' => 'تنظیمات ابداعی '.Str::uuid(), 'version' => $rule->version + 1, 'discipline' => 'freestyle',
                    'definition' => json_encode($newDefinition, JSON_THROW_ON_ERROR), 'approved_by' => $rule->approved_by,
                    'approved_at' => $rule->approved_at, 'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('categories')->where('id', $categoryId)->update(['scoring_rule_set_id' => $newRuleId, 'updated_at' => now()]);
                DB::table('performances')->whereIn('bout_id', DB::table('bouts')->where('category_id', $categoryId)->select('id'))->increment('version');
                DB::table('audit_logs')->insert([
                    'tournament_id' => $tournamentId, 'user_id' => $rule->approved_by ?? $tournament->created_by,
                    'action' => 'category.scoring_upgraded', 'subject_type' => 'category', 'subject_id' => $categoryId,
                    'before' => json_encode(['scoring_rule_set_id' => $rule->id], JSON_THROW_ON_ERROR),
                    'after' => json_encode(['scoring_rule_set_id' => $newRuleId, 'input_method' => 'components_and_deductions_v1'], JSON_THROW_ON_ERROR),
                    'created_at' => now(),
                ]);
            });
        }
    }

    /**
     * Retain scoring versions on rollback so recorded results keep their rules.
     */
    public function down(): void {}
};
