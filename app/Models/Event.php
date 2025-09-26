<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'description', 'participant_summary', 'location', 'start_at', 'end_at', 'all_day', 'created_by',
        'recurrence_type', 'recurrence_rule', 'recurrence_until',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'all_day' => 'boolean',
        'recurrence_rule' => 'array',
        'recurrence_until' => 'date',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(EventParticipant::class);
    }

    public function participantUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_participants')
            ->withPivot(['participant_role'])
            ->withTimestamps();
    }

    public function divisions(): BelongsToMany
    {
        return $this->belongsToMany(Division::class, 'event_divisions')
            ->withTimestamps();
    }

    // Scope to get events overlapping the given range
    public function scopeOverlap(Builder $query, CarbonInterface|string $start, CarbonInterface|string $end): Builder
    {
        return $query->where(function ($w) use ($start, $end) {
            $w->whereBetween('start_at', [$start, $end])
              ->orWhereBetween('end_at', [$start, $end])
              ->orWhere(function ($ov) use ($start, $end) {
                  $ov->where('start_at', '<=', $start)
                     ->where('end_at', '>=', $end);
              });
        });
    }
}

