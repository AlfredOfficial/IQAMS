<?php

namespace App\Models;

use App\Services\DashboardReferenceCache;
use App\Models\Concerns\Archivable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    use Archivable, HasFactory;

    protected $fillable = [
        'department_code',
        'department_name',
        'archived_at',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => DashboardReferenceCache::forget());
        static::deleted(fn () => DashboardReferenceCache::forget());
    }

    protected function casts(): array
    {
        return ['archived_at' => 'datetime'];
    }

    public function scopeActive($query)
    {
        return $query->whereNull('archived_at');
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    public function instructors(): HasMany
    {
        return $this->hasMany(Instructor::class);
    }

    // public function nonTeachingStaff(): HasMany
    // {
    //     return $this->hasMany(NonTeachingStaff::class);
    // }
}
