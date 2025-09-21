<?php

declare(strict_types=1);

use App\Models\User;

test('profile information can be updated', function (): void {
    $this->actingAs($user = User::factory()->create([
        'nip' => '198765432109876549',
        'phone' => '08177777777',
    ]));

    $this->put('/user/profile-information', [
        'name' => 'Test Name',
        'email' => 'test@example.com',
        'nip' => '198765432109876550',
        'phone' => '08188888888',
    ]);

    expect($user->fresh())
        ->name->toEqual('Test Name')
        ->email->toEqual('test@example.com')
        ->nip->toEqual('198765432109876550')
        ->phone->toEqual('08188888888');
});