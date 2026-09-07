# Email account setup and password resets

New student (regular or irregular), instructor and staff accounts receive a private email link to choose a password. IQAMS stores an undisclosed random password until setup succeeds; administrators never receive a temporary password. Usernames remain the student/employee identifier.

The existing admin reset action now queues a link and immediately replaces the previous password, rotates the remembered-login token, increments the session version and deletes earlier setup/reset tokens. Existing sessions are rejected on their next request. The account must use its email link to regain access. Inactive accounts cannot receive or consume links.

## Link behavior

- Links expire 60 minutes after issuance by the worker (`AUTH_PASSWORD_RESET_EXPIRE=60`). Expiration starts when the email job runs, not when it is queued.
- An administrator-entered email is unverified. Opening a link does not change the account; successful password submission verifies access to that mailbox, clears the forced-password-change flag and redirects to login.
- Token issuance and consumption lock the same user row. Only one concurrent submission can succeed; the token is deleted in the password-update transaction.
- “Forgot password?” also supports first setup and expired invitations. Valid email inputs receive the same generic queued response whether or not an active account exists. Existing request and broker throttles remain in effect.
- A successfully issued new link replaces the previous link. A throttled request leaves the existing link usable. Failed transport submission rolls back the attempted token replacement; a retry may then issue a new link. SMTP acceptance followed by a lost acknowledgement can still result in duplicate emails; only the currently stored token remains valid.
- Successful setup/reset increments the session version again and rotates the remembered-login token. An old queued job with a previous version is skipped, preventing it from replacing a link after a later reset or completed setup.
- Session-version zero is compatible with existing sessions that have no stamp. Accounts are not reset or emailed automatically by the migration. Existing legacy forced-password-change behavior remains available until an admin reset transitions that account.

## Deployment sequence

1. Apply the additive `2026_09_07_000000_add_session_version_to_users_table.php` migration before serving the new code. It adds unsigned `session_version` with default zero; it does not change passwords or send emails. Use the normal backed-up deployment process from [production-deployment.md](production-deployment.md).
2. Configure a reachable HTTPS `APP_URL`, the mail transport and a real asynchronous queue (`database` for a single-host installation or the existing production Redis configuration). Do not use `sync` for live invitation delivery: it removes worker retries and makes mail failures part of the HTTP request.
3. Restart queue workers with the new code and configuration. Confirm the configured worker is consuming the same connection/queue used by the application.
4. Verify one designated test account and mailbox before enabling account creation/reset for real users. No automatic batch-reset command is part of this rollout.

The implementation preserves the local `.env`. Its current log mailer is **not** real delivery. Setup/reset jobs reject the log transport, including a failover transport containing log, before generating a link. This prevents private links from being written into application logs. Use the array transport in automated tests or a local SMTP capture service for manual development checks.

## SMTP settings

Set these through the deployment environment or secret manager; do not commit actual credentials:

```dotenv
APP_URL=https://your-iqams-host.example
MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=your-smtp-host.example
MAIL_PORT=587
MAIL_REQUIRE_TLS=true
MAIL_TIMEOUT=30
MAIL_USERNAME=provided-by-your-mail-provider
MAIL_PASSWORD=provided-by-your-secret-manager
MAIL_EHLO_DOMAIN=your-iqams-host.example
MAIL_FROM_ADDRESS=your-approved-sender@example.com
MAIL_FROM_NAME=IQAMS
AUTH_PASSWORD_RESET_EXPIRE=60
QUEUE_CONNECTION=database
```

Use the provider-approved sender address and authentication method. Laravel's installed Symfony transport accepts `smtp` (STARTTLS) or `smtps` (implicit TLS, commonly port 465); `tls` is not a supported scheme. The production example now uses `smtp` with required TLS. The 30-second transport timeout bounds individual SMTP operations while the user row is locked; keep the worker timeout and queue reservation appropriately larger. The existing production Redis worker remains applicable.

`MAIL_URL`, when supplied, can override separate SMTP fields; avoid conflicting settings. Test the exact deployed configuration and worker environment. The link host must be reachable from the recipient's device; a developer loopback URL will not work for a remote user.

## Delivery monitoring

The UI states **queued**, not delivered. `SendPasswordResetLink` has three attempts with 30/120-second backoff. Sanitized `account.password_link_delivery` audit outcomes are `submitted_to_transport`, `throttled`, `inactive_or_missing`, `superseded`, `attempt_failed`, and `failed`. Transport submission does not prove inbox delivery. Investigate failures in normal queue monitoring and provider delivery/bounce tools.

Delivery exceptions are replaced with a generic message without the original transport exception, which may contain a URL or message body. Passwords and tokens are excluded from flash input and audit metadata. Reset pages have no-store and no-referrer headers. Configure web-server/proxy access logging and error monitoring to redact reset-token URL paths and email query strings; access logs exist outside the application logger.

After fixing configuration, retry a specific failed delivery job or request another link. Do not change account passwords again merely because a delivery attempt failed. An administrator can use the existing reset action when a deliberate new reset is needed.

## Captured-email verification

Automated tests use SQLite memory, fake filesystem disks, array sessions/cache/mail and isolated compiled views. The array-transport test renders the actual notification into memory without SMTP or external messages:

```powershell
$env:APP_ENV='testing'
$env:APP_CONFIG_CACHE='bootstrap/cache/config.testing.php'
$env:DB_CONNECTION='sqlite'
$env:DB_DATABASE=':memory:'
$env:DB_URL='(null)'
$env:CACHE_STORE='array'
$env:SESSION_DRIVER='array'
$env:MAIL_MAILER='array'
$env:QUEUE_CONNECTION='sync'
php vendor/phpunit/phpunit/phpunit --do-not-cache-result --filter 'EmailPasswordSetupTest|AccountInvitationTest|PasswordResetTest|Phase2IdentityAuthorizationTest'
```

For a manual local check, use an isolated application/database and an SMTP capture service on loopback, with no real recipients or relay. Point `MAIL_HOST`/`MAIL_PORT` at that service, use `MAIL_SCHEME=smtp`, and allow `MAIL_REQUIRE_TLS=false` only for that local capture environment. Run its database queue worker, create a disposable account and inspect the captured IQAMS email. Confirm username, reachable setup button, expiry and resend instructions. Submit the link, log in with the selected password, and verify reuse fails. Restart any worker after changing its environment.

## Concurrent token regression

`tests/Integration/PasswordSetupConcurrencyTest.php` is opt-in and is not part of the default SQLite suite. It starts two PHP consumers against one committed token. Exactly one must succeed, the chosen password must match the winner, the token must be gone, and `session_version` must increment once.

Initialize a separate MariaDB instance/data directory bound only to `127.0.0.1:13317`; create database `iqams_password_setup_test`. The test **recreates that schema** and refuses any other host/port/database. Never point it at an application database. Set the isolation values above, then override:

```powershell
$env:DB_CONNECTION='mysql'
$env:DB_HOST='127.0.0.1'
$env:DB_PORT='13317'
$env:DB_DATABASE='iqams_password_setup_test'
$env:DB_USERNAME='root'
$env:DB_PASSWORD='(empty)'
php vendor/phpunit/phpunit/phpunit --do-not-cache-result tests/Integration/PasswordSetupConcurrencyTest.php
```

The blank password applies only to this disposable loopback fixture. `(empty)` prevents Windows PowerShell from deleting the environment variable and falling back to local `.env` credentials. Child processes receive synthetic token input over stdin, never command-line arguments or printed output. Shut down the disposable server after testing.

No real SMTP configuration or delivery, mass email, existing-account password reset, or production migration is performed by the automated checks.

## Implementation verification

Verification used disposable data and captured mail, with the installed PHP 8.4.11 / Laravel 13.21.1 runtime:

- Full PHP suite: 306 tests, 304 passed, 1,400 assertions (107.995 seconds). The two pre-existing failures are `LeaveRequestTest::test_approved_multi_day_leave_counts_every_date_and_is_not_absent` (expected 3, actual 2) and `LeaveRequestTest::test_leave_spanning_months_only_counts_dates_inside_selected_month` (expected 2, actual 0). Both concern weekend leave counting, outside this change.
- A subsequently added older-queue-payload compatibility regression passed separately: 1 test, 2 assertions.
- Two simultaneous token consumers against isolated MariaDB: 1 test, 13 assertions, passed. The disposable database server was shut down afterward.
- JavaScript suite: all 9 tests passed.
- Production asset build passed using `npm.cmd run build -- --outDir storage/framework/testing/password-setup-build`; the served asset directory was left intact.

The PHP coverage includes all three account roles, regular and irregular enrollment, transaction rollback and after-commit dispatch, captured rendered email, token expiry/replacement/reuse, inactive accounts, session and remembered-login revocation, throttling, sanitized failures and retry behavior. Real-provider delivery and browser-based mailbox interaction remain rollout checks.
