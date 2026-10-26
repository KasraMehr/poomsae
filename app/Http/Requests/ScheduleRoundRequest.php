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
        return [
            'court_id' => ['required_without:courts', 'prohibits:courts', 'integer', 'min:1'],
            'judge_ids' => ['required_without:courts', 'prohibits:courts', 'array', 'list'],
            'judge_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
            'courts' => ['required_without:court_id', 'array', 'list', 'min:1', 'max:4'],
            'courts.*' => ['required', 'array:court_id,judge_ids'],
            'courts.*.court_id' => ['required', 'integer', 'min:1', 'distinct'],
            'courts.*.judge_ids' => ['required', 'array', 'list'],
            'courts.*.judge_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
        ];
    }
}
