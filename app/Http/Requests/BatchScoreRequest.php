<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class BatchScoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('operate', $this->route('tournament'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $definition = $this->route('performance')?->bout?->category?->scoringRuleSet?->definition ?? [];
        $method = $definition['input_method'] ?? null;
        $scoreRules = SubmitProxyScoreRequest::scoreRules($method, $definition);

        $rules = [
            'expected_version' => ['required', 'integer', 'min:1'],
            'approve' => ['sometimes', 'boolean'],
            'reason' => ['sometimes', 'string', 'min:5', 'max:500'],
            'scores' => ['required', 'array', 'min:1'],
        ];

        foreach ($scoreRules as $field => $fieldRules) {
            if ($field === 'expected_version') {
                continue;
            }
            if ($field === 'reason') {
                $rules['scores.*.'.$field] = ['nullable', 'string', 'min:5', 'max:500'];

                continue;
            }
            $rules['scores.*.'.$field] = $fieldRules;
        }

        return $rules;
    }
}
