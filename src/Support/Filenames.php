<?php
declare(strict_types=1);

namespace TSL\Support;

final class Filenames
{
    /** Strips a user-supplied Minecraft nick down to safe filename characters for Content-Disposition. */
    public static function sanitizeNick(?string $nick): ?string
    {
        if ($nick === null) {
            return null;
        }
        $clean = preg_replace('/[^A-Za-z0-9_\-]/', '', $nick);
        $clean = substr($clean, 0, 32);
        return $clean === '' ? null : $clean;
    }

    public static function rulesFilename(?string $nick): string
    {
        $safe = self::sanitizeNick($nick);
        return $safe === null ? 'rules.default.tsl' : "rules.{$safe}.tsl";
    }
}
