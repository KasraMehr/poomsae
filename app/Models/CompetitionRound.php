<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['category_id', 'name', 'sequence', 'status', 'form_sequence', 'forms_drawn_at', 'form_draw_id', 'schedule_version', 'scheduled_at', 'started_at', 'source_version', 'source_snapshot', 'source_hash'])]
class CompetitionRound extends Model
{
    protected function casts(): array
    {
        return ['sequence' => 'integer', 'form_sequence' => 'array', 'forms_drawn_at' => 'datetime', 'schedule_version' => 'integer', 'scheduled_at' => 'datetime', 'started_at' => 'datetime', 'source_version' => 'integer', 'source_snapshot' => 'array'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function bouts(): HasMany
    {
        return $this->hasMany(Bout::class);
    }
}
