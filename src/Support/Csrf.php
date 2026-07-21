<?php
declare(strict_types=1);

namespace TSL\Support;

/** Minimal per-session CSRF token for the two state-changing endpoints (save.php, r.php edit POST). */
final class Csrf
{
    public static function token(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function verify(?string $submitted): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        if (empty($_SESSION['csrf_token']) || $submitted === null) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $submitted);
    }
}
