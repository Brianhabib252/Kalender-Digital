<?php

declare(strict_types=1);

it('redirects home to login', function (): void {
    $response = $this->get('/');

    $response->assertRedirect(route('login', absolute: false));
});