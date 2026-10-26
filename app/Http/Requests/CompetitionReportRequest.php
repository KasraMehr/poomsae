<?php

namespace App\Http\Requests;

use App\Models\JudgeAssignment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompetitionReportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('operate', $this->route('tournament')) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tournament = $this->route('tournament');
        $judgeIds = JudgeAssignment::whereHas('bout.category', fn ($query) => $query->where('tournament_id', $tournament->id))->distinct()->pluck('user_id');

        return [
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where('tournament_id', $tournament->id)],
            'judge_id' => ['nullable', 'integer', Rule::in($judgeIds->all())],
            'user_id' => ['nullable', 'integer', Rule::exists('audit_logs', 'user_id')->where('tournament_id', $tournament->id)],
            'action' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', Rule::when($this->filled('from'), 'after_or_equal:from')],
            'page' => ['nullable', 'integer', 'min:1'],
            'export' => ['nullable', Rule::in(['csv', 'xlsx'])],
        ];
    }
}
