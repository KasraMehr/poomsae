<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitProxyScoreRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $method = $this->route('performance')?->bout?->category?->scoringRuleSet?->definition['input_method'] ?? null;
        $singleScore = $method === 'single_score_v1';
        $freestyleDetailed = $method === 'components_and_deductions_v1';

        return [
            'request_id' => ['required', 'uuid'],
            'judge_assignment_id' => ['required', 'integer', 'min:1'],
            'expected_version' => ['required', 'integer', 'min:1'],
            'expected_revision' => ['required', 'integer', 'in:0'],
            'score' => [$singleScore ? 'required' : 'prohibited', 'string', 'regex:/^\d{1,2}(?:\.\d{1,2})?$/D'],
            'accuracy' => [$singleScore ? 'prohibited' : 'required', 'string', 'regex:/^\d{1,2}(?:\.\d{1,2})?$/D'],
            'presentation' => [$singleScore ? 'prohibited' : 'required', 'string', 'regex:/^\d{1,2}(?:\.\d{1,2})?$/D'],
            'accuracy_penalties' => [$singleScore || $freestyleDetailed ? 'prohibited' : 'sometimes', 'array', 'max:40'],
            'accuracy_penalties.*' => ['required', 'string', Rule::in(['0.10', '0.30'])],
            'presentation_components' => [$singleScore || $freestyleDetailed ? 'prohibited' : 'sometimes', 'array', 'size:3'],
            'presentation_components.*' => ['required', 'string', 'regex:/^\d{1,2}(?:\.\d{1,2})?$/D'],
            'presentation_penalties' => [$freestyleDetailed ? 'present' : 'prohibited', 'array', 'max:40'],
            'presentation_penalties.*' => ['required', 'string', Rule::in(['0.10', '0.30'])],
            'accuracy_components' => [$freestyleDetailed ? 'required' : 'prohibited', 'array', 'size:3'],
            'accuracy_components.*' => ['required', 'string', 'regex:/^\d{1,2}(?:\.\d{1,2})?$/D'],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }
}
