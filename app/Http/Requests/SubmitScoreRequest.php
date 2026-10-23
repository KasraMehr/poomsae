<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitScoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasTournamentRole($this->route('tournament'), ['judge', 'head_referee']);
    }

    public function rules(): array
    {
        $definition = $this->route('performance')?->bout?->category?->scoringRuleSet?->definition ?? [];
        $method = $definition['input_method'] ?? null;
        $singleScore = $method === 'single_score_v1';
        $freestyleDetailed = $method === 'components_and_deductions_v1';
        $freestyleComponents = $method === 'freestyle_components_v1';
        $accCount = (int) ($definition['accuracy_component_count'] ?? 6);
        $presCount = (int) ($definition['presentation_component_count'] ?? 4);

        return [
            'request_id' => ['required', 'uuid'], 'expected_version' => ['required', 'integer', 'min:1'],
            'expected_revision' => ['required', 'integer', 'min:0'],
            'score' => [$singleScore ? 'required' : 'prohibited', 'string', 'regex:/^\d{1,2}(?:\.\d{1,2})?$/D'],
            'accuracy' => [$singleScore ? 'prohibited' : 'required', 'string', 'regex:/^\\d{1,2}(?:\\.\\d{1,2})?$/D'],
            'presentation' => [$singleScore ? 'prohibited' : 'required', 'string', 'regex:/^\\d{1,2}(?:\\.\\d{1,2})?$/D'],
            'accuracy_penalties' => [$singleScore || $freestyleDetailed || $freestyleComponents ? 'prohibited' : 'sometimes', 'array', 'max:40'],
            'accuracy_penalties.*' => ['required', 'string', Rule::in(['0.10', '0.30', '0.60'])],
            'restarted' => ['sometimes', 'boolean'],
            'presentation_components' => [$freestyleComponents ? 'required' : ($singleScore || $freestyleDetailed ? 'prohibited' : 'sometimes'), 'array', $freestyleComponents ? "size:{$presCount}" : 'size:3'],
            'presentation_components.*' => ['required', 'string', 'regex:/^\d{1,2}(?:\.\d{1,2})?$/D'],
            'presentation_penalties' => [$freestyleDetailed ? 'present' : 'prohibited', 'array', 'max:40'],
            'presentation_penalties.*' => ['required', 'string', Rule::in(['0.10', '0.30'])],
            'accuracy_components' => [$freestyleComponents ? 'required' : ($freestyleDetailed ? 'required' : 'prohibited'), 'array', $freestyleComponents ? "size:{$accCount}" : 'size:3'],
            'accuracy_components.*' => ['required', 'string', 'regex:/^\d{1,2}(?:\.\d{1,2})?$/D'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
