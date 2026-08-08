<?php

return [
    'ip'       => env('BIOMETRIC_IP',       '192.168.1.201'),
    'port'     => (int) env('BIOMETRIC_PORT',  4370),
    'password' => env('BIOMETRIC_PASSWORD', ''),
    'timeout'  => (int) env('BIOMETRIC_TIMEOUT', 5),
];
