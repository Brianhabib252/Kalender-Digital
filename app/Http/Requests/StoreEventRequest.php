<?php

namespace App\Http\Requests;

use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;

class StoreEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Event::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required','string','max:255'],
            'description' => ['nullable','string'],
            'location' => ['nullable','string','max:255'],
            'start_at' => ['required','date'],
            'end_at' => ['required','date','after:start_at'],
            'all_day' => ['boolean'],
            'participant_user_ids' => ['nullable','array'],
            'participant_user_ids.*' => ['integer','exists:users,id'],
            'division_ids' => ['nullable','array'],
            'division_ids.*' => ['integer','exists:divisions,id'],
            // Recurrence
            'recurrence_type' => ['nullable','in:weekly,monthly'],
            'recurrence_interval' => ['nullable','integer','min:1'],
            'recurrence_days' => ['nullable','array'], // for weekly, 1=Mon ... 7=Sun
            'recurrence_days.*' => ['integer','between:1,7'],
            'recurrence_month_days' => ['nullable','array'], // for monthly, 1..31
            'recurrence_month_days.*' => ['integer','between:1,31'],
            'recurrence_until' => ['nullable','date','after:start_at'],
        ];
    }
}
