<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['public_id', 'bout_id', 'entry_id', 'poomsae_form_id', 'draw_id', 'form_number', 'status', 'version', 'music_path', 'started_at', 'ended_at', 'approved_by', 'approved_at'])]
class Performance extends Model
{
    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'ended_at' => 'datetime', 'approved_at' => 'datetime', 'version' => 'integer'];
    }

    public function bout(): BelongsTo
    {
        return $this->belongsTo(Bout::class);
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(Entry::class);
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(PoomsaeForm::class, 'poomsae_form_id');
    }

    public function draw(): BelongsTo
    {
        return $this->belongsTo(Draw::class);
    }

    public function scoreSheets(): HasMany
    {
        return $this->hasMany(ScoreSheet::class);
    }

    public function result(): HasOne
    {
        return $this->hasOne(Result::class);
    }
}
