<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['category_id', 'competition_round_id', 'created_by', 'kind', 'algorithm_version', 'random_seed', 'input_snapshot', 'output_snapshot'])]
class Draw extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['input_snapshot' => 'array', 'output_snapshot' => 'array', 'created_at' => 'datetime'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function round(): BelongsTo
    {
        return $this->belongsTo(CompetitionRound::class, 'competition_round_id');
    }
}
