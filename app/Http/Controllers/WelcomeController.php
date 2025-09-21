<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

final class WelcomeController extends Controller
{
    public function home(Request $request): Response|RedirectResponse
    {
        if ($request->user()) {
            return redirect()->route('calendar.index');
        }

        return Inertia::render('Welcome', [
            'canLogin' => Route::has('login'),
            'canRegister' => Route::has('register'),
            'seo' => [
                'title' => 'Home',
            ],
        ]);
    }
}

