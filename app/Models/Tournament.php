<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'venue', 'starts_on', 'ends_on', 'timezone', 'status', 'created_by'])]
class Tournament extends Model
{
    use HasFactory;

    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->is_active) {
            $query->whereRaw('1 = 0');
        } elseif (! $user->is_admin) {
            $query->whereHas('users', fn ($members) => $members->where('users.id', $user->id));
        }
    }

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date'];
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function courts(): HasMany
    {
        return $this->hasMany(Court::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tournament_user')->withPivot('role');
    }
}
