<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Schedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'subject_id',
        'recurring_schedule_group_id',
        'instructor_id',
        'section_id',
        'day',
        'start_time',
        'end_time',
        'room',
    ];

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }
 
    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class);
    }
 
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }
 
    public function attendanceLogs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class);
    }

    public function recurringSchedules(): HasMany
    {
        return $this->hasMany(self::class, 'recurring_schedule_group_id', 'recurring_schedule_group_id');
    }
}
