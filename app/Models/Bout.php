<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['competition_round_id', 'category_id', 'court_id', 'sequence', 'status', 'winner_entry_id', 'resolved_by', 'resolution_reason'])]
class Bout extends Model
{
    public function round(): BelongsTo
    {
        return $this->belongsTo(CompetitionRound::class, 'competition_round_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }

    public function performances(): HasMany
    {
        return $this->hasMany(Performance::class);
    }

    public function judges(): HasMany
    {
        return $this->hasMany(JudgeAssignment::class, 'bout_id');
    }

    public function entries(): BelongsToMany
    {
        return $this->belongsToMany(Entry::class, 'bout_entries')->withPivot('side', 'category_id');
    }
}
