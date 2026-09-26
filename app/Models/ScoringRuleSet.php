<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'version', 'discipline', 'definition', 'approved_by', 'approved_at'])]
class ScoringRuleSet extends Model
{
    protected function casts(): array
    {
        return ['definition' => 'array', 'approved_at' => 'datetime'];
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }
}
