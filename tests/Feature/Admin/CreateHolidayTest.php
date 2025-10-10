<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;

class CreateHolidayTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_can_create_gregorian_holiday(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);

        $payload = [
            'name' => 'Sample Day',
            'calendar_type' => 'gregorian',
            'gregorian_month' => 5,
            'gregorian_day' => 20,
        ];

        $response = $this->actingAs($admin)->postJson('/admin/holidays', $payload);

        $response->assertCreated();
        $this->assertDatabaseHas('calendar_holidays', [
            'name' => 'Sample Day',
            'calendar_type' => 'gregorian',
            'gregorian_month' => 5,
            'gregorian_day' => 20,
        ]);
    }
}