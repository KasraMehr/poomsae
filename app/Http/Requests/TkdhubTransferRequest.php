<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TkdhubTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('tournament'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $pull = $this->route('division') === null;

        return [
            'eventId' => [$pull ? 'required' : 'sometimes', 'string', 'max:128', 'regex:/^[a-zA-Z0-9_.-]+$/D'],
            'divisionId' => [$pull ? 'required' : 'sometimes', 'string', 'max:128', 'regex:/^[a-zA-Z0-9_.-]+$/D'],
            'judgeCount' => ['sometimes', 'integer', 'in:3,5,7'],
            'reason' => ['nullable', 'string', 'min:5', 'max:1000'],
        ];
    }
}
