<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class LeaveRequest extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'leave_type', 'start_date', 'end_date', 'reason', 'attachment_path', 'status', 'reviewed_by', 'reviewed_at', 'review_notes', 'overlap_group_id', 'overlap_state'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date', 'reviewed_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::deleting(function (): void {
            throw new LogicException('Leave history is retained and cannot be deleted.');
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->leave_type) {
            'sick' => 'Sick Leave', 'vacation' => 'Vacation Leave',
            'emergency' => 'Emergency Leave', default => 'Other Leave',
        };
    }
}
