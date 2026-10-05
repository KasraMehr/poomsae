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
        $discipline = $this->input('discipline', 'recognized');
        $entryType = $this->input('entry_type', 'individual');
        $category = $this->route('category');
        $memberCount = match ($category?->entry_type) {
            'pair' => 2,
            'team' => 3,
            default => 1,
        };

        return match ($this->route()->getActionMethod()) {
            'court' => ['name' => ['required', 'string', 'max:100', Rule::unique('courts')->where('tournament_id', $tournament->id)]],
            'member' => [
                'name' => ['required', 'string', 'max:100'], 'email' => ['required', 'email', 'max:255'],
                'password' => ['nullable', 'string', 'min:12', 'max:255'],
                'role' => ['required', Rule::in(['judge', 'operator', 'display'])],
            ],
            'category' => [
                'name' => ['required', 'string', 'max:100', Rule::unique('categories')->where('tournament_id', $tournament->id)->ignore($this->route('category')?->id)],
                'discipline' => ['sometimes', Rule::in(['recognized', 'freestyle'])],
                'entry_type' => ['sometimes', Rule::in($discipline === 'freestyle' ? ['individual', 'pair'] : ['individual', 'team'])],
                'gender' => ['required', Rule::in($entryType === 'individual' ? ['male', 'female', 'open'] : ['male', 'female', 'mixed', 'open'])],
                'minimum_age' => ['nullable', 'integer', 'min:1', 'max:100'],
                'maximum_age' => ['nullable', 'integer', 'min:1', 'max:100', 'gte:minimum_age'],
                'format' => ['required', Rule::in(['knockout', 'round_robin'])],
                'execution_mode' => ['sometimes', Rule::in(['alternating', 'simultaneous'])],
                'performance_order' => ['sometimes', Rule::in(['consecutive', 'phased'])],
                'judge_count' => ['required', 'integer', Rule::in([5, 7])],
                'accuracy_max' => ['sometimes', 'integer', Rule::in($discipline === 'freestyle' ? [600] : [400])],
                'discard_each_end' => ['required', 'integer', Rule::in($this->integer('judge_count') === 7 ? [1, 2] : [1])],
                'rules_acknowledged' => ['accepted'],
                'planned_round_count' => ['sometimes', 'integer', 'min:1', 'max:6'],
                'stage_names' => ['sometimes', 'array', 'size:'.$this->integer('planned_round_count', 1)],
                'stage_names.*' => ['required', 'string', 'max:100'],
                'allow_form_repetition' => ['sometimes', 'boolean'],
                'form_draw_timing' => ['sometimes', Rule::in(['day_before', 'morning', 'before_stage'])],
                'form_draw_time' => ['sometimes', 'date_format:H:i'],
                'draw_method' => ['sometimes', Rule::in(['random'])],
                'form_names' => [$discipline === 'recognized' ? 'required' : 'prohibited', 'array', 'size:'.($this->has('planned_round_count') ? 8 : 2)],
                'form_names.*' => ['required', 'string', 'max:100', 'distinct'],
            ],
            'entry' => [
                'first_name' => [$memberCount === 1 ? 'required' : 'prohibited', 'string', 'max:100'],
                'last_name' => [$memberCount === 1 ? 'required' : 'prohibited', 'string', 'max:100'],
                'birth_date' => [$memberCount === 1 ? 'required' : 'prohibited', 'date_format:Y-m-d', 'before_or_equal:'.$tournament->starts_on->format('Y-m-d')],
                'gender' => [$memberCount === 1 ? 'required' : 'prohibited', Rule::in(['male', 'female'])],
                'club' => [$memberCount === 1 ? 'nullable' : 'prohibited', 'string', 'max:100'],
                'members' => [$memberCount === 1 ? 'prohibited' : 'required', 'array', 'size:'.$memberCount],
                'members.*.first_name' => ['required', 'string', 'max:100'],
                'members.*.last_name' => ['required', 'string', 'max:100'],
                'members.*.birth_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.$tournament->starts_on->format('Y-m-d')],
                'members.*.gender' => ['required', Rule::in(['male', 'female'])],
                'members.*.club' => ['nullable', 'string', 'max:100'],
                'music' => [$category?->discipline === 'freestyle' ? 'required' : 'prohibited', 'file', 'mimes:mp3,wav,m4a', 'max:20480'],
            ],
            'entryStatus' => ['status' => ['required', Rule::in(['registered', 'checked_in', 'withdrawn'])]],
            default => [],
        };
    }
}
