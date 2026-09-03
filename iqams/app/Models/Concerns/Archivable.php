<?php

namespace App\Models\Concerns;

use LogicException;

trait Archivable
{
    protected static function bootArchivable(): void
    {
        static::deleting(function (): void {
            throw new LogicException('This reference record is archived instead of deleted.');
        });
    }
}
