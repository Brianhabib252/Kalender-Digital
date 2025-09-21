<?php

namespace App\Http\Requests;

use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        $event = $this->route('event');

        return $event instanceof Event
            ? ($this->user()?->can('update', $event) ?? false)
            : false;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes','required','string','max:255'],
            'description' => ['nullable','string'],
            'location' => ['nullable','string','max:255'],
            'start_at' => ['sometimes','required','date'],
            'end_at' => ['sometimes','required','date','after:start_at'],
            'all_day' => ['boolean'],
            'participant_user_ids' => ['nullable','array'],
            'participant_user_ids.*' => ['integer','exists:users,id'],
            'division_ids' => ['nullable','array'],
            'division_ids.*' => ['integer','exists:divisions,id'],
            // Recurrence
            'recurrence_type' => ['nullable','in:weekly,monthly'],
            'recurrence_interval' => ['nullable','integer','min:1'],
            'recurrence_days' => ['nullable','array'],
            'recurrence_days.*' => ['integer','between:1,7'],
            'recurrence_month_days' => ['nullable','array'],
            'recurrence_month_days.*' => ['integer','between:1,31'],
            'recurrence_until' => ['nullable','date','after:start_at'],
        ];
    }
}
