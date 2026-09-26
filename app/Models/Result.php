<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['performance_id', 'scoring_rule_set_id', 'score', 'calculation_snapshot', 'published_at'])]
class Result extends Model
{
    protected function casts(): array
    {
        return ['score' => 'decimal:6', 'calculation_snapshot' => 'array', 'published_at' => 'datetime'];
    }

    public function performance(): BelongsTo
    {
        return $this->belongsTo(Performance::class);
    }

    public function scoringRuleSet(): BelongsTo
    {
        return $this->belongsTo(ScoringRuleSet::class);
    }
}
