<?php
declare(strict_types=1);

namespace TSL\Grammar;

/**
 * Turns raw .tsl text into rule-blocks of words, respecting:
 *  - `# ...` line comments and `#* ... *#` block comments (comment-only
 *    lines do NOT act as the blank-line rule separator — a documented
 *    real-world gotcha we deliberately preserve here),
 *  - `%...%` word grouping with `\%` escaping,
 *  - multi-line rule continuation (newline + leading space).
 */
final class Tokenizer
{
    /**
     * @return RuleBlock[]
     */
    public static function tokenize(string $raw): array
    {
        $cleaned = self::stripComments($raw);

        $rawLines = preg_split('/\r\n|\r|\n/', $raw);
        $cleanLines = preg_split('/\r\n|\r|\n/', $cleaned);

        /** @var RuleBlock[] $blocks */
        $blocks = [];
        $currentLines = [];   // list of ['line' => int, 'text' => string] cleaned content lines
        $blockStartLine = null;
        $pendingComment = [];  // comment-only lines seen since the last blank line, before the block started

        $flush = function () use (&$currentLines, &$blocks, &$blockStartLine, &$pendingComment): void {
            if (empty($currentLines)) {
                return;
            }
            $joined = [];
            foreach ($currentLines as $i => $entry) {
                $text = $i === 0 ? ltrim($entry['text']) : ' ' . ltrim($entry['text']);
                $joined[] = $text;
            }
            $logical = trim(implode('', $joined));
            $words = self::wordize($logical);
            $leadingComment = $pendingComment === [] ? null : trim(implode(' ', $pendingComment));
            $blocks[] = new RuleBlock(
                $blockStartLine ?? $currentLines[0]['line'],
                array_map(fn ($e) => $e['line'], $currentLines),
                $words,
                implode("\n", array_map(fn ($e) => $e['text'], $currentLines)),
                $leadingComment === '' ? null : $leadingComment
            );
            $currentLines = [];
            $blockStartLine = null;
            $pendingComment = [];
        };

        $lineCount = count($rawLines);
        for ($i = 0; $i < $lineCount; $i++) {
            $lineNo = $i + 1;
            $rawLine = $rawLines[$i] ?? '';
            $cleanLine = $cleanLines[$i] ?? '';

            $rawTrim = trim($rawLine);
            $cleanTrim = trim($cleanLine);

            if ($cleanTrim === '') {
                if ($rawTrim === '') {
                    // Truly blank line: this is the only real block separator. Any comment(s)
                    // sitting above it are a general aside, not a note for the next rule below.
                    $flush();
                    $pendingComment = [];
                } else {
                    // Comment-only line (single- or block-comment): ignored for parsing, does NOT
                    // close the current block. If it comes immediately before the block starts (no
                    // blank line in between), it's treated as that rule's note/label.
                    if (empty($currentLines)) {
                        $pendingComment[] = self::commentLineText($rawLine);
                    }
                    continue;
                }
            } else {
                if (empty($currentLines)) {
                    $blockStartLine = $lineNo;
                }
                $currentLines[] = ['line' => $lineNo, 'text' => $cleanLine];
            }
        }
        $flush();

        return $blocks;
    }

    /** Strips `#`/`#*`/`*#` markers from a comment-only line, leaving just its text. */
    private static function commentLineText(string $rawLine): string
    {
        return trim(trim($rawLine), " \t#*");
    }

    /**
     * Replaces `# ...` and `#* ... *#` comment content with spaces
     * (preserving newlines and non-comment character positions so line
     * numbers stay accurate), while respecting `%...%` grouping so a `#`
     * inside a grouped word is never mistaken for a comment.
     */
    public static function stripComments(string $raw): string
    {
        $len = strlen($raw);
        $out = '';
        $inGroup = false;
        $inBlockComment = false;

        for ($i = 0; $i < $len; $i++) {
            $ch = $raw[$i];
            $next = $i + 1 < $len ? $raw[$i + 1] : '';

            if ($inBlockComment) {
                if ($ch === '*' && $next === '#') {
                    $out .= '  ';
                    $i++;
                    $inBlockComment = false;
                } else {
                    $out .= ($ch === "\n") ? "\n" : ' ';
                }
                continue;
            }

            if (!$inGroup && $ch === '#' && $next === '*') {
                $out .= '  ';
                $i++;
                $inBlockComment = true;
                continue;
            }

            if (!$inGroup && $ch === '#') {
                // Line comment: consume to end of line.
                while ($i < $len && $raw[$i] !== "\n") {
                    $out .= ' ';
                    $i++;
                }
                if ($i < $len) {
                    $out .= "\n"; // preserve the newline itself
                } else {
                    $i--; // step back so the outer loop's i++ lands correctly
                }
                continue;
            }

            if ($ch === '\\' && $next === '%') {
                $out .= '\\%';
                $i++;
                continue;
            }

            if ($ch === '%') {
                $inGroup = !$inGroup;
                $out .= $ch;
                continue;
            }

            $out .= $ch;
        }

        return $out;
    }

    /**
     * Splits a single logical line into words, respecting `%...%` grouping
     * (with `\%` escaping to a literal `%`) and collapsing runs of
     * whitespace outside groups.
     *
     * @return string[]
     */
    public static function wordize(string $logical): array
    {
        $len = strlen($logical);
        $words = [];
        $current = '';
        $inGroup = false;
        $groupHasContent = false;

        for ($i = 0; $i < $len; $i++) {
            $ch = $logical[$i];
            $next = $i + 1 < $len ? $logical[$i + 1] : '';

            if ($ch === '\\' && $next === '%') {
                $current .= '%';
                $i++;
                $groupHasContent = true;
                continue;
            }

            if ($ch === '%') {
                if ($inGroup) {
                    $inGroup = false;
                } else {
                    $inGroup = true;
                    $groupHasContent = false;
                }
                continue;
            }

            if (!$inGroup && ctype_space($ch)) {
                if ($current !== '') {
                    $words[] = $current;
                    $current = '';
                }
                continue;
            }

            $current .= $ch;
            if ($inGroup) {
                $groupHasContent = true;
            }
        }

        if ($current !== '' || $inGroup) {
            $words[] = $current;
        }

        return array_values(array_filter($words, fn ($w) => $w !== ''));
    }
}
