<?php

namespace App\Models;

use App\Services\DashboardReferenceCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OfficeUnit extends Model
{
    protected $fillable = ['code', 'name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => DashboardReferenceCache::forget());
        static::deleted(fn () => DashboardReferenceCache::forget());
    }

    public function staff(): HasMany
    {
        return $this->hasMany(NonTeachingStaff::class);
    }
}
