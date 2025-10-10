<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCalendarHolidayRequest;
use App\Models\CalendarHoliday;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class CalendarHolidayController extends Controller
{
    public function store(StoreCalendarHolidayRequest $request): JsonResponse
    {
        $data = $request->validated();

        $holiday = CalendarHoliday::query()->create([
            'name' => $data['name'],
            'calendar_type' => $data['calendar_type'],
            'gregorian_month' => $data['gregorian_month'] ?? null,
            'gregorian_day' => $data['gregorian_day'] ?? null,
            'gregorian_year' => $data['gregorian_year'] ?? null,
            'hijri_month' => $data['hijri_month'] ?? null,
            'hijri_day' => $data['hijri_day'] ?? null,
            'hijri_year' => $data['hijri_year'] ?? null,
        ]);

        return response()->json([
            'data' => $holiday->fresh()->toArray(),
        ], Response::HTTP_CREATED);
    }

    public function destroy(Request $request, CalendarHoliday $holiday): Response
    {
        $user = $request->user();
        \abort_unless($user instanceof User && $user->isAdmin(), Response::HTTP_FORBIDDEN);

        $holiday->delete();

        return response()->noContent();
    }
}
