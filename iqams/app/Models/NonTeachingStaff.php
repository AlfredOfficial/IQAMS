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
        'employee_no',
        'first_name',
        'middle_name',
        'last_name',
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
 
    public function fullName(): string
    {
        return implode(' ', array_filter([
            $this->first_name,
            $this->middle_name,
            $this->last_name,
        ], fn ($part) => filled($part)));
    }
}
