<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SecurityFlag extends Model
{
    protected $fillable = ['severity', 'category', 'user_id', 'admin_id', 'scanner_terminal_id', 'attendance_scan_audit_id', 'deduplication_key', 'evidence', 'status', 'detected_at', 'reviewed_by', 'reviewed_at'];

    protected function casts(): array
    {
        return ['detected_at' => 'datetime', 'reviewed_at' => 'datetime'];
    }
}
