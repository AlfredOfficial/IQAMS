<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NonTeachingStaff extends Model
{
    use HasFactory;

    protected $table = 'non_teaching_staff';

    protected $fillable = [
        'user_id',
        'department_id',
        'office_unit_id',
        'employee_no',
        'name_prefix',
        'first_name',
        'middle_name',
        'last_name',
        'name_suffix',
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

    public function officeUnit(): BelongsTo
    {
        return $this->belongsTo(OfficeUnit::class);
    }

    public function fullName(): string
    {
        return self::formatFullName($this->only([
            'name_prefix', 'first_name', 'middle_name', 'last_name', 'name_suffix',
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

        $suffix = trim((string) ($name['name_suffix'] ?? ''), " \t\n\r\0\x0B,");

        return $suffix ? "{$baseName}, {$suffix}" : $baseName;
    }
}
