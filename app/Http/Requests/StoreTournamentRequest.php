<?php

namespace App\Http\Requests;

use App\Models\Tournament;
use Illuminate\Foundation\Http\FormRequest;

class StoreTournamentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Tournament::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'venue' => ['nullable', 'string', 'max:255'],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'timezone' => ['required', 'timezone'],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'نام مسابقه', 'starts_on' => 'تاریخ شروع', 'ends_on' => 'تاریخ پایان'];
    }
}
