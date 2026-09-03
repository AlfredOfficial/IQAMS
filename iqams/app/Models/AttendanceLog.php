<?php

namespace App\Models;

use App\Services\IntegrityKeyService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class AttendanceLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'schedule_id',
        'school_event_id',
        'attendance_type',
        'attendance_period',
        'scan_time',
        'attendance_date',
        'scan_key',
        'integrity_key',
        'record_state',
        'superseded_by_id',
        'status',
        'punctuality_status',
        'scanner_location',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'scan_time' => 'datetime',
            'attendance_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (): void {
            throw new LogicException('Attendance logs are retained; void the record instead.');
        });

        static::saving(function (AttendanceLog $log): void {
            $service = app(IntegrityKeyService::class);
            $log->attendance_date = $service->attendanceDate($log->scan_time);
            $log->record_state ??= 'canonical';
            $log->integrity_key = $log->record_state === 'canonical'
                ? $service->forAttendance($log)
                : null;
        });
    }

    public function scopeCanonical($query)
    {
        return $query->where(function ($query) {
            $query->where('record_state', 'canonical')->orWhereNull('record_state');
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    public function schoolEvent(): BelongsTo
    {
        return $this->belongsTo(SchoolEvent::class);
    }
}
