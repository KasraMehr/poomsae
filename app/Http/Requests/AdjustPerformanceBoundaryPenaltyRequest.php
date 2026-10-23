<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdjustPerformanceBoundaryPenaltyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('operate', $this->route('tournament'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'foul_count' => ['required', 'integer', 'min:0', 'max:20'],
            'penalty_hundredths' => ['nullable', 'integer', 'min:0', 'max:600'],
            'judge_votes' => ['nullable', 'array'],
            'judge_votes.*' => ['integer', 'min:0'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
            'expected_version' => ['required', 'integer', 'min:1'],
        ];
    }
}
