<?php

namespace App\Models;

use App\Services\IntegrityKeyService;
use App\Services\DashboardReferenceCache;
use App\Models\Concerns\Archivable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Section extends Model
{
    use Archivable, HasFactory;

    protected $fillable = [
        'course_id',
        'section_name',
        'school_year',
        'semester',
        'archived_at',
        'active_identity_key',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => DashboardReferenceCache::forget());
        static::deleted(fn () => DashboardReferenceCache::forget());
        static::saving(function (Section $section): void {
            $keys = app(IntegrityKeyService::class);
            $section->active_identity_key = $keys->sectionIsActive($section)
                ? $keys->sectionKey($section)
                : null;
        });
    }

    protected function casts(): array
    {
        return ['archived_at' => 'datetime'];
    }

    public function scopeActive($query)
    {
        return $query->whereNull('archived_at')
            ->whereHas('course', fn ($course) => $course->active());
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }
}
