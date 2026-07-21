<?php
declare(strict_types=1);

/**
 * Supports two on-disk layouts so the same codebase works both for local
 * dev (public/ as its own folder, docroot points at it) and for hosting
 * where this whole app sits in one subfolder of a bigger site's docroot
 * (e.g. gamelikeus.com/twitchspawn — no separate docroot control, so what
 * was public/* gets uploaded flattened into that subfolder directly,
 * with src/config/db as protected children instead of siblings):
 *  - sibling layout:   this dir has NO ./src, but its parent does (../src)
 *  - flattened layout: this dir has its own ./src and ./config
 */
if (is_dir(__DIR__ . '/src') && is_dir(__DIR__ . '/config')) {
    define('TSL_ROOT', __DIR__);
} else {
    define('TSL_ROOT', dirname(__DIR__));
}
define('TSL_SRC_DIR', TSL_ROOT . '/src');
define('TSL_TEMPLATES_DIR', TSL_SRC_DIR . '/View/templates');

require TSL_SRC_DIR . '/autoload.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function tsl_e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}
