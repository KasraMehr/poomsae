<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecordPerformanceRestartRequest extends FormRequest
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
            'reason' => ['required', 'string', 'min:5', 'max:500'],
            'expected_version' => ['required', 'integer', 'min:1'],
        ];
    }
}
