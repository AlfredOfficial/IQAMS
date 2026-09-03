<?php

namespace App\Models;

use App\Models\Concerns\Archivable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolEvent extends Model
{
    use Archivable;

    protected $fillable = [
        'title', 'description', 'location', 'starts_at', 'ends_at',
        'attendance_mode', 'target_scope', 'status', 'published_at', 'attendance_finalized_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'published_at' => 'datetime',
            'attendance_finalized_at' => 'datetime',
        ];
    }

    public function targets(): HasMany
    {
        return $this->hasMany(SchoolEventTarget::class);
    }

    public function attendanceLogs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class);
    }
}
