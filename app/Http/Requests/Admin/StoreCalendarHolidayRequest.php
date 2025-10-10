<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\CalendarHoliday;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class StoreCalendarHolidayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User && $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'calendar_type' => [
                'required',
                Rule::in([
                    CalendarHoliday::CALENDAR_GREGORIAN,
                    CalendarHoliday::CALENDAR_HIJRI,
                    CalendarHoliday::CALENDAR_BOTH,
                ]),
            ],
            'gregorian_month' => ['nullable', 'integer', 'between:1,12'],
            'gregorian_day' => ['nullable', 'integer', 'between:1,31'],
            'gregorian_year' => ['nullable', 'integer', 'between:1900,2100'],
            'hijri_month' => ['nullable', 'integer', 'between:1,12'],
            'hijri_day' => ['nullable', 'integer', 'between:1,30'],
            'hijri_year' => ['nullable', 'integer', 'between:1300,1700'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $type = (string) $this->input('calendar_type');
            $hasGregorian = $this->filled('gregorian_month') && $this->filled('gregorian_day');
            $hasHijri = $this->filled('hijri_month') && $this->filled('hijri_day');

            if (in_array($type, [CalendarHoliday::CALENDAR_GREGORIAN, CalendarHoliday::CALENDAR_BOTH], true) && ! $hasGregorian) {
                $validator->errors()->add('gregorian_month', 'Tanggal Gregorian wajib diisi.');
            }

            if (in_array($type, [CalendarHoliday::CALENDAR_HIJRI, CalendarHoliday::CALENDAR_BOTH], true) && ! $hasHijri) {
                $validator->errors()->add('hijri_month', 'Tanggal Hijriah wajib diisi.');
            }

            if (! $hasGregorian && ! $hasHijri) {
                $validator->errors()->add('calendar_type', 'Setidaknya salah satu tanggal harus diisi.');
            }
        });
    }
}
