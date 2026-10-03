<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ManagementSnapshotRequest extends FormRequest
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
        $category = $this->route('category');
        $count = match ($category->entry_type) {
            'pair' => 2, 'team' => 3, default => 1
        };

        return [
            'source' => ['required', 'string', 'max:100'],
            'version' => ['required', 'integer', 'min:1'],
            'expected_version' => ['required', 'integer', 'min:0'],
            'reason' => ['nullable', 'string', 'min:5', 'max:1000'],
            'draw_method' => ['required', Rule::in(['random'])],
            'category' => ['sometimes', 'array:name,minimum_age,maximum_age,form_names'],
            'category.name' => ['sometimes', 'string', 'max:100', Rule::unique('categories', 'name')->where('tournament_id', $this->route('tournament')->id)->ignore($category->id)],
            'category.minimum_age' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
            'category.maximum_age' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
            'category.form_names' => [$category->discipline === 'freestyle' ? 'prohibited' : 'sometimes', 'array', 'list', 'size:8'],
            'category.form_names.*' => ['required', 'string', 'max:100', 'distinct'],
            'entries' => ['required', 'array', 'list', 'min:2', 'max:64'],
            'entries.*' => ['array:external_id,local_entry_id,status,members'],
            'entries.*.external_id' => ['required', 'string', 'max:100', 'distinct'],
            'entries.*.local_entry_id' => ['sometimes', 'integer', 'min:1', 'distinct'],
            'entries.*.status' => ['required', Rule::in(['registered', 'checked_in', 'withdrawn'])],
            'entries.*.members' => ['required', 'array', 'list', 'size:'.$count],
            'entries.*.members.*' => ['array:first_name,last_name,birth_date,gender,club'],
            'entries.*.members.*.first_name' => ['required', 'string', 'max:100'],
            'entries.*.members.*.last_name' => ['required', 'string', 'max:100'],
            'entries.*.members.*.birth_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.$this->route('tournament')->starts_on->format('Y-m-d')],
            'entries.*.members.*.gender' => ['required', Rule::in(['male', 'female'])],
            'entries.*.members.*.club' => ['nullable', 'string', 'max:100'],
            'stages' => ['required', 'array', 'list', 'min:1', 'max:6'],
            'stages.*' => ['array:sequence,name,form_ids,form_names,court_id,judge_ids,bouts'],
            'stages.*.sequence' => ['required', 'integer', 'min:1', 'max:6', 'distinct'],
            'stages.*.name' => ['required', 'string', 'max:100'],
            'stages.*.form_ids' => ['present', 'array', 'list', 'max:2'],
            'stages.*.form_ids.*' => ['required', 'integer', 'min:1'],
            'stages.*.form_names' => [$category->discipline === 'freestyle' ? 'prohibited' : 'sometimes', 'array', 'list', 'size:2'],
            'stages.*.form_names.*' => ['required', 'string', 'max:100'],
            'stages.*.court_id' => ['required', 'integer', 'min:1'],
            'stages.*.judge_ids' => ['required', 'array', 'list', 'size:'.$category->judge_count],
            'stages.*.judge_ids.*' => ['required', 'integer', 'min:1'],
            'stages.*.bouts' => ['present', 'array', 'list', 'max:32'],
            'stages.*.bouts.*' => ['required', 'array', 'list', 'min:1', 'max:'.($category->format === 'round_robin' ? 1 : 2)],
            'stages.*.bouts.*.*' => ['required', 'string', 'max:100'],
        ];
    }
}
