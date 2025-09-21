<?php

declare(strict_types=1);

use App\Models\User;

it('redirects guests attempting to access the calendar', function (): void {
    $response = $this->get('/calendar');

    $response->assertRedirect(route('login', absolute: false));
});

it('allows authenticated users to view the calendar', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/calendar');

    $response->assertOk();
});

it('logs out authenticated users and invalidates the session', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = $this->post('/logout');

    $this->assertGuest();
    $response->assertRedirect('/');
});

