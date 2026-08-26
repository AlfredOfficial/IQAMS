<?php

namespace App\Http\Middleware;

use App\Services\ScanSecurityService;
use Closure;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class ThrottleScannerRequests
{
    public function __construct(private ScanSecurityService $security) {}

    public function handle(Request $request, Closure $next, string $action): Response
    {
        $limit = (int) config("attendance.scanner_{$action}_rate", 60);
        $terminal = $request->attributes->get('scanner_terminal');
        $keys = [
            "scanner:{$action}:admin:".$request->user()->id,
            "scanner:{$action}:terminal:".$terminal->id,
            "scanner:{$action}:ip:".hash('sha256', (string) $request->ip()),
        ];

        foreach ($keys as $key) {
            if (RateLimiter::tooManyAttempts($key, $limit)) {
                $retryAfter = RateLimiter::availableIn($key);
                $this->security->audit($request, 'rate_limited', [
                    'failure_category' => "{$action}_rate_limit",
                    'metadata' => ['retry_after_seconds' => $retryAfter],
                ]);
                throw new HttpResponseException(response()->json([
                    'message' => 'Too many scanner requests. Please wait before trying again.',
                ], 429, ['Retry-After' => $retryAfter]));
            }
        }

        foreach ($keys as $key) {
            RateLimiter::hit($key, 60);
        }

        return $next($request);
    }
}
