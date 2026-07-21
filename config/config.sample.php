<?php
declare(strict_types=1);

// Copy this file to config.php (gitignored) and fill in real values before deploying.
return [
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'twitchspawn_builder',
        'user' => 'twitchspawn_builder',
        'pass' => 'change-me',
        'charset' => 'utf8mb4',
    ],
    'save_throttle' => [
        'max_saves_per_window' => 20,
        'window_seconds' => 3600,
    ],
    // Import upload cap — real rulesets are small text files.
    'import_max_bytes' => 262144,
];
