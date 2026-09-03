<?php

namespace App\Services;

use App\Models\Instructor;
use App\Models\NonTeachingStaff;
use App\Models\QrCredential;
use App\Models\Student;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class QrIdentityResolver
{
    public function resolve(string $qrCode): User
    {
        return $this->resolveWithMetadata($qrCode)['user'];
    }

    public function resolveWithMetadata(string $qrCode): array
    {
        $credential = QrCredential::with('user.roles')->where('code_hash', hash('sha256', $qrCode))->first();

        if ($credential) {
            if ($credential->status !== 'active') {
                throw ValidationException::withMessages(['qr_code' => 'This QR credential has been revoked.']);
            }

            if (! $credential->user) {
                throw ValidationException::withMessages(['qr_code' => 'This QR credential is not linked to a user.']);
            }

            return ['user' => $credential->user, 'credential' => $credential, 'is_legacy' => false];
        }

        if (! $this->legacyAllowed()) {
            throw ValidationException::withMessages(['qr_code' => 'This legacy QR card is no longer accepted. Request a replacement ID card.']);
        }

        $profiles = collect([
            Student::with(['user.roles', 'user.student'])->where('qr_code', $qrCode)->first(),
            Instructor::with(['user.roles', 'user.instructor'])->where('qr_code', $qrCode)->first(),
            NonTeachingStaff::with(['user.roles', 'user.nonTeachingStaff'])->where('qr_code', $qrCode)->first(),
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

        $expectedRole = match (true) {
            $profile instanceof Student => 'student',
            $profile instanceof Instructor => 'instructor',
            $profile instanceof NonTeachingStaff => 'staff',
        };

        if ($user->primaryRoleName() !== $expectedRole) {
            throw ValidationException::withMessages([
                'qr_code' => 'Attendance is denied because this user has an invalid or mismatched role.',
            ]);
        }

        return ['user' => $user, 'credential' => null, 'is_legacy' => true];
    }

    private function legacyAllowed(): bool
    {
        $cutoff = config('attendance.legacy_qr_cutoff');

        return ! $cutoff || now()->lessThanOrEqualTo($cutoff);
    }
}
