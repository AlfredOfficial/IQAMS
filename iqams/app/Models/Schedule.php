<?php

namespace App\Models;

use App\Models\Concerns\Archivable;
use App\Services\IntegrityKeyService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class Schedule extends Model
{
    use Archivable, HasFactory;

    protected $fillable = [
        'subject_id',
        'recurring_schedule_group_id',
        'instructor_id',
        'section_id',
        'day',
        'start_time',
        'end_time',
        'room',
        'archived_at',
        'active_identity_key',
    ];

    protected function casts(): array
    {
        return ['archived_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::saving(function (Schedule $schedule): void {
            if (substr((string) $schedule->start_time, 0, 5) === substr((string) $schedule->end_time, 0, 5)) {
                throw ValidationException::withMessages([
                    'end_time' => 'The schedule must have different start and end times.',
                ]);
            }

            $keys = app(IntegrityKeyService::class);
            $schedule->active_identity_key = $keys->scheduleIsActive($schedule)
                ? $keys->scheduleKey($schedule)
                : null;
        });
    }

    public function scopeActive($query)
    {
        return $query->whereNull('archived_at')
            ->whereHas('subject', fn ($subject) => $subject->whereNull('archived_at'))
            ->whereHas('section', fn ($section) => $section->active());
    }

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
