<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['performance_id', 'judge_assignment_id', 'submitted_by', 'submission_mode', 'revision', 'breakdown', 'status', 'submitted_at', 'confirmed_by', 'confirmed_at'])]
class ScoreSheet extends Model
{
    protected function casts(): array
    {
        return ['submitted_at' => 'datetime', 'confirmed_at' => 'datetime', 'revision' => 'integer', 'breakdown' => 'array'];
    }

    public function performance(): BelongsTo
    {
        return $this->belongsTo(Performance::class);
    }

    public function judgeAssignment(): BelongsTo
    {
        return $this->belongsTo(JudgeAssignment::class);
    }
}
