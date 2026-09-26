<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tournament_id', 'scoring_rule_set_id', 'name', 'discipline', 'entry_type', 'gender', 'minimum_age', 'maximum_age', 'format', 'execution_mode', 'judge_count', 'forms_per_round', 'draw_timing', 'minimum_duration_seconds', 'maximum_duration_seconds', 'form_sequence'])]
class Category extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['judge_count' => 'integer', 'forms_per_round' => 'integer', 'form_sequence' => 'array'];
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function scoringRuleSet(): BelongsTo
    {
        return $this->belongsTo(ScoringRuleSet::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(Entry::class);
    }

    public function rounds(): HasMany
    {
        return $this->hasMany(CompetitionRound::class, 'category_id');
    }

    public function forms(): BelongsToMany
    {
        return $this->belongsToMany(PoomsaeForm::class, 'category_poomsae_form');
    }
}
