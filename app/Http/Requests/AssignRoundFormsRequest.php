<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignRoundFormsRequest extends FormRequest
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
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'round_id' => ['required', 'integer', 'min:1'],
            'form_ids' => ['required', 'array', 'list', 'size:2'],
            'form_ids.*' => ['required', 'integer', 'distinct', 'min:1'],
        ];
    }
}
