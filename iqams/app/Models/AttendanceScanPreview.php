<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceScanPreview extends Model
{
    protected $fillable = ['token_hash', 'user_id', 'admin_id', 'scanner_terminal_id', 'encrypted_qr_value', 'is_legacy', 'expires_at', 'consumed_at', 'cancelled_at'];

    protected function casts(): array
    {
        return ['is_legacy' => 'boolean', 'expires_at' => 'datetime', 'consumed_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }
}
