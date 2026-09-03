# IQAMS production deployment runbook

This runbook targets Linux with Nginx, PHP-FPM, MySQL/MariaDB, Redis, and an
SMTP provider. It is intentionally written so production credentials and
personal data stay outside the repository.

## Required infrastructure

- PHP 8.4 FPM with the extensions required by Composer and `pdo_mysql`.
- MySQL/MariaDB with TLS enabled where supported by the provider.
- Redis with separate logical databases for application data and cache.
- SMTP over TLS, normally port 587, with a verified sender identity.
- Nginx serving the application `public/` directory.
- A secret manager or deployment agent that supplies the production environment.
- A process supervisor for PHP-FPM, the queue worker, and the scheduler timer.

The production environment contract is in `.env.production.example`. The
local `.env` and `.env.example` remain development-oriented and must not be
overwritten as part of this deployment.

## Secrets and environment injection

Render the environment from the secret manager to a server-local path such as
`/etc/iqams/iqams.env`, or inject the same variables directly into PHP-FPM and
the systemd services. If a file is used, it must be root-owned, mode `0640`,
and readable only by the application service account and the deployment
operator.

The secret manager must provide at least:

- `APP_KEY`
- `DB_PASSWORD`
- `REDIS_PASSWORD`, when Redis authentication is enabled
- `MAIL_USERNAME` and `MAIL_PASSWORD`

`APP_KEY` is long-lived encryption material. Generate it once in a trusted
environment if it is missing, then preserve it. Never rotate it during a
routine deployment.

## Phase 1 safety baseline

Complete these steps before changing the production runtime configuration or
running any data migration.

### Database backup

Create both a provider/database snapshot and an encrypted logical dump. Use a
read-only backup credential or a server-local MySQL option file supplied by the
secret manager; do not put a password in the command line.

```bash
set -euo pipefail
umask 077

backup_dir=/var/backups/iqams
mkdir -p "$backup_dir"
timestamp=$(date -u +%Y%m%dT%H%M%SZ)

mysqldump \
  --defaults-extra-file=/run/secrets/iqams-mysql.cnf \
  --single-transaction \
  --routines \
  --triggers \
  --hex-blob \
  --no-tablespaces \
  --databases "$DB_DATABASE" \
  | gzip -9 \
  | age --encrypt --recipient "$BACKUP_AGE_RECIPIENT" \
      -o "$backup_dir/iqams-$timestamp.sql.gz.age"
```

Verify the encrypted dump by restoring it into an isolated MySQL/MariaDB
database. Never restore it over production as a verification step.

```bash
age --decrypt /var/backups/iqams/iqams-<timestamp>.sql.gz.age \
  | gzip -dc \
  | mysql --defaults-extra-file=/run/secrets/iqams-restore.cnf
```

Retain the snapshot, dump, restore log, and verification result according to
the organization’s backup policy. Keep all artifacts outside the repository.

### Sensitive baseline exports

Use read-only `mysql --batch --raw` queries or an approved SQL export tool to
write three encrypted artifacts outside the repository:

1. `role-mappings.tsv`: user ID, username, status, legacy role, Spatie role,
   and profile type. Do not include passwords or password hashes.
2. `active-accounts.tsv`: active account IDs, usernames, names, email
   addresses, roles, and profile identifiers. Treat this as confidential.
3. `qr-usage.tsv`: QR credential ID, user ID, status, issue/revocation
   timestamps, credential type, and scan-audit outcome counts. Do not export
   `code_hash`, `encrypted_code`, or any plaintext QR value.

Encrypt each artifact with the approved operator/public backup key, record its
checksum, and store it under the restricted backup location. Remove temporary
plaintext files after encryption and verification.

Run the existing read-only reconciliation command:

```bash
php artisan roles:reconcile
```

Stop the rollout if it reports a missing, multiple, or mismatched role.

### Data policy baseline

Record these approved rules before enabling production administration:

- Only instructors and staff may submit or cancel leave requests.
- User accounts are deactivated rather than hard-deleted.
- Attendance and leave history are preserved until an institutional retention
  period is formally approved.
- USB/Bluetooth plug-and-play QR scanners are the approved attendance capture
  hardware; webcam and mobile-camera scanning are not part of the IQAMS
  deployment contract.
- QR credentials remain random, encrypted, and associated with the user
  account; the credential value is not an ID number.
- Non-teaching personnel final report statuses are Present, Absent, and On
  Leave. Partial attendance is reported as Present with a completeness detail.
- Student attendance rate is (Present + Late) divided by (Present + Late +
  Absent); Excused, On Leave, cancelled, and non-scheduled sessions are
  excluded.

### Backup operations ownership

Database backup and restore are external administrator/operations procedures,
not IQAMS browser features. The operations owner must schedule encrypted
logical dumps and provider snapshots, retain them according to institutional
policy, monitor failures, and perform a restore into an isolated database at
least once per retention cycle. Record the backup timestamp, checksum, restore
verification result, and responsible operator outside the application. Never
restore a production database as a smoke test.

These rules are a policy baseline. Enforcing the leave-role restriction and
preserving audit, attendance, and leave history are application invariants.

The default operations schedule is:

- Provider snapshots run daily before the maintenance window.
- Encrypted logical dumps run nightly after attendance queues have drained.
- Retain 30 daily dumps and 12 monthly snapshots, unless the institution's
  approved retention schedule is longer.
- Encrypt dumps with the approved `age` recipient and protect storage with the
  provider's encryption-at-rest controls; use TLS for database connections.
- Store artifacts only in the restricted backup vault or `/var/backups/iqams`
  on the designated operations host, never in the web root or repository.
- Only named database administrators and authorized operations/release
  operators may create, inspect, or restore backups.
- Restore by decrypting into a newly isolated database, applying the restore
  operator's option file, and verifying migrations, row counts, login, QR
  resolution, and attendance reports before discarding the isolated database.
- Monitor both snapshot and dump jobs for failures and page the operations
  owner when a backup or restore-verification cycle is missed.

## Phase 2 identity and authorization hardening

Apply the additive password-reset and audit migrations after the approved
snapshot and encrypted dump have been verified. Run the permission seeder (or
the audit-permission migration) so the administrator receives
`view-audit-logs`.

New student, instructor, and staff accounts receive a random unusable initial
password and a one-time reset invitation. The invitation is queued only after
the account transaction commits. Existing active human accounts must be
reviewed with:

```bash
php artisan accounts:require-password-reset --dry-run
php artisan accounts:require-password-reset --send
```

The reset broker uses the existing `password_reset_tokens` table and the
60-minute expiry configured in the production environment. Password resets
and authenticated password changes clear the forced-reset state. Do not copy
reset links, passwords, password hashes, or QR values into tickets, logs, or
deployment output.

QR credentials are generated and returned only by the authenticated private
ID-card endpoint. To rotate credentials, review the count first and then run
one controlled selector:

```bash
php artisan qr:rotate --dry-run --all
php artisan qr:rotate --all
php artisan qr:rotate --role=student
php artisan qr:rotate --user=123
```

The rotation command prints counts only. It immediately revokes active
credentials inside a transaction before issuing replacements. Legacy QR
fallback must remain enabled until credential coverage is verified and a
separate cutoff is approved.

Authorization reads use Spatie roles. `users.role_id` remains a populated,
one-way compatibility mirror for this release and must not be used as an
authorization source. Run the read-only deployment gate before rollout:

```bash
php artisan roles:reconcile
```

Administrator role, status, and deletion operations lock the relevant active
administrator rows and preserve the final-administrator invariant. Persistent
administrator mutations require Laravel password confirmation, configured for
15 minutes by `AUTH_PASSWORD_TIMEOUT=900`. Review the append-only audit trail
at `/admin/audit-logs`; records are retained with attendance and leave history
until an institutional retention period is approved.

## Release and configuration sequence

Run the following from an immutable release directory, not from a working copy
containing secrets:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
npm ci
npm run build
php artisan storage:link
php artisan migrate:status
php artisan optimize
```

Do not run `composer update`, `npm update`, or migrations as part of this
configuration rollout. Migrations require a separate approved change window.

Before `optimize`, make sure the secret manager environment is visible to the
PHP CLI and PHP-FPM. The optimized cache embeds the resolved configuration.

## Process supervision

Install the templates under `deploy/` using the actual release path, PHP-FPM
socket, host name, TLS certificate paths, and secret-manager environment path.

After installing the rendered unit files, reload systemd and enable the
continuous worker plus the scheduler and health timers:

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now iqams-queue.service iqams-schedule.timer iqams-health.timer
sudo systemctl status iqams-queue.service iqams-schedule.timer iqams-health.timer --no-pager
```

During a release, ask the running worker to finish its current job before the
new release is restarted:

```bash
php artisan queue:restart
sudo systemctl restart iqams-queue.service
```

The queue worker must run:

```text
php artisan queue:work redis --sleep=3 --tries=3 --timeout=90 --max-time=3600
```

The scheduler timer must invoke `php artisan schedule:run` once per minute.
Install and enable `iqams-health.service` and `iqams-health.timer` as an
independent monitor. The scheduler writes a shared-cache heartbeat and the
queue worker processes a heartbeat job every minute; `php artisan ops:health`
returns a non-zero exit code when either heartbeat is stale, or when database
or cache connectivity fails. The health timer should write to journald and be
connected to the host's normal service-alerting path.

The scheduled attendance commands and report-export cleanup use distributed
Redis-backed scheduler locks. Enable the cache-backed maintenance mode in the
production environment so every application instance observes the same state.
The scheduled attendance commands can write attendance rows, so do not use an
uncontrolled production `schedule:run` invocation as a smoke test.

## Verification checklist

- `php artisan about` reports `production` and debug disabled.
- `php artisan migrate:status` reports no unexpected pending migrations.
- HTTPS responses contain `Secure`, `HttpOnly`, and `SameSite=Lax` session
  cookie attributes.
- Login and password-reset mail reach an approved test mailbox.
- Redis cache, session storage, queue processing, scanner throttling, and
  scheduler locks work from separate processes.
- A disposable queue job is processed and a failure is visible in monitoring.
- `php artisan ops:health` reports healthy database, Redis, scheduler, and
  queue-worker checks after the heartbeat cycle has run.
- Queued daily personnel exports complete into private storage and expire after
  the configured 24-hour artifact lifetime.
- `php artisan schedule:list` shows the attendance, heartbeat, and cleanup commands.
- Application and worker logs arrive through stderr/systemd journal collection.
- Nginx serves only `public/`; private application storage is not web-readable.
- The encrypted database dump restores successfully in isolation.
- No secrets, plaintext QR values, passwords, or personal-data exports appear
  in Git, deployment logs, or command output.

Run application smoke tests in staging first, then use one approved production
test account and mailbox. The full automated suite must pass before release;
as of this implementation it passes 259 tests and 1,102 assertions.

## Rollback

For a configuration-only rollback:

1. Stop the new queue worker and scheduler timer.
2. Stop the health monitor timer.
3. Restore the previous release and environment binding.
4. Restart PHP-FPM and the previous worker configuration.
5. Do not rotate `APP_KEY`.
6. Do not use destructive migration rollback commands.
7. Restore database data only if a separate approved data transformation was
   performed and the restore decision is authorized.
