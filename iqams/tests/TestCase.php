<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        // Live requests and concurrent test processes must never clear each
        // other's compiled Blade templates (especially on Windows).
        $path = dirname(__DIR__).'/storage/framework/testing/views-'.getmypid();
        if (! is_dir($path)) {
            mkdir($path, 0777, true);
        }
        $_ENV['VIEW_COMPILED_PATH'] = $_SERVER['VIEW_COMPILED_PATH'] = $path;

        return parent::createApplication();
    }
}
