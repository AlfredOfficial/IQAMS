<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceScanAudit extends Model
{
    protected $fillable = ['user_id', 'admin_id', 'scanner_terminal_id', 'attendance_log_id', 'outcome', 'failure_category', 'credential_type', 'ip_address', 'user_agent', 'location', 'metadata'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function scannerTerminal(): BelongsTo
    {
        return $this->belongsTo(ScannerTerminal::class);
    }
}
