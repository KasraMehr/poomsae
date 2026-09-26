<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['first_name', 'last_name', 'birth_date', 'gender', 'club', 'federation_number'])]
class Athlete extends Model
{
    protected function casts(): array
    {
        return ['birth_date' => 'date'];
    }

    public function entries(): BelongsToMany
    {
        return $this->belongsToMany(Entry::class, 'entry_members')->withPivot('category_id', 'position');
    }
}
