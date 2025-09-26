<?php

namespace App\Http\Controllers;

use App\Models\Division;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CalendarController extends Controller
{
    public function index(Request $request)
    {
        $view = $request->string('view')->isNotEmpty() ? $request->get('view') : 'month';
        $date = $request->string('date')->isNotEmpty() ? $request->get('date') : now()->toDateString();

        $defaultDivisions = [
            'Seluruh Pegawai',
            'Hakim',
            'Kesekretariatan',
            'Kepaniteraan',
            'Dinas Luar',
        ];

        foreach ($defaultDivisions as $divisionName) {
            Division::query()->firstOrCreate(['name' => $divisionName]);
        }

        $divisions = Division::query()->orderBy('name')->get(['id','name']);

        return Inertia::render('Calendar/Index', [
            'today' => now()->toDateString(),
            'view' => $view,
            'date' => $date,
            'divisionOptions' => $divisions,
            'participantOptions' => User::query()->orderBy('name')->get(['id','name','email']),
        ]);
    }
}
