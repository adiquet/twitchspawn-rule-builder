<?php
declare(strict_types=1);

namespace TSL\Slug;

/**
 * The edit token for a saved ruleset: high-entropy, shown once at save time,
 * never stored in plaintext. This is security-by-obscurity, not real auth —
 * see the "ruleset_revisions" table / "fork a copy" escape hatch for what
 * mitigates a lost or leaked token.
 */
final class EditSecret
{
    public static function generateToken(): string
    {
        return bin2hex(random_bytes(24));
    }

    public static function hash(string $token): string
    {
        return password_hash($token, PASSWORD_DEFAULT);
    }

    public static function verify(string $token, string $hash): bool
    {
        return password_verify($token, $hash);
    }
}
