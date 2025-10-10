<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CalendarHoliday extends Model
{
    use HasFactory;

    public const CALENDAR_GREGORIAN = 'gregorian';
    public const CALENDAR_HIJRI = 'hijri';
    public const CALENDAR_BOTH = 'both';

    protected $fillable = [
        'name',
        'calendar_type',
        'gregorian_month',
        'gregorian_day',
        'gregorian_year',
        'hijri_month',
        'hijri_day',
        'hijri_year',
    ];

    protected $casts = [
        'gregorian_month' => 'integer',
        'gregorian_day' => 'integer',
        'gregorian_year' => 'integer',
        'hijri_month' => 'integer',
        'hijri_day' => 'integer',
        'hijri_year' => 'integer',
    ];
}
