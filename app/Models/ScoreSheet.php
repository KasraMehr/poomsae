<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['performance_id', 'judge_assignment_id', 'submitted_by', 'submission_mode', 'revision', 'status', 'submitted_at'])]
class ScoreSheet extends Model
{
    protected function casts(): array
    {
        return ['submitted_at' => 'datetime', 'revision' => 'integer'];
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
