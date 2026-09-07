# Reliability and security remediation

Implemented September 7, 2026. This release follows the audit report in [full-system-audit.md](full-system-audit.md).

## Changes

- Leave approval, rejection, and cancellation now share a locked transition. It locks the owner and reloads the leave record before checking the pending state, conflicts, attendance and authorization. Notifications run only after commit.
- Personnel totals now show both **Approved leave (working days)**, which excludes weekends and other non-working dates, and **Approved leave (calendar days)**, which counts every approved date in the selected period.
- QR scans revalidate a credential, role and profile after the user row has been locked. Changing a portal role revokes active random QR credentials and clears legacy QR values, so old student cards cannot be reused.
- Export workers use a 120-second claim lease, claim-specific artifact path, 90-second timeout and 150-second reservation. A restarted job can reclaim expired work; stale writers cannot publish over a newer result. Export pruning also removes abandoned artifacts after the lease window.
- Leave-upload failures remove only the newly written attachment. Avatar writes now fail cleanly and delete partial primary/thumbnail files; update paths retain the old avatar until the replacement and database transaction succeed.
- Patched dependencies: Guzzle 7.15.5, CommonMark 2.10.0, PostCSS 8.5.28 and Nano ID 3.3.18. Excel names are explicitly written as strings, including formula-like prefixes.

## Deployment

Apply the additive export-lease migration together with the earlier `session_version` migration using the normal backed-up deployment process. Configure queue reservations above the worker timeout: `DB_QUEUE_RETRY_AFTER=150` or `REDIS_QUEUE_RETRY_AFTER=150`; the worker command should keep `--timeout=90`.

Restart workers after deployment. Do not run `migrate:fresh` on an application database.

## Verification and limits

Focused SQLite regression tests cover leave transition loss, leave totals, QR credential revocation, formula-safe Excel cells, retryable exports and avatar behavior. Tests use disposable databases and fake storage.

On September 7, 2026, the complete PHP suite passed: 315 tests and 2,452 assertions in 99.936 seconds. The nine JavaScript tests and a production Vite build also passed. Composer and npm audits reported no known locked dependency advisories; Composer platform requirements passed.

The report-rendering test needs more memory than the local PHP CLI default of 128 MB. The verified suite command used `php -d memory_limit=1024M`; set an appropriate CLI memory limit in CI rather than weakening or skipping the report test.

The in-app browser has no available browser binding in this environment. Physical scanner, browser/mobile portal, Redis queue and staging checks remain required before production certification. Simulated QR requests and database-queue tests do not replace those checks.
