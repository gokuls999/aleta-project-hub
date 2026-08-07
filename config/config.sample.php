<?php
// Copy this file to config.php and fill in real values. config.php is gitignored.
return [
    'db' => [
        'host'    => '127.0.0.1',
        'name'    => 'aleta_worktracker',
        'user'    => 'root',
        'pass'    => '',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'base_url'     => 'http://localhost:8000',
        'session_name' => 'aleta_wt',
        'timezone'     => 'Asia/Kolkata',
    ],
    'zoho' => [
        'dc'            => 'in',
        'api_base'      => 'https://projectsapi.zoho.in/api/v3',
        'accounts_base' => 'https://accounts.zoho.in',
        'client_id'     => '',   // Zoho self-client Client ID
        'client_secret' => '',   // Zoho self-client Client Secret
        'refresh_token' => '',   // Projects-scoped refresh token
        'portal_id'     => '',   // Zoho Projects portal id
    ],
];
