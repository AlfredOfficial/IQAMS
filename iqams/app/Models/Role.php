<?php

namespace App\Models;

use App\Services\DashboardReferenceCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'role_name',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => DashboardReferenceCache::forget());
        static::deleted(fn () => DashboardReferenceCache::forget());
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
