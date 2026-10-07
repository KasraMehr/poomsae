<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompetitionSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $tournament = $this->route('tournament');
        if (! $this->user()->can('update', $tournament)) {
            return false;
        }
        if ($court = $this->route('court')) {
            abort_unless($court->tournament_id === $tournament->id, 404);
        }
        if ($entry = $this->route('entry')) {
            abort_unless($entry->category->tournament_id === $tournament->id, 404);
        }

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tournament = $this->route('tournament');

        return match ($this->route()->getActionMethod()) {
            'update' => [
                'name' => ['required', 'string', 'max:255'],
                'venue' => ['nullable', 'string', 'max:255'],
                'starts_on' => ['required', 'date_format:Y-m-d'],
                'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
                'timezone' => ['required', 'timezone'],
            ],
            'updateCourt' => ['name' => ['required', 'string', 'max:100', Rule::unique('courts')->where('tournament_id', $tournament->id)->ignore($this->route('court')->id)]],
            'updateEntry' => [
                'members' => ['required', 'array', 'list', 'size:'.$this->route('entry')->athletes()->count()],
                'members.*' => ['required', 'array:id,first_name,last_name,birth_date,gender,club'],
                'members.*.id' => ['required', 'integer', 'distinct'],
                'members.*.first_name' => ['required', 'string', 'max:100'],
                'members.*.last_name' => ['required', 'string', 'max:100'],
                'members.*.birth_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.$tournament->starts_on->format('Y-m-d')],
                'members.*.gender' => ['required', Rule::in(['male', 'female'])],
                'members.*.club' => ['nullable', 'string', 'max:100'],
            ],
            default => [],
        };
    }
}
