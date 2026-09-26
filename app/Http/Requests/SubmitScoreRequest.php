<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitScoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasTournamentRole($this->route('tournament'), ['judge']);
    }

    public function rules(): array
    {
        return [
            'request_id' => ['required', 'uuid'], 'expected_version' => ['required', 'integer', 'min:1'],
            'expected_revision' => ['required', 'integer', 'min:0'],
            'accuracy' => ['required', 'string', 'regex:/^\\d{1,2}(?:\\.\\d{1,2})?$/D'],
            'presentation' => ['required', 'string', 'regex:/^\\d{1,2}(?:\\.\\d{1,2})?$/D'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
