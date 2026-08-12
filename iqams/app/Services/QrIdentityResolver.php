<?php

namespace App\Services;

use App\Models\Instructor;
use App\Models\NonTeachingStaff;
use App\Models\Student;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class QrIdentityResolver
{
    public function resolve(string $qrCode): User
    {
        $profiles = collect([
            Student::with(['user.role', 'user.student'])->where('qr_code', $qrCode)->first(),
            Instructor::with(['user.role', 'user.instructor'])->where('qr_code', $qrCode)->first(),
            NonTeachingStaff::with(['user.role', 'user.nonTeachingStaff'])->where('qr_code', $qrCode)->first(),
        ])->filter();

        if ($profiles->isEmpty()) {
            throw ValidationException::withMessages([
                'qr_code' => 'This QR code is not assigned to any user.',
            ]);
        }

        if ($profiles->count() > 1) {
            throw ValidationException::withMessages([
                'qr_code' => 'This QR code matches multiple profiles. Ask an administrator to correct the duplicate QR value.',
            ]);
        }

        $profile = $profiles->first();
        $user = $profile->user;

        if (! $user) {
            throw ValidationException::withMessages([
                'qr_code' => 'This QR code is registered but is not linked to a user account.',
            ]);
        }

        if ($user->status !== 'active') {
            throw ValidationException::withMessages([
                'qr_code' => 'Attendance is denied because this account is inactive.',
            ]);
        }

        $expectedRole = match (true) {
            $profile instanceof Student => 'student',
            $profile instanceof Instructor => 'instructor',
            $profile instanceof NonTeachingStaff => 'staff',
        };

        if ($user->role?->role_name !== $expectedRole) {
            throw ValidationException::withMessages([
                'qr_code' => 'Attendance is denied because this user has an invalid or mismatched role.',
            ]);
        }

        return $user;
    }
}
