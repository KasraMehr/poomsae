<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['first_name', 'last_name', 'birth_date', 'gender', 'club', 'country_code', 'federation_number'])]
class Athlete extends Model
{
    protected function casts(): array
    {
        return ['birth_date' => 'date'];
    }

    public function getShortNameAttribute(): string
    {
        $first = trim($this->first_name ?? '');
        $last = trim($this->last_name ?? '');
        if ($first !== '' && $last !== '') {
            $firstWords = preg_split('/[\s\.\-]+/u', $first, -1, PREG_SPLIT_NO_EMPTY);
            $initials = '';
            $isLatin = false;
            foreach ($firstWords as $word) {
                $char = mb_substr($word, 0, 1);
                if (preg_match('/^[a-zA-Z]/', $char)) {
                    $isLatin = true;
                    $initials .= strtoupper($char).'.';
                } else {
                    $initials .= $char.'. ';
                }
            }
            $lastUpper = mb_strtoupper($last);

            return $isLatin ? $initials.$lastUpper : trim($initials).' '.$lastUpper;
        }

        return mb_strtoupper(trim($first.' '.$last));
    }

    public function entries(): BelongsToMany
    {
        return $this->belongsToMany(Entry::class, 'entry_members')->withPivot('category_id', 'position');
    }
}
