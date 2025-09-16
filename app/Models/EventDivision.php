<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventDivision extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id', 'division_id',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }
}

