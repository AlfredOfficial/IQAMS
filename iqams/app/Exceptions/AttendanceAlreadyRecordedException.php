<?php

namespace App\Exceptions;

use App\Models\AttendanceLog;
use Illuminate\Validation\ValidationException;

class AttendanceAlreadyRecordedException extends ValidationException
{
    public AttendanceLog $attendanceLog;

    public static function forLog(AttendanceLog $attendanceLog, string $message): static
    {
        /** @var static $exception */
        $exception = static::withMessages(['qr_code' => $message]);
        $exception->attendanceLog = $attendanceLog;

        return $exception;
    }
}
