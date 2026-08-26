<?php

namespace App\Services;

use App\Models\AttendanceScanAudit;
use App\Models\SecurityFlag;
use Illuminate\Http\Request;

class ScanSecurityService
{
    public function audit(Request $request, string $outcome, array $data = []): AttendanceScanAudit
    {
        $terminal = $request->attributes->get('scanner_terminal');
        $audit = AttendanceScanAudit::create([
            'user_id' => $data['user_id'] ?? null, 'admin_id' => $request->user()?->id,
            'scanner_terminal_id' => $terminal?->id, 'attendance_log_id' => $data['attendance_log_id'] ?? null,
            'outcome' => $outcome, 'failure_category' => $data['failure_category'] ?? null,
            'credential_type' => $data['credential_type'] ?? null, 'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000), 'location' => $terminal?->location,
            'metadata' => $data['metadata'] ?? null,
        ]);
        $this->detect($audit);

        return $audit;
    }

    private function detect(AttendanceScanAudit $audit): void
    {
        if (in_array($audit->outcome, ['invalid', 'revoked', 'replay'], true)) {
            $count = AttendanceScanAudit::where('scanner_terminal_id', $audit->scanner_terminal_id)
                ->whereIn('outcome', ['invalid', 'revoked', 'replay'])->where('created_at', '>=', now()->subMinute())->count();
            if ($count >= config('attendance.invalid_scan_threshold', 5)) {
                $this->flag($audit, 'high', 'repeated_invalid_scans', "{$count} invalid scans occurred within one minute.");
            }
        }
        if ($audit->outcome === 'cancelled' && $audit->user_id) {
            $count = AttendanceScanAudit::where('user_id', $audit->user_id)->where('outcome', 'cancelled')->where('created_at', '>=', now()->subMinutes(15))->count();
            if ($count >= 3) {
                $this->flag($audit, 'medium', 'repeated_identity_mismatch', "{$count} identity previews were cancelled within 15 minutes.");
            }
        }
        if ($audit->outcome === 'previewed' && $audit->user_id) {
            $other = AttendanceScanAudit::where('user_id', $audit->user_id)->where('outcome', 'previewed')->where('scanner_terminal_id', '!=', $audit->scanner_terminal_id)->where('created_at', '>=', now()->subMinutes(10))->exists();
            if ($other) {
                $this->flag($audit, 'high', 'multiple_terminal_use', 'The same credential was presented at different terminals within 10 minutes.');
            }
        }
    }

    private function flag(AttendanceScanAudit $audit, string $severity, string $category, string $evidence): void
    {
        $key = $category.':'.($audit->user_id ?? 'none').':'.($audit->scanner_terminal_id ?? 'none').':'.now()->format('YmdHi');
        SecurityFlag::firstOrCreate(['deduplication_key' => $key, 'status' => 'open'], [
            'severity' => $severity, 'category' => $category, 'user_id' => $audit->user_id, 'admin_id' => $audit->admin_id,
            'scanner_terminal_id' => $audit->scanner_terminal_id, 'attendance_scan_audit_id' => $audit->id,
            'evidence' => $evidence, 'detected_at' => now(),
        ]);
    }
}
