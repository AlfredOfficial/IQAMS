<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Instructor extends Model
{
    use HasFactory;
 
    protected $fillable = [
        'user_id',
        'department_id',
        'employee_no',
        'name_prefix',
        'first_name',
        'middle_name',
        'last_name',
        'professional_credentials',
        'qr_code',
    ];
 
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
 
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
 
    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }
 
    public function fullName(): string
    {
        return self::formatFullName($this->only([
            'name_prefix', 'first_name', 'middle_name', 'last_name', 'professional_credentials',
        ]));
    }

    public static function formatFullName(array $name): string
    {
        $baseName = collect([
            $name['name_prefix'] ?? null,
            $name['first_name'] ?? null,
            $name['middle_name'] ?? null,
            $name['last_name'] ?? null,
        ])->map(fn ($part) => trim((string) $part))->filter()->implode(' ');

        $credentials = trim((string) ($name['professional_credentials'] ?? ''), " \t\n\r\0\x0B,");

        return $credentials ? "{$baseName}, {$credentials}" : $baseName;
    }
}
