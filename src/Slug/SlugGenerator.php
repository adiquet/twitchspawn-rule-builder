<?php
declare(strict_types=1);

namespace TSL\Slug;

/** Random 12-hex-char slugs for the no-login shareable view URL. */
final class SlugGenerator
{
    public static function generate(): string
    {
        return bin2hex(random_bytes(6));
    }
}
