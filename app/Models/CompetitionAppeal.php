<?php

namespace App\Models;

use Database\Factories\CompetitionAppealFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['public_id', 'tournament_id', 'category_id', 'performance_id', 'filed_by', 'representative_name', 'reason', 'evidence', 'status', 'version', 'performance_version', 'original_snapshot', 'reviewed_by', 'review_started_at', 'decision_reason', 'corrections', 'decision_snapshot', 'decided_at', 'applied_by', 'applied_snapshot', 'applied_at'])]
class CompetitionAppeal extends Model
{
    /** @use HasFactory<CompetitionAppealFactory> */
    use HasFactory;

    public const OPEN_STATUSES = ['submitted', 'reviewing', 'accepted'];

    protected function casts(): array
    {
        return ['version' => 'integer', 'performance_version' => 'integer', 'original_snapshot' => 'array', 'corrections' => 'array', 'decision_snapshot' => 'array', 'applied_snapshot' => 'array', 'review_started_at' => 'datetime', 'decided_at' => 'datetime', 'applied_at' => 'datetime'];
    }

    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', self::OPEN_STATUSES);
    }

    public function performance(): BelongsTo
    {
        return $this->belongsTo(Performance::class);
    }

    public function filedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'filed_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function appliedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by');
    }
}
