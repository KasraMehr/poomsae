<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

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
        return [
            'request_id' => ['required', 'uuid'],
            'judge_assignment_id' => ['required', 'integer', 'min:1'],
            'expected_version' => ['required', 'integer', 'min:1'],
            'expected_revision' => ['required', 'integer', 'in:0'],
            'accuracy' => ['required', 'string', 'regex:/^\d{1,2}(?:\.\d{1,2})?$/D'],
            'presentation' => ['required', 'string', 'regex:/^\d{1,2}(?:\.\d{1,2})?$/D'],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }
}
