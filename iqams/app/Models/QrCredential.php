<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QrCredential extends Model
{
    protected $fillable = ['user_id', 'code_hash', 'encrypted_code', 'status', 'issued_by', 'revoked_by', 'issued_at', 'revoked_at'];

    protected function casts(): array
    {
        return ['issued_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
