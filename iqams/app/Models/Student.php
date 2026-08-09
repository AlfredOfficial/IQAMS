<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'student_no',
        'first_name',
        'last_name',
        'middle_name',
        'contact_number',
        'qr_code',
        'section_id',
        'course_id',
        'year_level',
        'status',
    ];

     
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
 
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }
 
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
 
    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->middle_name} {$this->last_name}");
    }
}
