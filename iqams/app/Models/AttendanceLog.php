<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'schedule_id',
        'attendance_type',
        'scan_time',
        'status',
        'scanner_location',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'scan_time' => 'datetime',
        ];
    }
 
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
 
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }
}
