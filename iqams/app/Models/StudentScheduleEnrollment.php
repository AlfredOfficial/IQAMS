<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentScheduleEnrollment extends Model
{
    use HasFactory;

    protected $fillable = ['student_id', 'recurring_schedule_group_id'];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
