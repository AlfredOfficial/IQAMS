<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScannerTerminal extends Model
{
    protected $fillable = ['name', 'location', 'is_active', 'last_used_at'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'last_used_at' => 'datetime'];
    }
}
