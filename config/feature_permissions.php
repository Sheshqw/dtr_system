<?php

// Catalog of features a Super Admin can grant or revoke per employee, and the
// default grant for each role. An employee's *effective* permission is the
// role default, overridden by any matching row in the feature_permissions
// table (see FeaturePermissionService).
return [

    'features' => [
        'dtr_records.view_all' => 'Employee Logs — view other employees\' attendance records',
        'dtr_generation.export' => 'Bulk DTR generation & CSC Form 48 export',
        'shift_management'      => 'Shift management',
        'holiday_calendar'      => 'Holiday calendar management',
        'system_health'         => 'System health monitoring',
        'tracking_analytics'    => 'Tracking & analytics (tardiness ranking, Bradford Factor)',
        'notifications_manage'  => 'Notifications & alerts management',
    ],

    'role_defaults' => [
        'super_admin' => [
            'dtr_records.view_all' => true,
            'dtr_generation.export' => true,
            'shift_management'      => true,
            'holiday_calendar'      => true,
            'system_health'         => true,
            'tracking_analytics'    => true,
            'notifications_manage'  => true,
        ],
        'hr_officer' => [
            'dtr_records.view_all' => true,
            'dtr_generation.export' => true,
            'shift_management'      => true,
            'holiday_calendar'      => true,
            'system_health'         => false,
            'tracking_analytics'    => true,
            'notifications_manage'  => true,
        ],
        'employee' => [
            'dtr_records.view_all' => false,
            'dtr_generation.export' => false,
            'shift_management'      => false,
            'holiday_calendar'      => false,
            'system_health'         => false,
            'tracking_analytics'    => false,
            'notifications_manage'  => false,
        ],
    ],

];
