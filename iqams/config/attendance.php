<?php

return [
    'early_scan_minutes' => (int) env('ATTENDANCE_EARLY_SCAN_MINUTES', 15),
    'present_grace_minutes' => (int) env('ATTENDANCE_PRESENT_GRACE_MINUTES', 15),
    'duplicate_cooldown_seconds' => (int) env('ATTENDANCE_DUPLICATE_COOLDOWN_SECONDS', 5),
    'personnel_daily_scan_limit' => (int) env('ATTENDANCE_PERSONNEL_DAILY_SCAN_LIMIT', 4),
    'scanner_idle_submit_milliseconds' => (int) env('ATTENDANCE_SCANNER_IDLE_SUBMIT_MS', 350),

    'personnel_windows' => [
        'instructor' => [
            'morning_in' => [
                'label' => 'Morning Time In',
                'type' => 'time_in',
                'start' => env('ATTENDANCE_INSTRUCTOR_MORNING_IN_START', '06:30'),
                'end' => env('ATTENDANCE_INSTRUCTOR_MORNING_IN_END', '08:30'),
                'on_time_until' => env('ATTENDANCE_INSTRUCTOR_MORNING_IN_ON_TIME_UNTIL', '08:00'),
            ],
            'lunch_out' => [
                'label' => 'Lunch Time Out',
                'type' => 'time_out',
                'start' => env('ATTENDANCE_INSTRUCTOR_LUNCH_OUT_START', '11:30'),
                'end' => env('ATTENDANCE_INSTRUCTOR_LUNCH_OUT_END', '12:30'),
                'not_early_before' => env('ATTENDANCE_INSTRUCTOR_LUNCH_OUT_NOT_EARLY_BEFORE', '12:00'),
            ],
            'afternoon_in' => [
                'label' => 'Afternoon Time In',
                'type' => 'time_in',
                'start' => env('ATTENDANCE_INSTRUCTOR_AFTERNOON_IN_START', '12:30'),
                'end' => env('ATTENDANCE_INSTRUCTOR_AFTERNOON_IN_END', '13:30'),
                'on_time_until' => env('ATTENDANCE_INSTRUCTOR_AFTERNOON_IN_ON_TIME_UNTIL', '13:00'),
            ],
            'final_out' => [
                'label' => 'Final Time Out',
                'type' => 'time_out',
                'start' => env('ATTENDANCE_INSTRUCTOR_FINAL_OUT_START', '16:30'),
                'end' => env('ATTENDANCE_INSTRUCTOR_FINAL_OUT_END', '18:00'),
                'not_early_before' => env('ATTENDANCE_INSTRUCTOR_FINAL_OUT_NOT_EARLY_BEFORE', '17:00'),
            ],
        ],
        'staff' => [
            'morning_in' => [
                'label' => 'Morning Time In',
                'type' => 'time_in',
                'start' => env('ATTENDANCE_STAFF_MORNING_IN_START', '06:30'),
                'end' => env('ATTENDANCE_STAFF_MORNING_IN_END', '08:30'),
            ],
            'lunch_out' => [
                'label' => 'Lunch Time Out',
                'type' => 'time_out',
                'start' => env('ATTENDANCE_STAFF_LUNCH_OUT_START', '11:30'),
                'end' => env('ATTENDANCE_STAFF_LUNCH_OUT_END', '12:30'),
            ],
            'afternoon_in' => [
                'label' => 'Afternoon Time In',
                'type' => 'time_in',
                'start' => env('ATTENDANCE_STAFF_AFTERNOON_IN_START', '12:30'),
                'end' => env('ATTENDANCE_STAFF_AFTERNOON_IN_END', '13:30'),
            ],
            'final_out' => [
                'label' => 'Final Time Out',
                'type' => 'time_out',
                'start' => env('ATTENDANCE_STAFF_FINAL_OUT_START', '16:30'),
                'end' => env('ATTENDANCE_STAFF_FINAL_OUT_END', '18:00'),
            ],
        ],
    ],
];
