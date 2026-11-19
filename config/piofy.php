<?php

return [
    'enabled' => (bool) env('PIOFY_ENABLED', true),
    'base_url' => rtrim((string) env('PIOFY_BASE_URL', 'http://192.168.10.102:5000'), '/'),
    'username' => (string) env('PIOFY_USERNAME', 'admin'),
    'password' => (string) env('PIOFY_PASSWORD', 'admin'),
    'timeout' => (int) env('PIOFY_TIMEOUT', 30),
];
