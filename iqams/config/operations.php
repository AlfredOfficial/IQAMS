<?php

return [
    'scheduler_heartbeat_key' => env('IQAMS_SCHEDULER_HEARTBEAT_KEY', 'ops.scheduler.last_run'),
    'queue_heartbeat_key' => env('IQAMS_QUEUE_HEARTBEAT_KEY', 'ops.queue.last_processed'),
    'heartbeat_ttl_seconds' => (int) env('IQAMS_HEARTBEAT_TTL_SECONDS', 300),
    'health_max_age_seconds' => (int) env('IQAMS_HEALTH_MAX_AGE_SECONDS', 120),
];
