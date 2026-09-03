<?php

namespace App\Models;

use App\Models\Concerns\Archivable;
use App\Services\DashboardReferenceCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subject extends Model
{
    use Archivable, HasFactory;

    protected $fillable = [
        'subject_code',
        'subject_name',
        'units',
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

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }
}
