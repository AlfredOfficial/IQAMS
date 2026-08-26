<?php

namespace App\Services;

use App\Models\QrCredential;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class QrCredentialService
{
    public function issue(User $user, ?User $administrator = null): QrCredential
    {
        $code = 'IQAMS-'.Str::random(43);

        return QrCredential::create([
            'user_id' => $user->id,
            'code_hash' => hash('sha256', $code),
            'encrypted_code' => Crypt::encryptString($code),
            'status' => 'active',
            'issued_by' => $administrator?->id,
            'issued_at' => now(),
        ]);
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

    public function regenerate(User $user, User $administrator): QrCredential
    {
        QrCredential::where('user_id', $user->id)->where('status', 'active')->update([
            'status' => 'revoked', 'revoked_by' => $administrator->id, 'revoked_at' => now(),
        ]);

        return $this->issue($user, $administrator);
    }
}
