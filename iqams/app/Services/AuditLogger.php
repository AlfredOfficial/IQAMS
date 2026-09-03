<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    private const MAX_METADATA_BYTES = 16384;

    private const SENSITIVE_KEY_FRAGMENTS = [
        'password',
        'token',
        'secret',
        'credential',
        'code',
        'qr_code',
        'code_hash',
        'encrypted_code',
        'remember_token',
    ];

    public function record(
        string $action,
        ?Model $subject = null,
        array $metadata = [],
        ?Model $actor = null,
        ?Request $request = null,
    ): AuditLog {
        $consoleEvent = $request === null && app()->runningInConsole();
        $request ??= $consoleEvent ? null : request();
        $actor ??= Auth::user();

        $metadata = $this->sanitize($metadata);
        if ($consoleEvent) {
            $metadata['source'] ??= 'console';
        }
        $metadata = $this->limitMetadata($metadata);

        return AuditLog::create([
            'actor_id' => $actor?->getKey(),
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'route_name' => $request?->route()?->getName(),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }

    private function sanitize(array $metadata): array
    {
        $sanitized = [];

        foreach ($metadata as $key => $value) {
            $key = (string) $key;
            $normalizedKey = strtolower(str_replace(['-', ' '], '_', $key));

            if (Arr::first(self::SENSITIVE_KEY_FRAGMENTS, fn (string $fragment) => str_contains($normalizedKey, $fragment))) {
                continue;
            }

            if (is_object($value)) {
                $value = method_exists($value, 'toArray') ? $value->toArray() : '[object]';
            }

            if (is_array($value)) {
                $value = $this->sanitize($value);
            } elseif (is_resource($value)) {
                $value = '[resource]';
            }

            $sanitized[$key] = is_string($value) ? mb_substr($value, 0, 1000) : $value;
        }

        return $sanitized;
    }

    private function limitMetadata(array $metadata): array
    {
        if (strlen((string) json_encode($metadata, JSON_UNESCAPED_SLASHES)) <= self::MAX_METADATA_BYTES) {
            return $metadata;
        }

        $metadata['metadata_truncated'] = true;

        while (strlen((string) json_encode($metadata, JSON_UNESCAPED_SLASHES)) > self::MAX_METADATA_BYTES) {
            $keys = array_values(array_filter(array_keys($metadata), fn ($key) => $key !== 'metadata_truncated'));
            $removable = end($keys);

            if ($removable === false) {
                break;
            }

            unset($metadata[$removable]);
            $metadata['metadata_truncated'] = true;
        }

        return $metadata;
    }
}
