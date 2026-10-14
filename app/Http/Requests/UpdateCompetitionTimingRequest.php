<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCompetitionTimingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('tournament'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'minimum_duration_seconds' => ['nullable', 'integer', 'min:0', 'max:3600'],
            'maximum_duration_seconds' => ['required_if:enabled,true', 'nullable', 'integer', 'min:1', 'max:3600', 'gte:minimum_duration_seconds'],
            'minimum_rest_seconds' => ['required', 'integer', 'min:0', 'max:3600'],
            'time_penalty_hundredths' => ['required', 'integer', 'min:0', 'max:1000'],
        ];
    }
}
