<?php

declare(strict_types=1);

use App\Models\User;

test('login screen can be rendered', function (): void {
    $response = $this->get('/login');

    $response->assertStatus(200);
});

test('users can authenticate using the login screen', function (): void {
    $user = User::factory()->create();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('calendar.index', absolute: false));
});

test('users cannot authenticate with invalid password', function (): void {
    $user = User::factory()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('inactive users cannot authenticate', function (): void {
    $user = User::factory()->create([
        'role' => User::ROLE_INACTIVE,
    ]);

    $response = $this->from('/login')->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect('/login');
    $response->assertSessionHasErrors([
        'email' => 'Mohon Maaf Akun Anda Belum di Aktifkan, Mohon Hubungi Admin Untuk Mengaktifkan Akun',
    ]);

    $this->assertGuest();
});
