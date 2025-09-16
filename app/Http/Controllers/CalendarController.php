<?php

namespace App\Http\Controllers;

use App\Models\Division;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CalendarController extends Controller
{
    public function index(Request $request)
    {
        $view = $request->string('view')->isNotEmpty() ? $request->get('view') : 'month';
        $date = $request->string('date')->isNotEmpty() ? $request->get('date') : now()->toDateString();

        $divisions = Division::query()->orderBy('name')->get(['id','name']);

        return Inertia::render('Calendar/Index', [
            'today' => now()->toDateString(),
            'view' => $view,
            'date' => $date,
            'divisionOptions' => $divisions,
        ]);
    }
}

