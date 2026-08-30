<?php

namespace App\Models;

use App\Services\DashboardReferenceCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    use HasFactory;

    protected $fillable = [
        'role_name',
        'name',
        'guard_name',
    ];

    public static function create(array $attributes = [])
    {
        $attributes['name'] ??= $attributes['role_name'] ?? null;
        $attributes['role_name'] ??= $attributes['name'] ?? null;
        $attributes['guard_name'] ??= 'web';

        return parent::create($attributes);
    }

    protected static function booted(): void
    {
        static::saving(function (Role $role) {
            $role->guard_name ??= 'web';
            $role->name ??= $role->role_name;
            $role->role_name ??= $role->name;
        });

        static::saved(fn () => DashboardReferenceCache::forget());
        static::deleted(fn () => DashboardReferenceCache::forget());
    }
}
