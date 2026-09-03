# IQAMS performance setup

## Local XAMPP

Use `SESSION_DRIVER=file` and `CACHE_STORE=file` in `.env` for a single-machine installation. Keep `DB_CONNECT_TIMEOUT=3` so a stopped MySQL service fails quickly.

Enable OPcache in `C:\xampp\php\php.ini`, then restart Apache:

```ini
zend_extension=opcache
opcache.enable=1
opcache.enable_cli=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000
opcache.validate_timestamps=1
opcache.revalidate_freq=2
```

Confirm it is loaded with `php -m | findstr /I opcache`.

## Deployment

Use Redis or database-backed sessions/cache when multiple application instances share state. During each deployment run:

```shell
composer install --no-dev --optimize-autoloader
php artisan optimize
npm install
npm run build
```

For development after configuration or route changes, run `php artisan optimize:clear`.

Personnel attendance history accepts a maximum inclusive range of 366 days in
production. Daily personnel PDF and Excel exports are queued, stored privately,
and expire after 24 hours. Monitor their queue processing through the
`report_exports` status endpoint rather than holding an HTTP request open.

The student and school-event absence commands select eligible students in bulk
and insert absence rows in bounded batches. They use shared Redis scheduler
locks so only one application instance performs each occurrence at a time.

Production operators should enable the Redis queue worker, scheduler timer, and
health timer from `deploy/systemd`. Check `php artisan ops:health` and the
`iqams-queue`/`iqams-health` systemd journals during rollout.
