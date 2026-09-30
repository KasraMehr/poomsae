<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompetitionSetupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('tournament'));
    }

    public function rules(): array
    {
        $tournament = $this->route('tournament');

        return match ($this->route()->getActionMethod()) {
            'court' => ['name' => ['required', 'string', 'max:100', Rule::unique('courts')->where('tournament_id', $tournament->id)]],
            'member' => [
                'name' => ['required', 'string', 'max:100'], 'email' => ['required', 'email', 'max:255'],
                'password' => ['nullable', 'string', 'min:12', 'max:255'],
                'role' => ['required', Rule::in(['judge', 'operator', 'display'])],
            ],
            'category' => [
                'name' => ['required', 'string', 'max:100', Rule::unique('categories')->where('tournament_id', $tournament->id)->ignore($this->route('category')?->id)],
                'gender' => ['required', Rule::in(['male', 'female', 'open'])],
                'minimum_age' => ['nullable', 'integer', 'min:1', 'max:100'],
                'maximum_age' => ['nullable', 'integer', 'min:1', 'max:100', 'gte:minimum_age'],
                'format' => ['required', Rule::in(['knockout', 'round_robin'])],
                'execution_mode' => ['sometimes', Rule::in(['alternating', 'simultaneous'])],
                'performance_order' => ['sometimes', Rule::in(['consecutive', 'phased'])],
                'judge_count' => ['required', 'integer', Rule::in([5, 7])],
                'accuracy_max' => ['required', 'integer', 'min:1', 'max:999'],
                'discard_each_end' => ['required', 'integer', Rule::in($this->integer('judge_count') === 7 ? [1, 2] : [1])],
                'rules_acknowledged' => ['accepted'],
                'form_names' => ['required', 'array', 'size:2'],
                'form_names.*' => ['required', 'string', 'max:100', 'distinct'],
            ],
            'entry' => [
                'first_name' => ['required', 'string', 'max:100'], 'last_name' => ['required', 'string', 'max:100'],
                'birth_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.$tournament->starts_on->format('Y-m-d')],
                'gender' => ['required', Rule::in(['male', 'female'])], 'club' => ['nullable', 'string', 'max:100'],
            ],
            'entryStatus' => ['status' => ['required', Rule::in(['registered', 'checked_in', 'withdrawn'])]],
            default => [],
        };
    }
}
