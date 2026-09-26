<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResolveBoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('operate', $this->route('tournament'));
    }

    public function rules(): array
    {
        return [
            'winner_entry_id' => ['required', 'integer'],
            'reason' => ['required', 'string', 'min:5', 'max:900'],
            'decision_type' => ['sometimes', Rule::in(['tie', 'walkover'])],
            'confirmed' => ['accepted_if:decision_type,walkover'],
        ];
    }
}
