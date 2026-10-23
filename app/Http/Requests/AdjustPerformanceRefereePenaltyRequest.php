<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AdjustPerformanceRefereePenaltyRequest extends FormRequest
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
            'penalty_hundredths' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'penalty' => ['nullable', 'string', 'regex:/^\d{1,2}(?:\.\d{1,2})?$/D'],
            'reason' => ['nullable', 'string', 'max:500'],
            'expected_version' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param  Validator  $validator
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->input('penalty_hundredths') === null && $this->input('penalty') === null) {
                $validator->errors()->add('penalty_hundredths', 'میزان کسر نمره باید مشخص شود.');
            }
        });
    }
}
