<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CalendarHoliday;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class HolidayController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $holidays = CalendarHoliday::query()
            ->orderBy('calendar_type')
            ->orderBy('gregorian_month')
            ->orderBy('gregorian_day')
            ->orderBy('hijri_month')
            ->orderBy('hijri_day')
            ->get([
                'id',
                'name',
                'calendar_type',
                'gregorian_month',
                'gregorian_day',
                'gregorian_year',
                'hijri_month',
                'hijri_day',
                'hijri_year',
            ]);

        return response()->json([
            'data' => $holidays,
        ]);
    }
}
