<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'participant_summary' => $this->participant_summary,
            'location' => $this->location,
            'start_at' => optional($this->start_at)->toIso8601String(),
            'end_at' => optional($this->end_at)->toIso8601String(),
            'all_day' => (bool) $this->all_day,
            'occurrence_key' => $this->when(isset($this->occurrence_key), fn() => $this->occurrence_key),
            'divisions' => $this->whenLoaded('divisions', fn() => $this->divisions->map(fn($d) => [
                'id' => $d->id,
                'name' => $d->name,
            ])),
            'participants' => $this->whenLoaded('participantUsers', fn() => $this->participantUsers->map(fn($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'division' => $u->relationLoaded('division') && $u->division ? [
                    'id' => $u->division->id,
                    'name' => $u->division->name,
                ] : null,
            ])),
        ];
    }
}
