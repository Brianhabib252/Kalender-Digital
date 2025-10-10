<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\Division;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;

class EventCrudTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_can_create_update_and_delete_event(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);

        $division = Division::create(['name' => 'Operasional']);
        $participant = User::factory()->create();

        Sanctum::actingAs($admin);

        $payload = [
            'title' => 'Planning Meeting',
            'description' => 'Initial sync with stakeholders',
            'start_at' => '2025-01-10T09:00:00',
            'end_at' => '2025-01-10T10:00:00',
            'all_day' => false,
            'division_ids' => [$division->id],
            'participant_user_ids' => [$participant->id],
        ];

        $createResponse = $this->postJson('/api/events', $payload);

        $createResponse->assertCreated();
        $eventId = $createResponse->json('data.id');

        $this->assertNotNull($eventId);
        $this->assertDatabaseHas('events', [
            'id' => $eventId,
            'title' => 'Planning Meeting',
        ]);

        $updatePayload = [
            'title' => 'Planning Sync Updated',
            'description' => 'Updated agenda and timeline',
            'start_at' => '2025-01-10T09:30:00',
            'end_at' => '2025-01-10T11:00:00',
            'division_ids' => [$division->id],
            'participant_user_ids' => [$participant->id],
        ];

        $updateResponse = $this->putJson("/api/events/{$eventId}", $updatePayload);
        $updateResponse->assertOk();

        $this->assertDatabaseHas('events', [
            'id' => $eventId,
            'title' => 'Planning Sync Updated',
        ]);

        $deleteResponse = $this->deleteJson("/api/events/{$eventId}");
        $deleteResponse->assertNoContent();

        $this->assertDatabaseMissing('events', [
            'id' => $eventId,
        ]);
    }
}
