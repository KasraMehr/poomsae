<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ScheduleRoundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('tournament'));
    }

    public function rules(): array
    {
        return ['court_id' => ['required', 'integer'], 'judge_ids' => ['required', 'array'], 'judge_ids.*' => ['required', 'integer', 'distinct']];
    }
}
