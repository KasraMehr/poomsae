<?php

namespace App\Http\Requests;

use App\Models\Bout;
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
            'winner_entry_id' => [
                Rule::requiredIf(fn () => $this->input('decision_type') !== 'walkover' || ($this->route('bout') instanceof Bout && $this->route('bout')->entries()->count() === 2)),
                'nullable',
                'integer',
            ],
            'reason' => ['required', 'string', 'min:5', 'max:900'],
            'decision_type' => ['sometimes', Rule::in(['tie', 'walkover'])],
            'confirmed' => ['accepted_if:decision_type,walkover'],
        ];
    }
}
