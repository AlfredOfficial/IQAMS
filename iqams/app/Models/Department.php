<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    use HasFactory;

    protected $fillable = [
        'department_code',
        'department_name',
    ];

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
