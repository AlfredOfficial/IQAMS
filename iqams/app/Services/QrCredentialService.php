<?php

namespace App\Services;

use App\Models\QrCredential;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class QrCredentialService
{
    public function issueIfMissing(User $user, ?User $administrator = null): ?QrCredential
    {
        return DB::transaction(function () use ($user, $administrator): ?QrCredential {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($lockedUser->qrCredentials()->where('status', 'active')->exists()) {
                return null;
            }

            return $this->issue($lockedUser, $administrator);
        });
    }

    public function issue(User $user, ?User $administrator = null): QrCredential
    {
        $this->assertEligible($user);

        do {
            $code = 'IQAMS-'.Str::random(43);
            $hash = hash('sha256', $code);
        } while (QrCredential::where('code_hash', $hash)->exists());

        $credential = QrCredential::create([
            'user_id' => $user->id,
            'code_hash' => $hash,
            'encrypted_code' => Crypt::encryptString($code),
            'status' => 'active',
            'issued_by' => $administrator?->id,
            'issued_at' => now(),
        ]);

        app(AuditLogger::class)->record('qr.issued', $user, [
            'status' => 'active',
            'issued_by' => $administrator?->id,
        ], $administrator);

        return $credential;
    }

    public function activeFor(User $user): QrCredential
    {
        return QrCredential::where('user_id', $user->id)->where('status', 'active')->latest('id')->first()
            ?? $this->issue($user);
    }

    public function plainText(QrCredential $credential): string
    {
        return Crypt::decryptString($credential->encrypted_code);
    }

    public function regenerate(User $user, ?User $administrator = null): QrCredential
    {
        return DB::transaction(function () use ($user, $administrator) {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            $this->assertEligible($lockedUser);

            $activeCredentials = QrCredential::query()
                ->where('user_id', $lockedUser->id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->get();

            if ($activeCredentials->isNotEmpty()) {
                QrCredential::query()
                    ->whereKey($activeCredentials->modelKeys())
                    ->update([
                        'status' => 'revoked',
                        'revoked_by' => $administrator?->id,
                        'revoked_at' => now(),
                    ]);

                app(AuditLogger::class)->record('qr.revoked', $lockedUser, [
                    'count' => $activeCredentials->count(),
                    'reason' => 'regeneration',
                ], $administrator);
            }

            $replacement = $this->issue($lockedUser, $administrator);

            app(AuditLogger::class)->record('qr.regenerated', $lockedUser, [
                'revoked_count' => $activeCredentials->count(),
            ], $administrator);

            return $replacement;
        });
    }

    private function assertEligible(User $user): void
    {
        if (! $user->isAccountActive() || ! $user->hasAnyRole(['student', 'instructor', 'staff'])) {
            throw ValidationException::withMessages([
                'qr_code' => 'Only active student, instructor, and staff accounts may receive QR credentials.',
            ]);
        }
    }
}
