<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmProxyScoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('operate', $this->route('tournament'));
    }

    public function rules(): array
    {
        return ['expected_revision' => ['required', 'integer', 'min:1']];
    }
}
