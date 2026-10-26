<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DecideCompetitionAppealRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('reviewAppeals', $this->route('tournament'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $performance = $this->route('appeal')?->performance()->with('bout.category.scoringRuleSet')->first();
        $method = $performance?->bout?->category?->scoringRuleSet?->definition['input_method'] ?? null;
        $fields = SubmitProxyScoreRequest::scoreRules($method);
        unset($fields['request_id'], $fields['expected_version'], $fields['reason']);
        $fields['expected_revision'] = ['required', 'integer', 'min:1'];
        $fields['judge_assignment_id'][] = 'distinct';
        $rules = [
            'expected_version' => ['required', 'integer', 'min:1'],
            'decision' => ['required', 'in:accepted,rejected'],
            'decision_reason' => ['required', 'string', 'min:10', 'max:2000'],
            'corrections' => [$this->input('decision') === 'accepted' ? 'required' : 'prohibited', 'array', 'list', 'min:1', 'max:7'],
        ];
        if ($this->input('decision') === 'accepted') {
            foreach ($fields as $name => $fieldRules) {
                $rules['corrections.*.'.$name] = $fieldRules;
            }
        }

        return $rules;
    }
}
