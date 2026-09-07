<?php

use App\Services\PasswordSetupService;
use Illuminate\Contracts\Console\Kernel;

// Helper for the opt-in MySQL integration test. No real-account credentials.
$root = dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'mysql'
    || config('database.connections.mysql.database') !== 'iqams_password_setup_test'
    || config('database.connections.mysql.host') !== '127.0.0.1'
    || (string) config('database.connections.mysql.port') !== '13317'
    || config('database.connections.mysql.url')) {
    throw new RuntimeException('Password concurrency helper requires the disposable test database.');
}
$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
while (microtime(true) < $input['start_at']) {
    usleep(1000);
}
try {
    $result = app(PasswordSetupService::class)->consume($input['credentials']);
    echo json_encode(['status' => $result], JSON_THROW_ON_ERROR);
} catch (Throwable) {
    // No exception data or credentials in subprocess output.
    echo json_encode(['status' => 'unexpected_failure']);
    exit(1);
}
