<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PerformanceCommandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('operate', $this->route('tournament'));
    }

    public function rules(): array
    {
        return ['command' => ['required', Rule::in(['start', 'finish', 'approve'])], 'expected_version' => ['required', 'integer', 'min:1']];
    }
}
