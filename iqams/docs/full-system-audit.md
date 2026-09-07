# IQAMS full-system audit

Audit date: September 6, 2026 (Asia/Manila). Baseline commit: `60b23c9b7973c0e307b89afa14db3af1f5726f87`.

## Outcome

**The current checkout does not meet its documented release gate.** The existing PHP suite has two failures and one error. This audit also reproduced predictable temporary passwords, missing attendance entries in larger daily reports, stale leave transitions, role-inconsistent QR attendance, export recovery failures, spreadsheet formula interpretation, and two upload failure paths.

This is an audit and remediation backlog, not a remediation release. Application source, routes, schema definitions, dependencies, configuration, and existing attendance data were not changed. Only this report and supporting audit evidence were added under `docs/`. All database mutations used in-memory SQLite or a newly initialized, loopback-only MariaDB instance. Production readiness is not certified.

### Baseline and method

| Item | Observed value |
| --- | --- |
| Working tree before audit | Clean |
| Platform | Windows; local XAMPP PHP CLI |
| PHP / PHPUnit | 8.4.11 / 12.5.31 |
| Laravel | Locked and installed `v13.21.1`; manifest constraint `^13.8` |
| Composer / Node / Vite | 2.9.3 / 24.4.1 / 8.1.5 |
| Other locked PHP components | Spatie Permission 8.3.0; Dompdf 3.1.6; PhpSpreadsheet 5.9.0 |
| PHP platform requirements | `composer check-platform-reqs` passed |
| Available database drivers | PDO SQLite and PDO MySQL |
| OPcache / Redis extension | Neither loaded in the audited CLI runtime |
| Primary test isolation | SQLite `:memory:`, array cache/session/mail, synchronous queue, per-process compiled views, fake upload/export disks |
| Additional database | MariaDB 10.4.32, `127.0.0.1:13316`, database `iqams_audit`, new data directory under ignored `storage/framework/testing/audit-mariadb` |
| MariaDB lifecycle | Fresh initialization and all 42 migrations succeeded; audit instance shut down after testing |
| Browser | Browser skill setup completed, selection returned “No browser is available,” discovery returned `[]` |

Direct PHPUnit execution avoided Composer's `test` script, which clears configuration. The Vite build used a separate output directory, preserving the application's currently served assets. Test notifications were faked in new probes; existing tests used array mail. No live worker, scheduler, backup, reconciliation apply, or credential-rotation command was executed. The existing suite itself exercises migration/reconciliation behavior against its isolated database.

## Coverage and verification

“Assessed” means the stated code and/or automated checks were examined; it does not imply that every behavior passed.

| Area | Evidence and result | Remaining limitation |
| --- | --- | --- |
| Architecture and routes | Laravel/Blade/Alpine application; 138 route entries: 18 `admin.*`, 9 `instructor.*`, 10 `staff.*`, 11 `student.*`, 90 shared/auth/resource entries. Many admin-only resources do not have an `admin.` name prefix. | Not a complete endpoint-by-endpoint penetration test. |
| Authorization | Four-role portal matrix, permission-gated audit access, password confirmation, account deactivation, final-admin protection and legacy-role isolation covered by existing tests and middleware/service review. Admin is deliberately a super-role except for `view-audit-logs`. | Simultaneous final-admin changes and revocation during an in-flight scan were not stress-tested. See F04 for a reproduced role-change defect. |
| Cross-user records | Student history uses authenticated user ID; instructor class attendance checks schedule ownership; exports enforce requesting owner; leave cancellation checks owner; notification read operations use the authenticated user's relation. Existing ownership tests passed. | Browser/session behavior and proxy-level controls remain unverified. |
| Authentication | Login throttling, reset broker, forced password change, inactive-account rejection, reset state and audit tests assessed. | F01 contradicts the documented account-invitation contract. A forced password change does not establish who first used a predictable password. |
| QR attendance | Random credentials, hashing/encryption, revocation, legacy cutoff, server-side terminal selection/location, throttling, duplicate rejection and security flags assessed. Eight simultaneous MariaDB scans created one row. | No USB/Bluetooth scanner, Redis-backed multi-process throttle test, or physical terminal identity test. |
| Attendance rules | Existing boundary, overnight, personnel calculation, irregular enrollment, recurring schedule, class attendance, student summary and absence-warning tests passed. Student rate uses `(present + late) / (present + late + absent)`. | Weekend leave expectations conflict; see V01. Real institutional policy and semester boundaries were not independently supplied. |
| Leave | Owner-only cancellation, review validation, overlap prevention and attendance/leave exclusion assessed. Four concurrent identical submissions produced one row. | F03 demonstrates a separate transition race; submission uniqueness does not prevent it. |
| Integrity and retention | SQLite suite, fresh MariaDB migration, uniqueness/relationship constraints, canonical/voided history, archival guards and report/dry-run commands assessed. New report/dry-run probe preserved fixture counts. | No migration against a copy of populated legacy production data; direct SQL permissions and database-level immutability not verified. |
| Reports and exports | Existing report content, PDF signature, XLSX cell, private download ownership and expiration checks passed. Additional scale, formula and retry probes found F02, F05 and F06. | PDF visual layout, Excel desktop behavior and full 90-second worker termination/recovery were not exercised. |
| Uploads and sensitive data | MIME/size rules, image conversion, public avatars, local private leave files, owner-only exports, audit metadata filtering and model append-only guards assessed. `.env` is not tracked; only environment examples are tracked. | F07/F08 cover failure handling. This was targeted secret-exposure review, not a complete Git-history secret scan. |
| Navigation and usability | Nine JS tests passed for native navigation, history/form handling, hidden-tab polling, request cancellation and recovery. Server-rendered portal pages and admin destinations tested. Reviewed modal, scanner and chart markup. | **Blocked:** real-browser back/forward, mobile rendering, keyboard/focus, contrast, download interactions, visual empty/error states and screen-reader checks. |
| Performance | Existing query-bound/cache tests passed; 140 measured kernel requests on MariaDB, plus controlled concurrency and an 800-row correctness probe. See measurements below. | Small synthetic fixtures, no production web server, no network/render time, no sustained capacity test. |
| Operations | Reviewed Nginx public root/HTTPS/hidden-file protections, production cookie flags, queue/scheduler/health units, private export expiration, backup/restore and rollback runbook. Existing fake-heartbeat health test passed. | **Blocked:** live Linux/Nginx/PHP-FPM, Redis locks and retries, SMTP delivery, TLS headers, external alert routing, backup restore and real production settings. |
| Dependencies | Live Packagist and npm audit endpoints queried without installing/upgrading packages; affected locks confirmed. See D01. | Advisory presence is confirmed; application-specific exploitation was not demonstrated. |

### Fresh test results

| Check | Result |
| --- | --- |
| Existing complete PHP suite, SQLite | **288 tests: 285 passed, 2 failures, 1 error; 1,265 assertions; 130.373 seconds reported by runner** |
| Existing JS suite | **9/9 passed** |
| Vite production build, isolated output | Passed; 36 modules, 20.40 seconds; emitted manifest/CSS/JS |
| First nine audit probes, SQLite | 9/9 passed, 24 assertions |
| First nine audit probes, MariaDB | 9/9 passed, 24 assertions |
| Expanded audit probes, MariaDB | 11/11 passed, 44 assertions, 38.686 seconds; includes valid/missing enrollment payloads and request measurements |
| Additional MariaDB chunk-boundary probe | 1/1 passed, 3 assertions, 11.139 seconds; 200 staff and 800 attendance rows |
| Concurrent MariaDB scan writes | 8 processes: 1 created, 7 duplicate outcomes, 1 stored attendance row |
| Concurrent MariaDB leave submissions | 4 processes: 1 created, 3 validation rejections, 1 stored leave row |

The audit probes intentionally assert the **observed defective behavior** to make findings reproducible. Their passing status is not an application health claim. They are outside the normal test suites. The 12 final probe methods were exercised as an 11-test run plus one focused run, not a single 12-test run. Earlier failed probe attempts were corrected for a fixture missing its refreshed database-default `pending` status.

### Measurements

Fixture: 43 users (1 admin, 1 instructor, 1 student, 40 staff), one class schedule, 481 attendance rows (480 personnel scans over three dates and one student scan). Each case had one warm-up and 20 measured requests, concurrency 1. Timings use the Laravel HTTP test kernel with MariaDB, array cache/session, and no OPcache; browser rendering and HTTP transport are excluded. p50 is the mean of samples 10/11 after sorting; p95 is nearest-rank sample 19.

| Operation | p50 ms | p95 ms | Queries/request | Unexpected HTTP error rate |
| --- | ---: | ---: | ---: | ---: |
| Admin dashboard | 204.06 | 286.09 | 17 | 0% |
| Student dashboard | 26.26 | 50.39 | 9 | 0% |
| Instructor dashboard | 21.52 | 27.51 | 7 | 0% |
| Staff dashboard | 27.80 | 46.41 | 4 | 0% |
| People lookup | 5.84 | 10.27 | 2 | 0% |
| Daily personnel report page | 142.52 | 206.49 | 13 | 0% |
| Duplicate QR scan endpoint | 28.24 | 69.65 | 26 | 0% |

Duplicate scans return their expected business result with HTTP 200. This case does not measure fresh inserts. The separate synchronized service/controller race probes recorded scan times of 142.69–544.53 ms and leave times of 119.08–190.09 ms, with no unexpected exceptions. Their small, single-burst samples do not establish capacity or stable latency percentiles. Each scan process entered the attendance service at a shared wall-clock deadline; these were not browser requests and did not test shared throttling.

Existing query-growth tests cover bulk absence processing (including a 25-student fixture with at most eight queries), cache reuse/invalidation, bounded lookups and notification query reuse. The new scale probe demonstrates why passing small-fixture performance tests does not establish report correctness at chunk boundaries.

Raw measured values are retained in [benchmark-mariadb.json](audit-evidence/benchmark-mariadb.json).

## Confirmed application findings

Severity is contextual: **High** affects account confidentiality or materially incorrect attendance reporting; **Medium** affects workflow integrity/reliability with additional conditions; **Low** is limited failure handling/resource leakage. No production exploitation is claimed.

### F01 — High: initial and reset passwords are predictable from identifiers

- **Locations:** `app/Http/Controllers/StudentController.php:89`, `InstructorController.php:65`, `NonTeachingStaffController.php:67`, `UserAccountPasswordController.php:27` (password construction; inspect named symbols if line numbers move).
- **Reproduce:** Create a synthetic regular student with identifier `AUD-NEW`; its stored hash verifies against `Student@AUD-NEW`. Reset a synthetic staff account as admin; its hash verifies against `Staff@` plus its profile identifier/username. Both paths retain `must_change_password = true`.
- **Expected / actual:** The production runbook requires an unusable random initial password and a one-time broker invitation. The application instead installs a deterministic usable password and flashes it into the administrator session. Instructor and staff creation follow the same pattern by source inspection.
- **Impact:** Someone who knows an identifier can guess the initial/reset password and complete the required password change before the intended person. Login throttling offers little protection against a correct first guess. The new/reset password itself has no short expiration.
- **Correction:** Restore broker-based one-time invitation/reset delivery, with a cryptographically random unusable initial password. Remove predictable reset construction and credential flashes. Preserve after-commit delivery and audit events. If offline handoff is institutionally required, approve a separate random, expiring, single-use design rather than identifier-derived passwords.
- **Acceptance:** Valid creation and admin reset never produce a password derivable from any account field; invitation token expiry/reuse are tested; rollback never sends an invitation; old sessions are addressed by an explicit reset policy. Update conflicting account-creation tests and runbook together.

### F02 — High: larger daily reports omit stored attendance scans

- **Location:** `app/Services/PersonnelAttendanceReportService.php:24` (`orderBy('scan_time')` followed by `chunkById(500, ...)`).
- **Reproduce:** Insert 200 staff and four scans per person, person-by-person, for one date. This produces 800 IDs whose order differs from scan time. Generate the daily report. The first staff member has four database rows, including 17:00 final-out, but `afternoon_time_out` in the report is empty. Reproduced on MariaDB.
- **Expected / actual:** All selected canonical scans must contribute to the report. Pagination advances by the last row's ID while the primary sort remains scan time, skipping lower-ID rows that belong later in time order.
- **Impact:** The HTML report and PDF/XLSX jobs share this service, so all can falsely show missing scans. Normal small fixtures do not expose it.
- **Correction:** Traverse in a consistent unique-ID order (`reorder` before ID chunking), then retain per-person/per-period time selection in the aggregation. Do not combine a scan-time primary order with an ID-only cursor.
- **Acceptance:** Compare report contents with direct fixture truth across 499/500/501 and 800+ rows, deliberately shuffled ID/time order, multiple days and filtered personnel. Verify HTML, PDF input and XLSX cells agree.

### F03 — Medium: stale pending leave objects overwrite newer terminal decisions

- **Locations:** `app/Http/Controllers/LeaveRequestController.php:78`; `AdminLeaveRequestController.php:32` and `:66`.
- **Reproduce:** Load a pending leave model, change its database row to approved through a separate query, then invoke owner cancellation with the previously loaded model: status becomes cancelled while reviewer metadata remains. Conversely, load pending, persist cancelled, then invoke admin approval with the stale object: status becomes approved. Both deterministic interleavings reproduced on SQLite and MariaDB.
- **Expected / actual:** Only a currently pending record may transition. Cancellation checks a stale model without a lock; review checks before the transaction and continues using the stale model even after locking user/leave rows.
- **Impact:** Racing requests can overwrite approval, rejection or cancellation and produce contradictory audit/notification history. The successful concurrent *submission* check does not cover this transition defect.
- **Correction:** Use the same transaction/lock order for cancel and review; reload and lock the target leave, recheck status inside the transaction, and permit one terminal transition only. Send notifications after commit from the committed state.
- **Acceptance:** Concurrent approve/reject/cancel combinations have one winner; losing requests return a conflict/validation result, do not alter reviewer metadata and emit no successful transition notification.

### F04 — Medium: random QR attendance ignores a changed student's portal role

- **Locations:** `app/Services/QrIdentityResolver.php:19`; `app/Services/QrAttendanceService.php:42`; `app/Services/RoleAssignmentService.php`.
- **Reproduce:** Issue a random credential to an active student with a schedule, promote that user to admin through `RoleAssignmentService`, and scan the old credential during the class window. Student attendance is still created for the admin account.
- **Expected / actual:** QR eligibility must agree with current role/profile rules. Legacy resolution checks profile-role agreement, but random resolution returns the account directly; the attendance service selects student logic solely because a student relation exists. Role assignment leaves that relation and credential active.
- **Impact:** Role changes can silently continue recording attendance under obsolete student eligibility. This is attendance authorization inconsistency, not a demonstrated admin privilege escalation.
- **Correction:** Under the user lock, enforce a supported current attendance role and matching profile before selecting a recording path. Define profile/credential lifecycle during role changes and revoke or reject incompatible credentials.
- **Acceptance:** Student-to-admin and student-to-staff transitions cannot record old class attendance; supported consistent roles still scan correctly; legacy/random resolution follow the same eligibility rule.

### F05 — Medium: interrupted export jobs can remain permanently processing

- **Location:** `app/Jobs/GenerateDailyPersonnelExport.php:35` and `:39`; related `config/queue.php` and `deploy/systemd/iqams-queue.service`.
- **Reproduce:** Create an export in `processing` with no artifact, representing termination after its claim transaction; invoke the job again. It returns without rendering or changing the state. Confirmed with mocks that prohibit report/render calls.
- **Expected / actual:** An abandoned attempt should be recoverable or explicitly failed. Only `pending` records can run; a hard worker termination cannot execute the catch block that resets pending. There is no processing lease/recovery or failure callback to resolve this state.
- **Impact:** The requester can poll processing indefinitely until expiry, while the retried job can return successfully without producing a file. Ordinary caught exceptions have a retry path; this finding concerns abandoned processing.
- **Related configuration:** The worker timeout is 90 seconds and the default Redis `retry_after` is also 90; the production example does not override it. This leaves no timeout/reservation safety margin.
- **Correction:** Add bounded ownership/lease recovery and a final failure transition; guard against concurrent writers. Set queue reservation longer than the worker timeout (for example 120 versus 90 seconds), with a documented render timeout policy.
- **Acceptance:** Terminate a real isolated worker after claim, retry after reservation expiry, and obtain one completed artifact or an explicit failed record; concurrent duplicate jobs cannot overwrite ownership/artifacts. Verify caught transient failures separately.

### F06 — Medium: personnel names become spreadsheet formulas

- **Location:** `app/Services/DailyPersonnelAttendanceExportService.php:61` (`fromArray` with the default value binder).
- **Reproduce:** Pass a report row with name `=1+1`. Cell A7 has formula type `f` and calculates to `2` in PhpSpreadsheet. This probe uses an inert arithmetic expression; no external formula or exfiltration was attempted.
- **Expected / actual:** Names are literal text; the spreadsheet writer interprets a leading equals sign as a formula. Profile name validation permits arbitrary strings, and the report consumes profile names.
- **Impact:** Exported names can be corrupted or interpreted as formulas. Administrative control of profile names limits the demonstrated input surface; unauthenticated injection is not claimed.
- **Correction:** Set user-controlled text cells explicitly as strings. Apply the same rule to other future spreadsheet exports.
- **Acceptance:** Names beginning with `=`, `+`, `-` and `@` remain exact literal strings after writing/reopening XLSX; no formula cell is produced from a name.

### F07 — Low: failed avatar writes return a success path

- **Locations:** `app/Services/ProfileImageService.php:43–44`; `config/filesystems.php` (`throw => false`).
- **Reproduce:** Make both disk `put` calls return false and submit a decodable image to the service. It returns an `avatars/*.jpg` path without throwing. Reproduced with a controlled mock on both databases.
- **Expected / actual:** Storage failure should fail the upload. Boolean failure results are ignored, and the exception-only cleanup path is skipped.
- **Impact:** Callers can persist a nonexistent avatar path; update paths may then remove the previous valid image.
- **Correction:** Require successful primary and thumbnail writes before returning; remove partial artifacts on failure and preserve the existing avatar until the replacement is safely stored.
- **Acceptance:** Primary failure, thumbnail failure and exceptions all leave the previous profile photo usable and show a recoverable upload error.

### F08 — Low: rejected overlapping leave uploads leave private orphan files

- **Location:** `app/Http/Controllers/LeaveRequestController.php:48`.
- **Reproduce:** Submit an overlapping leave request with a PDF attachment. Response is 422, leave row count remains one, but a new file remains under `leave-attachments` on the fake local disk.
- **Expected / actual:** Rejected requests should leave no unused upload. The file is written before the transactional conflict check and is not cleaned up when that check fails.
- **Impact:** Repeated valid-file but rejected submissions consume private storage and retain attachments that no leave row references.
- **Correction:** Preserve transactional overlap checking and add compensation for a newly stored attachment when record creation fails. Also handle failed disk writes explicitly.
- **Acceptance:** Overlap rejection, database failure and storage failure create no orphan file and do not affect existing attachments; successful requests retain exactly one linked file.

## Dependency findings and verification debt

### D01 — Affected dependencies in both lockfiles

`composer audit --locked --format=json` returned **12 advisories across two production packages**, no abandoned packages. `npm.cmd audit --json` returned **two affected development packages**, one high and one moderate. Initial sandbox network failures were retried with the required network permission; these are successful live advisory results, not inferred from package ages.

| Locked package | Upstream severity / count | Evidence and remediation target |
| --- | --- | --- |
| `guzzlehttp/guzzle` 7.15.1 | 1 high, 1 medium | Host canonicalization and cookie scope advisories; fixed 7.x target at least 7.15.2. [Host advisory](https://github.com/advisories/GHSA-v5mv-p594-2x33), [cookie advisory](https://github.com/advisories/GHSA-f7vp-7xgx-4w4r). |
| `league/commonmark` 2.8.3 | 8 high, 2 medium | Parsing/extension DoS and attribute filtering issues. Update to at least 2.10.0 for all returned ranges, then rerun audit. [Latest returned Attributes advisory](https://github.com/advisories/GHSA-8rr7-cvq3-gmfh), [core parsing advisory](https://github.com/advisories/GHSA-2q4p-g7hv-5rgv). |
| `nanoid` 3.3.16 | high, development dependency | Zero-size custom generator loop; affected range below 3.3.18. [Advisory](https://github.com/advisories/GHSA-2v37-7h3g-55p8). |
| `postcss` 8.5.22 | moderate, development dependency | Source-map file-reading issue; affected range through 8.5.22. Select a patched compatible version above that range. [Advisory](https://github.com/advisories/GHSA-fxqj-rqcc-2cmp). |

Full CommonMark identifiers returned: `GHSA-8rr7-cvq3-gmfh`, `GHSA-jjv6-8j6v-6j52`, `GHSA-f8fg-pg57-v4j8`, `GHSA-j8pm-gj4c-rq4x`, `GHSA-mj63-m3rc-8ppr`, `GHSA-mh25-x5hq-wrqp`, `GHSA-jfm3-95jq-q3rf`, `GHSA-g2gp-3wwq-f4ph`, `GHSA-2q4p-g7hv-5rgv`, `GHSA-29pj-957v-52mc`.

**Reachability:** Targeted searches found no direct application use of user-controlled Guzzle URLs, CommonMark/Attributes rendering, or Nano ID custom generators. IQAMS uses Blade and its PDF renderer disables remote retrieval. This reduces demonstrated exposure but is not proof that all transitive execution is safe. The PostCSS/Nano ID findings affect the build toolchain rather than a demonstrated browser runtime attack. Treat upstream high severity as package severity, not a confirmed high-severity exploit in IQAMS.

**Reproduce / accept:** Run both audit commands against the unchanged locks; remediate through a reviewed dependency update, rerun both audits plus the full application/build checks, and record residual reachability if any advisory remains. No dependency was changed during this audit.

### V01 — Medium verification debt: complete PHP suite is not green

| Test | Observed issue | Assessment |
| --- | --- | --- |
| `LeaveRequestTest::test_approved_multi_day_leave_counts_every_date_and_is_not_absent` | Expected 3 leave days; actual 2 | August 20–22 includes Saturday. Calendar code excludes weekends before considering leave. |
| `LeaveRequestTest::test_leave_spanning_months_only_counts_dates_inside_selected_month` | Expected 2 August leave days; actual 0 | August 1–2 are Saturday/Sunday; same precedence issue. |
| `AccountInvitationTest::test_student_instructor_and_staff_creation_use_role_specific_temporary_passwords` | Runner error: `Call to a member function all() on array` | Student fixture lacks newly required `enrollment_type`. A new explicit JSON probe returns 422 for that omission and a corrected regular payload creates the student. The original error's full framework stack was not retained; do not describe it as a demonstrated production HTTP 500. |

The account test also encodes F01's insecure password contract. `PersonnelAttendanceCalculationTest` separately requires weekend exclusion. Therefore the two leave failures represent an unresolved definition of calendar leave days versus rated working leave days, not sufficient evidence to reverse the weekend rule automatically.

**Correction / acceptance:** Resolve the leave metric explicitly; keep attendance-rated working days distinct from total calendar leave duration if both are required. Update fixtures for required enrollment fields. Replace predictable-password expectations as part of F01, without weakening unrelated assertions. The complete suite must pass with documented policy; no exclusions or blanket skipped tests.

## Prioritized remediation backlog

| Order | Work | Dependencies and regression gate |
| --- | --- | --- |
| 1 | F01: secure account onboarding/reset | Decide broker delivery versus approved random offline handoff; update account tests/runbook; prove identifiers cannot predict credentials. |
| 2 | F02: correct report pagination | Independent; prove complete data across chunk boundaries before relying on daily reports or exports. |
| 3 | F03: serialize leave transitions | Independent; deterministic and real concurrent transition tests, one committed outcome. |
| 4 | F04: enforce current role/profile at scan time | Define role-change profile lifecycle; reject stale credentials and retain normal attendance behavior. |
| 5 | F05: recover abandoned exports and align timeouts | Coordinate job state and queue configuration; real isolated worker termination/retry check. |
| 6 | D01: update affected locked packages | Reviewed dependency-only change; clean audits or documented exceptions; full regression/build after update. |
| 7 | F06: force spreadsheet text types | Independent; reopened XLSX retains literal names; run after F02 for end-to-end export validation. |
| 8 | F07/F08: transactional upload failure handling | Independent; failing storage and rejected request probes show no data loss or orphan artifacts. |
| 9 | V01: reconcile policy/test drift and restore release gate | Depends on F01 and leave metric decision; full PHP suite plus JS/build pass. |
| 10 | Close unavailable-environment coverage | Browser with disposable role accounts, hardware scanner, representative Redis/worker/Nginx staging, production-sized anonymized data and backup restore evidence. |

### Improvements and unresolved decisions (not counted as reproduced defects)

- The reusable modal markup contains keyboard handlers but lacks explicit dialog role/name/modal semantics and an obvious opener-focus restoration path. Add accessible semantics and validate actual focus behavior in a browser before claiming accessibility compliance. The chart's hover details also need keyboard verification.
- Student administration eagerly loads all active schedule offerings; report filter options also collect personnel. Consider pagination/search after profiling representative institutional volumes. No production capacity threshold was supplied, so local timings are not graded against an invented SLA.
- Audit-log immutability is enforced by Eloquent model hooks. Database privileges and direct-query tamper resistance remain an operational control to verify, not proof of append-only storage at every layer.
- Historical student summaries select currently eligible schedules; clarify whether reports should preserve prior section/enrollment membership after reassignment and define an academic-term boundary. No independent policy was supplied for these semantics.
- Legacy QR fallback has no cutoff when its configuration is empty. The runbook deliberately requires an approved cutoff after coverage verification; this audit did not make that policy change.
- Preserve the runbook's distinction between external database backups and application features. Actual backup ownership, successful restoration, alert delivery and institutional retention approval require operations evidence.

## Reproduction notes and audit artifacts

Run commands from `iqams/`. [FullSystemAuditProbeTest.php](audit-evidence/FullSystemAuditProbeTest.php) retains the synthetic reproductions and measurement method. [audit-concurrency.php](audit-evidence/audit-concurrency.php) retains the synchronized subprocess probes. These are audit artifacts, not permanent acceptance tests; reverse the defect assertions when implementing fixes.

Before PHPUnit, explicitly isolate the process (PowerShell):

```powershell
$env:APP_ENV='testing'
$env:APP_CONFIG_CACHE='bootstrap/cache/config.audit-unused.php'
$env:DB_CONNECTION='sqlite'
$env:DB_DATABASE=':memory:'
$env:DB_URL='(null)'
$env:CACHE_STORE='array'
$env:SESSION_DRIVER='array'
$env:MAIL_MAILER='array'
$env:QUEUE_CONNECTION='sync'
php vendor/phpunit/phpunit/phpunit --do-not-cache-result
php vendor/phpunit/phpunit/phpunit --do-not-cache-result docs/audit-evidence/FullSystemAuditProbeTest.php
npm.cmd test
npm.cmd run build -- --outDir storage/framework/testing/audit-build
composer check-platform-reqs
composer audit --locked --format=json
npm.cmd audit --json
```

For MariaDB reproduction, initialize a **new disposable instance** with XAMPP's `mysql_install_db.exe`, a new data directory, port 13316 and loopback binding; create only `iqams_audit`. Use `DB_CONNECTION=mysql`, `DB_HOST=127.0.0.1`, `DB_PORT=13316`, `DB_DATABASE=iqams_audit`, `DB_USERNAME=root`, `DB_PASSWORD=(empty)`, `DB_URL=(null)` with the other isolation values above. `(empty)` prevents Windows PowerShell from removing the variable and allowing `.env` fallback. Set an isolated compiled-view path for Artisan. Run migrations on that instance only. PHPUnit's `RefreshDatabase` recreates schema, so never substitute an existing application database.

The concurrency script pins that disposable address/database, runs migrations only through the separate setup step, and adds fixed-name fixtures; run it once against a freshly migrated empty database. It does not clear an existing database or start a network service. Its workers read synthetic QR values internally and print only outcomes/timings. Shut down the disposable instance after use.

The audit's ignored temporary database/build/test files remain under `storage/framework/testing`; no running audit database service was left behind. Browser and production/Redis/hardware gaps above are explicit outstanding verification work. Every requested audit area has been assessed to the stated depth or marked blocked; remediation remains unimplemented by design.
