<?php
declare(strict_types=1);

namespace TSL\Grammar;

/**
 * Semantic pass over successfully-parsed Rule[]: nothing here is a syntax
 * error, but each check flags something that's likely a mistake — most
 * importantly, a predicate property that doesn't apply to its event, which
 * TwitchSpawn accepts silently and then the rule just never fires.
 */
final class Validator
{
    /** @param Rule[] $rules @return ParseWarning[] */
    public static function validate(array $rules, string $profile): array
    {
        $warnings = [];

        foreach ($rules as $ruleIndex => $rule) {
            if (isset(GrammarDefinition::EVENTS[$rule->event])) {
                if (!GrammarDefinition::isEventInProfile($rule->event, $profile)) {
                    $warnings[] = new ParseWarning(
                        'EVENT_NOT_IN_PROFILE',
                        "\"{$rule->event}\" isn't available on the selected Minecraft version.",
                        $rule->sourceLine,
                        $ruleIndex,
                        'event'
                    );
                }
                foreach ($rule->predicates as $predicate) {
                    if (isset(GrammarDefinition::PROPERTIES[$predicate->property])
                        && !GrammarDefinition::eventAllowsProperty($rule->event, $predicate->property)) {
                        $warnings[] = new ParseWarning(
                            'INVALID_PROPERTY_FOR_EVENT',
                            "\"{$predicate->property}\" isn't a valid property for \"{$rule->event}\" — this " .
                            "predicate won't cause a parse error, but the rule will silently never fire.",
                            $predicate->sourceLine ?? $rule->sourceLine,
                            $ruleIndex,
                            'predicate.' . $predicate->property
                        );
                    }
                }
            }

            self::walkActions($rule->action, function (ActionInvocation $a) use (&$warnings, $ruleIndex, $rule, $profile) {
                if ($a->displaying !== null && $a->displaying['mode'] === 'nothing' && $profile === GrammarDefinition::PROFILE_A) {
                    $warnings[] = new ParseWarning(
                        'DISPLAYING_NOTHING_PROFILE_A',
                        'DISPLAYING NOTHING (message suppression) is not supported on Minecraft 1.12.2.',
                        $rule->sourceLine,
                        $ruleIndex,
                        'displaying'
                    );
                }

                if ($a->displaying !== null && $a->displaying['mode'] === 'text') {
                    $text = trim((string) $a->displaying['value']);
                    if ($text === '' || $text[0] !== '[' || substr($text, -1) !== ']') {
                        $warnings[] = new ParseWarning(
                            'DISPLAYING_MISSING_BRACKETS',
                            "DISPLAYING's message must be a JSON array wrapped in [ ] — e.g. " .
                            'DISPLAYING %[{text:"Hello!"}]%. Got "' . $a->displaying['value'] . '", which ' .
                            "TwitchSpawn won't be able to read as a text component and will likely error or show nothing.",
                            $rule->sourceLine,
                            $ruleIndex,
                            'displaying'
                        );
                    } else {
                        $bracketIssue = self::findBracketMismatch($text);
                        if ($bracketIssue !== null) {
                            $warnings[] = new ParseWarning(
                                'DISPLAYING_UNBALANCED_BRACKETS',
                                "DISPLAYING's JSON array has {$bracketIssue['message']}, right after " .
                                "\"{$bracketIssue['context']}\". Suggested fix: DISPLAYING " .
                                Generator::wrapWord($bracketIssue['fixed']),
                                $rule->sourceLine,
                                $ruleIndex,
                                'displaying'
                            );
                        } else {
                            foreach (self::findDisplayingContentIssues($text) as $issue) {
                                $warnings[] = new ParseWarning(
                                    $issue['code'],
                                    $issue['message'],
                                    $rule->sourceLine,
                                    $ruleIndex,
                                    'displaying'
                                );
                            }
                        }
                    }
                }

                $nbtValue = $a->params['nbt'] ?? null;
                if ($nbtValue !== null && trim($nbtValue) !== '') {
                    $bracketIssue = self::findBracketMismatch($nbtValue);
                    if ($bracketIssue !== null) {
                        $warnings[] = new ParseWarning(
                            'NBT_UNBALANCED_BRACKETS',
                            "This action's NBT data has {$bracketIssue['message']}, right after " .
                            "\"{$bracketIssue['context']}\". Suggested fix: {$bracketIssue['fixed']}",
                            $rule->sourceLine,
                            $ruleIndex,
                            'nbt'
                        );
                    }

                    $itemCloseIssue = self::findPrematureItemStackClose($nbtValue);
                    if ($itemCloseIssue !== null) {
                        $warnings[] = new ParseWarning(
                            'NBT_ITEM_STACK_CLOSED_EARLY',
                            $itemCloseIssue,
                            $rule->sourceLine,
                            $ruleIndex,
                            'nbt'
                        );
                    }
                }

                if ($a->action === 'EITHER' && !empty($a->children)) {
                    $chances = array_map(fn ($c) => $c['chance'], $a->children);
                    $anySet = count(array_filter($chances, fn ($c) => $c !== null)) > 0;
                    $allSet = count(array_filter($chances, fn ($c) => $c !== null)) === count($chances);
                    if ($anySet && !$allSet) {
                        $warnings[] = new ParseWarning(
                            'EITHER_CHANCE_SUM',
                            'Some EITHER branches specify CHANCE and others don\'t — either give every branch a chance, or none.',
                            $rule->sourceLine,
                            $ruleIndex,
                            'action'
                        );
                    } elseif ($allSet) {
                        $sum = array_sum($chances);
                        if (abs($sum - 100.0) > 0.01) {
                            $warnings[] = new ParseWarning(
                                'EITHER_CHANCE_SUM',
                                "EITHER branch chances add up to {$sum}%, not 100%.",
                                $rule->sourceLine,
                                $ruleIndex,
                                'action'
                            );
                        }
                    }
                }
            });
        }

        return $warnings;
    }

    private static function walkActions(ActionInvocation $a, callable $visit): void
    {
        $visit($a);
        foreach ($a->children as $child) {
            self::walkActions($child['action'], $visit);
        }
    }

    /**
     * Stack-based { }/[ ] balance check that respects "..." string content (with \" escaping),
     * used for both NBT data and DISPLAYING's JSON array. Returns null if every bracket is
     * properly matched and closed, or on the first problem found: a human-readable description,
     * a few characters of context leading up to the problem so the user can locate it, and a
     * best-guess corrected version of the whole string with the bracket fixed.
     *
     * @return array{message:string,context:string,fixed:string}|null
     */
    private static function findBracketMismatch(string $s): ?array
    {
        $closerFor = ['}' => '{', ']' => '['];
        $openerCloses = ['{' => '}', '[' => ']'];
        $stack = [];
        $inString = false;
        $len = strlen($s);

        for ($i = 0; $i < $len; $i++) {
            $ch = $s[$i];
            if ($inString) {
                if ($ch === '\\') {
                    $i++; // skip escaped character, including \"
                } elseif ($ch === '"') {
                    $inString = false;
                }
                continue;
            }
            if ($ch === '"') {
                $inString = true;
            } elseif ($ch === '{' || $ch === '[') {
                $stack[] = $ch;
            } elseif ($ch === '}' || $ch === ']') {
                if (empty($stack)) {
                    return [
                        'message' => "an extra \"{$ch}\" with no matching opening bracket",
                        'context' => self::contextBefore($s, $i),
                        'fixed' => substr($s, 0, $i) . substr($s, $i + 1),
                    ];
                }
                $top = array_pop($stack);
                if ($top !== $closerFor[$ch]) {
                    // Most likely cause: the $top opener was never closed before $ch showed up —
                    // insert its missing closer right where it should have gone, immediately before $ch.
                    return [
                        'message' => "a \"{$ch}\" that doesn't match the most recently opened \"{$top}\"",
                        'context' => self::contextBefore($s, $i),
                        'fixed' => substr($s, 0, $i) . $openerCloses[$top] . substr($s, $i),
                    ];
                }
            }
        }

        if (!empty($stack)) {
            $reversed = array_reverse($stack); // most-recently-opened first, i.e. LIFO close order
            $unclosed = implode('', $reversed);
            $plural = strlen($unclosed) > 1 ? 's' : '';
            $closers = implode('', array_map(fn (string $c) => $openerCloses[$c], $reversed));
            return [
                'message' => "unclosed bracket{$plural} (\"{$unclosed}\") — every {, [ needs a matching }, ]",
                'context' => self::contextBefore($s, $len),
                'fixed' => $s . $closers,
            ];
        }

        return null;
    }

    /**
     * Heuristic for a common item-stack mistake, generic to Minecraft NBT rather than tied to
     * any specific item/entity: an item stack is always shaped {id:..., Count:..., tag:{...}}
     * as one compound, so an {id:...} that closes immediately and is followed right away by
     * ",Count" or ",tag" almost always means the closing "}" landed one key too early — those
     * were meant to be siblings inside the same compound, not outside it. This only needs to
     * recognize that universal id/Count/tag shape, not any per-entity or per-item schema.
     */
    private static function findPrematureItemStackClose(string $s): ?string
    {
        $pattern = '/\{\s*id\s*:\s*("(?:[^"\\\\]|\\\\.)*"|[^{},]+?)\s*(\})\s*,\s*(Count|tag)\b/';
        if (!preg_match($pattern, $s, $m, PREG_OFFSET_CAPTURE)) {
            return null;
        }
        $closeBracePos = (int) $m[2][1];
        $key = $m[3][0];
        $context = self::contextBefore($s, $closeBracePos + 1);

        return "An item stack's \"{id:...}\" closes right after \"{$context}\", but \"{$key}\" " .
            "follows immediately after. Item stacks are usually one compound shaped " .
            "{id:..., Count:..., tag:{...}} — if \"{$key}\" was meant to be part of the same " .
            "item, move this \"}\" to close after \"{$key}\"'s value instead, not right after \"id\".";
    }

    /** Trimmed snippet of $s leading up to (not including) $pos, for "right after ..." messages. */
    private static function contextBefore(string $s, int $pos, int $window = 24): string
    {
        $start = max(0, $pos - $window);
        $snippet = rtrim(substr($s, $start, $pos - $start));
        return ($start > 0 ? '…' : '') . $snippet;
    }

    /** Replaces the inside of every "..." literal (not the quotes themselves) with spaces, so
     *  braces/words that only appear inside quoted text don't get mistaken for JSON structure. */
    private static function maskQuotedStrings(string $s): string
    {
        $out = '';
        $inString = false;
        $len = strlen($s);
        for ($i = 0; $i < $len; $i++) {
            $ch = $s[$i];
            if ($inString) {
                if ($ch === '\\' && $i + 1 < $len) {
                    $out .= '  ';
                    $i++; // mask the escaped character too, e.g. the " in \"
                    continue;
                }
                if ($ch === '"') {
                    $inString = false;
                    $out .= $ch;
                    continue;
                }
                $out .= ' ';
                continue;
            }
            if ($ch === '"') {
                $inString = true;
            }
            $out .= $ch;
        }
        return $out;
    }

    /**
     * Heuristic checks over an already-bracket-balanced DISPLAYING array: flags text-component
     * objects with no key:"value" structure at all, and string values that aren't quoted — both
     * parse fine as TSL but produce JSON TwitchSpawn can't read as a chat/title message.
     *
     * @return array<array{code:string,message:string}>
     */
    private static function findDisplayingContentIssues(string $text): array
    {
        $issues = [];
        $seen = [];
        $add = function (string $code, string $message) use (&$issues, &$seen): void {
            $key = $code . '|' . $message;
            if (isset($seen[$key])) {
                return;
            }
            $seen[$key] = true;
            $issues[] = ['code' => $code, 'message' => $message];
        };

        // Blank out the inside of "..." string literals first: a brace or bare word that only
        // exists inside quoted text (e.g. a "${actor}" placeholder) isn't JSON structure, so it
        // shouldn't be mistaken for an unquoted value or a malformed text-component object.
        $text = self::maskQuotedStrings($text);

        if (preg_match_all('/\{([^{}]*)\}/', $text, $objMatches)) {
            foreach ($objMatches[1] as $body) {
                $trimmed = trim($body);
                if ($trimmed !== '' && !str_contains($trimmed, ':')) {
                    $add(
                        'DISPLAYING_INVALID_TEXT_COMPONENT',
                        "Found \"{{$trimmed}}\" — a text component needs key:\"value\" pairs, e.g. " .
                        "{text:\"{$trimmed}\"}, not just a bare word."
                    );
                }
            }
        }

        if (preg_match_all('/:\s*([A-Za-z][A-Za-z0-9_]*)\s*(?=[,}\]])/', $text, $valMatches)) {
            foreach (array_unique($valMatches[1]) as $word) {
                if (in_array(strtolower($word), ['true', 'false'], true)) {
                    continue;
                }
                $add(
                    'DISPLAYING_UNQUOTED_VALUE',
                    "The value \"{$word}\" isn't in quotes — string values need double quotes, e.g. " .
                    "\"{$word}\" not {$word}."
                );
            }
        }

        return $issues;
    }
}
