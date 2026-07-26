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
        'first_name',
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
 
    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }
 
    public function fullName(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }
}
