<?php

namespace App\Models;

use App\Services\DashboardReferenceCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'role_id',
        'username',
        'name',
        'email',
        'avatar_path',
        'password',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected static function booted(): void
    {
        static::saved(function (User $user) {
            DashboardReferenceCache::forget();

            // Compatibility bridge for legacy code that still writes users.role_id.
            if ($user->role_id && Schema::hasTable('model_has_roles')) {
                $role = Role::find($user->role_id);
                if ($role && $user->roles()->whereKey($role->id)->count() !== 1) {
                    $user->syncRoles([$role]);
                }
            }
        });
        static::deleted(fn () => DashboardReferenceCache::forget());
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function student(): HasOne
    {
        return $this->hasOne(Student::class);
    }

    public function instructor(): HasOne
    {
        return $this->hasOne(Instructor::class);
    }

    public function nonTeachingStaff(): HasOne
    {
        return $this->hasOne(NonTeachingStaff::class);
    }

    public function attendanceLogs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class);
    }

    public function qrCredentials(): HasMany
    {
        return $this->hasMany(QrCredential::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function isAccountActive(): bool
    {
        return $this->status === 'active';
    }

    public function getAvatarUrlAttribute(): ?string
    {
        if (! $this->avatar_path) {
            return null;
        }

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('public');

        return $disk->url($this->avatar_path);
    }

    // Convenience helpers for role checks in middleware/blade
    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    public function isInstructor(): bool
    {
        return $this->hasRole('instructor');
    }

    public function isStaff(): bool
    {
        return $this->hasRole('staff');
    }

    public function isStudent(): bool
    {
        return $this->hasRole('student');
    }
}
