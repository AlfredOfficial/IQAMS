# Navigation and performance audit — September 5, 2026

## Findings and changes

The custom sidebar navigation fetched complete HTML but replaced only
`#app-content` and the sidebar. Instructor and staff headings were outside that
replacement region, so they retained the previous page title. Scripts in fetched
HTML did not execute; this affected the report export controls. The navigation
also lacked request cancellation and retried redirected or failed responses as
a second full page request. Back/forward could start competing fetches.

Sidebar links now use native browser document navigation for every portal.
The destination URL, heading, scripts, mobile menu and browser history are
initialized together. Loading feedback is delayed and does not block clicks;
it no longer waits for every initial page resource before allowing interaction.

Personal dashboards previously polled every three seconds, including hidden
tabs; instructor requests could overlap. They now poll every 15 seconds with
one request in flight, pause while hidden, and abort requests on departure.
Admin attendance remains at four seconds and analytics at 15 seconds, with
the same cancellation and visibility controls. This reduces a visible personal
dashboard's scheduled polling from 20 to 4 requests per minute, excluding
initial loads and refreshes when returning to a tab.

Scanner Security no longer queries and embeds every eligible user. Its
permission-protected search returns at most 25 active student/personnel users.
Desktop and mobile notification bells share their two queries within one
request; the next request retrieves fresh data.

Application logs contained compiled-template file-not-found and Windows rename
errors. PHPUnit previously shared the live Blade directory. Each test process
now uses its own compiled-view directory. Do not run `view:clear`, `view:cache`
or `optimize:clear` against the live application during use.

## Local server measurement

The existing PHP 8.4.11 installation did not load OPcache. A second loopback
server was started with OPcache enabled through process flags, using the same
application and database without changing the global PHP configuration.

Sequential unauthenticated GET `/login` timings, measured with curl:

| Server | Warm time to first byte |
| --- | --- |
| Existing port 8000 | 471, 452, 507 ms |
| OPcache port 8081 | 38, 42, 78 ms |

The first OPcache request took 927 ms. Earlier existing-server health/login
requests took approximately 1.1–3.6 seconds. These are small local samples,
affected by warm caches and machine activity; they are not authenticated page
benchmarks or evidence of concurrent-user capacity.

## Running locally

Build assets with `npm.cmd run build`. Stop the Vite development server when
demonstrating the system. Laravel's `public/hot` marker selects Vite even after
a production build; keep that marker outside `public` for built-asset mode.
Run `serve-local.cmd` from the IQAMS directory and open
`http://127.0.0.1:8081`. Stop an existing process on that port before starting
another. This launcher enables OPcache for that process only.

This loopback PHP server is for local use. Multi-user deployment should use the
web server and worker configuration in `production-deployment.md`, with OPcache,
shared state and measured capacity. The existing queue worker and scheduler
are still needed for queued exports, notifications and absence processing.

## Verification and limits

The full existing PHP suite passed after the navigation and polling changes:
275 tests, 1,204 assertions. Additional regression tests cover native click,
history and form behavior, cancelled/hidden polling, bounded user search,
notification query reuse, and admin sidebar destinations.

After the database-load changes, 21 focused PHP tests passed with 145
assertions. All nine JavaScript regression tests and the Vite production build
passed. The optimized server returned HTTP 200 for login and both built assets.

No controllable browser was available in this session. JavaScript tests use
Node's event environment; PHP tests use an isolated SQLite database. Neither
replaces checking the deployed application in a real browser or testing a
representative populated MySQL database under concurrent load.

For manuscript claims, report the actual test environment, record counts,
concurrent users, response-time percentiles and error rate. Local login timings
and passing correctness tests do not establish scalability.
