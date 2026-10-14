<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TkdhubDivisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('tournament'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return self::payloadRules($this->input('allowedFormCodes', []));
    }

    /** @return array<string, mixed> */
    public static function payloadRules(mixed $allowedForms): array
    {
        $allowedForms = is_array($allowedForms) ? $allowedForms : [];

        return [
            'eventId' => ['required', 'string', 'max:128', 'regex:/^[a-zA-Z0-9_.-]+$/D'],
            'divisionId' => ['required', 'string', 'max:128', 'regex:/^[a-zA-Z0-9_.-]+$/D'],
            'divisionName' => ['required', 'string', 'max:255'],
            'birthDateFrom' => ['nullable', 'required_without:birthDateTo', 'date_format:Y-m-d'],
            'birthDateTo' => ['nullable', 'required_without:birthDateFrom', 'date_format:Y-m-d'],
            'allowedFormCodes' => ['required', 'array', 'list', 'min:1', 'max:16'],
            'allowedFormCodes.*' => ['required', 'integer', 'between:1,16', 'distinct'],
            'form1Code' => ['required', 'integer', Rule::in($allowedForms)],
            'form2Code' => ['required', 'integer', Rule::in($allowedForms)],
            'gender' => ['sometimes', Rule::in(['male', 'female', 'mixed', 'open'])],
            'judgeCount' => ['sometimes', 'integer', Rule::in([3, 5, 7])],
            'athletes' => ['required', 'array', 'list', 'min:1', 'max:5000'],
            'athletes.*' => ['required', 'array:entryId,firstName,lastName,teamName,birthDate,drawPosition,status,gender'],
            'athletes.*.entryId' => ['required', 'string', 'max:128', 'regex:/^[a-zA-Z0-9_.-]+$/D', 'distinct:strict'],
            'athletes.*.firstName' => ['required', 'string', 'max:255'],
            'athletes.*.lastName' => ['required', 'string', 'max:255'],
            'athletes.*.teamName' => ['present', 'nullable', 'string', 'max:255'],
            'athletes.*.birthDate' => ['required', 'date_format:Y-m-d'],
            'athletes.*.drawPosition' => ['present', 'nullable', 'integer', 'between:1,65535', 'distinct'],
            'athletes.*.status' => ['sometimes', Rule::in(['active', 'absent', 'withdrawn'])],
            'athletes.*.gender' => ['sometimes', 'nullable', Rule::in(['male', 'female'])],
        ];
    }
}
