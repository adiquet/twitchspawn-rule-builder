<?php
declare(strict_types=1);

namespace TSL\Grammar;

/**
 * Tokens -> Rule[]. Each blank-line-delimited block is parsed independently
 * so one malformed rule doesn't stop the rest of the file from importing.
 */
final class Parser
{
    /** Structural keywords that end a greedily-consumed word list (EXECUTE commands, OS_RUN script). */
    private const BOUNDARY_KEYWORDS = [
        'ON', 'WITH', 'DISPLAYING', 'OR', 'AND', 'ALL', 'TIMES', 'PERCENT', 'INTO', 'FROM', 'ONLY', 'CHANCE',
    ];

    /**
     * Every reserved single-word keyword TSL recognizes, structural + action + comparator. TwitchSpawn's
     * own parser treats these case-insensitively (confirmed empirically in-game — "on"/"On"/"ON" all work),
     * so any token that matches one of these case-insensitively is canonicalized to its uppercase form
     * before parsing, rather than being flagged as an error or silently failing to match.
     */
    private const RESERVED_KEYWORDS = [
        'ON', 'WITH', 'DISPLAYING', 'OR', 'AND', 'ALL', 'TIMES', 'PERCENT', 'INTO', 'FROM', 'ONLY', 'CHANCE',
        'NOTHING', 'INSTANTLY',
        'DROP', 'SUMMON', 'EXECUTE', 'THROW', 'CLEAR', 'SHUFFLE', 'CHANGE', 'EITHER', 'BOTH', 'FOR', 'WAIT',
        'REFLECT', 'OS_RUN',
        'IS', 'PREFIX', 'POSTFIX', 'CONTAINS', 'IN', 'RANGE',
        'CMD', 'POWERSHELL', 'BASH', 'LOCAL', 'REMOTE',
    ];

    private static function normalizeKeywordCase(array $words): array
    {
        return array_map(
            fn (string $w) => in_array(strtoupper($w), self::RESERVED_KEYWORDS, true) ? strtoupper($w) : $w,
            $words
        );
    }

    public static function parse(string $raw, string $profile): ParseResult
    {
        $blocks = Tokenizer::tokenize($raw);
        $result = new ParseResult();

        foreach ($blocks as $block) {
            $block->words = self::normalizeKeywordCase($block->words);
            try {
                [$rule, $warnings] = self::parseBlock($block, $profile);
                $result->rules[] = $rule;
                foreach ($warnings as $w) {
                    $result->warnings[] = $w;
                }
            } catch (ParserSyntaxException $e) {
                $err = $e->error;
                $err->line = $err->line ?? $block->startLine;
                $err->snippet = $err->snippet ?? $block->rawText;
                $result->errors[] = $err;
            }
        }

        return $result;
    }

    /** @return array{0:Rule,1:ParseWarning[]} */
    private static function parseBlock(RuleBlock $block, string $profile): array
    {
        $words = $block->words;
        $len = count($words);
        $warnings = [];

        $onIndex = null;
        $secondOnIndex = null;
        foreach ($words as $i => $w) {
            if ($w === 'ON') {
                if ($onIndex === null) {
                    $onIndex = $i;
                } else {
                    $secondOnIndex = $i;
                    break;
                }
            }
        }

        if ($onIndex === null) {
            throw new ParserSyntaxException(new ParseError(
                'MISSING_ON',
                "Couldn't find an ON <Event> clause in this rule.",
                $block->startLine,
                $block->rawText
            ));
        }

        $firstWithBeforeOn = null;
        for ($i = 0; $i < $onIndex; $i++) {
            if ($words[$i] === 'WITH') {
                $firstWithBeforeOn = $i;
                break;
            }
        }
        if ($firstWithBeforeOn !== null) {
            throw new ParserSyntaxException(new ParseError(
                'WITH_BEFORE_ON',
                'A WITH clause appears before ON — WITH clauses must come after ON <Event>.',
                $block->startLine,
                $block->rawText
            ));
        }

        if ($secondOnIndex !== null) {
            throw new ParserSyntaxException(new ParseError(
                'DUPLICATE_ON',
                "Found a second ON keyword in what looks like a single rule. This usually means two " .
                "rules got merged together — check whether a comment-only line between them is standing " .
                "in for a real blank line (comments don't separate rules; only a truly blank line does).",
                $block->startLine,
                $block->rawText
            ));
        }

        // --- Action portion: words[0 .. onIndex-1]
        $actionWords = array_slice($words, 0, $onIndex);
        if (empty($actionWords)) {
            throw new ParserSyntaxException(new ParseError(
                'MISSING_ACTION',
                'No action found before ON — every rule needs an action (DROP, SUMMON, EXECUTE, ...).',
                $block->startLine,
                $block->rawText
            ));
        }
        $pos = 0;
        $action = self::consumeAction($actionWords, $pos, count($actionWords), $profile);
        if ($pos !== count($actionWords)) {
            $leftover = implode(' ', array_slice($actionWords, $pos));
            throw new ParserSyntaxException(new ParseError(
                'UNEXPECTED_TOKENS',
                "Unexpected extra word(s) before ON: \"{$leftover}\". This can happen if a multi-word " .
                "value wasn't wrapped in %...%.",
                $block->startLine,
                $block->rawText
            ));
        }

        // --- Event + predicates: words[onIndex+1 .. end]
        $rest = array_slice($words, $onIndex + 1);
        $withIndices = [];
        foreach ($rest as $i => $w) {
            if ($w === 'WITH') {
                $withIndices[] = $i;
            }
        }

        $eventWords = $withIndices === [] ? $rest : array_slice($rest, 0, $withIndices[0]);
        if (empty($eventWords)) {
            throw new ParserSyntaxException(new ParseError(
                'MISSING_EVENT',
                'No event name found after ON.',
                $block->startLine,
                $block->rawText
            ));
        }
        $event = implode(' ', $eventWords);
        $canonicalEvent = GrammarDefinition::resolveEventName($event);
        if ($canonicalEvent === null) {
            $warnings[] = new ParseWarning(
                'UNKNOWN_EVENT',
                "\"{$event}\" isn't a recognized TwitchSpawn event name — check spelling/capitalization.",
                $block->startLine
            );
        } else {
            $event = $canonicalEvent;
        }

        $predicates = [];
        for ($i = 0; $i < count($withIndices); $i++) {
            $start = $withIndices[$i] + 1;
            $end = $i + 1 < count($withIndices) ? $withIndices[$i + 1] : count($rest);
            $predWords = array_slice($rest, $start, $end - $start);
            [$predicate, $predWarnings] = self::parsePredicate($predWords, $block);
            $predicates[] = $predicate;
            foreach ($predWarnings as $w) {
                $warnings[] = $w;
            }
        }

        return [new Rule($action, $event, $predicates, $block->startLine, $block->leadingComment), $warnings];
    }

    /** @return array{0:Predicate,1:ParseWarning[]} */
    private static function parsePredicate(array $predWords, RuleBlock $block): array
    {
        $warnings = [];
        if (count($predWords) < 2) {
            throw new ParserSyntaxException(new ParseError(
                'PREDICATE_INCOMPLETE',
                'A WITH clause is missing its comparator/value — expected "property comparator value".',
                $block->startLine,
                $block->rawText
            ));
        }

        $propertyWord = $predWords[0];
        $rest = array_slice($predWords, 1);

        // Try the multi-word comparator "IN RANGE" first.
        $comparator = null;
        $valueWords = [];
        if (count($rest) >= 1 && strtoupper($rest[0]) === 'IN' && count($rest) >= 2 && strtoupper($rest[1]) === 'RANGE') {
            $comparator = 'IN RANGE';
            $valueWords = array_slice($rest, 2);
        } elseif (count($rest) >= 1) {
            $comparator = $rest[0];
            $valueWords = array_slice($rest, 1);
        }

        if ($comparator === null || !isset(GrammarDefinition::COMPARATORS[$comparator])) {
            throw new ParserSyntaxException(new ParseError(
                'UNKNOWN_COMPARATOR',
                "\"{$comparator}\" isn't a recognized comparator (expected one of =, >, <, >=, <=, IS, PREFIX, POSTFIX, CONTAINS, IN RANGE).",
                $block->startLine,
                $block->rawText
            ));
        }

        if ($comparator === 'IN RANGE') {
            if (count($valueWords) === 2
                && str_starts_with($valueWords[0], '[')
                && str_ends_with($valueWords[1], ']')) {
                $min = ltrim(rtrim($valueWords[0], ','), '[');
                $max = rtrim($valueWords[1], ']');
                throw new ParserSyntaxException(new ParseError(
                    'RANGE_BRACKET_SPACE',
                    "IN RANGE's [min,max] can't contain a space — write it as one word, e.g. " .
                    "[{$min},{$max}] with no space, not \"{$valueWords[0]} {$valueWords[1]}\".",
                    $block->startLine,
                    $block->rawText
                ));
            }
            if (count($valueWords) !== 1) {
                throw new ParserSyntaxException(new ParseError(
                    'UNWRAPPED_MULTIWORD_LITERAL',
                    'IN RANGE expects exactly one [min,max] value.',
                    $block->startLine,
                    $block->rawText
                ));
            }
            if (!preg_match('/^\[[^,\[\]\s]+,[^,\[\]\s]+\]$/', $valueWords[0])) {
                throw new ParserSyntaxException(new ParseError(
                    'RANGE_BRACKET_MISSING',
                    "\"{$valueWords[0]}\" isn't a valid IN RANGE value — expected the form [min,max], e.g. [100,200].",
                    $block->startLine,
                    $block->rawText
                ));
            }
        } elseif (count($valueWords) !== 1) {
            if (count($valueWords) > 1) {
                throw new ParserSyntaxException(new ParseError(
                    'UNWRAPPED_MULTIWORD_LITERAL',
                    'This predicate has extra trailing word(s) — if the value contains spaces it must be ' .
                    'wrapped in %...%, e.g. WITH message CONTAINS %hello there%.',
                    $block->startLine,
                    $block->rawText
                ));
            }
            throw new ParserSyntaxException(new ParseError(
                'PREDICATE_INCOMPLETE',
                'A WITH clause is missing its value.',
                $block->startLine,
                $block->rawText
            ));
        }

        $value = $valueWords[0];
        $canonical = GrammarDefinition::resolveProperty($propertyWord);
        if ($canonical === null) {
            $warnings[] = new ParseWarning(
                'UNKNOWN_PROPERTY',
                "\"{$propertyWord}\" isn't a recognized predicate property.",
                $block->startLine
            );
            $canonical = $propertyWord;
        }

        return [new Predicate($canonical, $comparator, $value, $block->startLine), $warnings];
    }

    private static function consumeAction(array $words, int &$pos, int $len, string $profile): ActionInvocation
    {
        if ($pos >= $len) {
            throw new ParserSyntaxException(new ParseError('MISSING_ACTION', 'Expected an action here.'));
        }
        $name = $words[$pos];
        if (!isset(GrammarDefinition::ACTIONS[$name])) {
            throw new ParserSyntaxException(new ParseError('UNKNOWN_ACTION', "\"{$name}\" isn't a recognized action."));
        }
        $pos++;
        $def = GrammarDefinition::ACTIONS[$name];

        switch ($def['wraps']) {
            case 'single':
                return self::consumeWrapSingle($name, $words, $pos, $len, $profile);
            case 'multiple':
                return self::consumeWrapMultiple($name, $words, $pos, $len, $profile);
            default:
                $params = self::consumeSimpleParams($name, $words, $pos, $len, $profile);
                $displaying = self::consumeDisplaying($words, $pos, $len);
                return new ActionInvocation($name, $params, $displaying);
        }
    }

    private static function consumeWrapSingle(string $name, array $words, int &$pos, int $len, string $profile): ActionInvocation
    {
        if ($name === 'FOR') {
            $count = self::take($words, $pos, $len, 'count');
            self::expectKeyword($words, $pos, $len, 'TIMES');
            $child = self::consumeAction($words, $pos, $len, $profile);
            $displaying = self::consumeDisplaying($words, $pos, $len);
            return new ActionInvocation('FOR', ['count' => $count], $displaying, [['chance' => null, 'action' => $child]]);
        }

        // REFLECT [ONLY] <targets> <action>
        $only = false;
        if ($pos < $len && $words[$pos] === 'ONLY') {
            $only = true;
            $pos++;
        }
        $targets = self::take($words, $pos, $len, 'targets');
        $child = self::consumeAction($words, $pos, $len, $profile);
        $displaying = self::consumeDisplaying($words, $pos, $len);
        return new ActionInvocation(
            'REFLECT',
            ['targets' => $targets, 'only' => $only ? 'true' : 'false'],
            $displaying,
            [['chance' => null, 'action' => $child]]
        );
    }

    private static function consumeWrapMultiple(string $name, array $words, int &$pos, int $len, string $profile): ActionInvocation
    {
        $children = [];
        $instantly = false;

        if ($name === 'BOTH' && $pos < $len && $words[$pos] === 'INSTANTLY') {
            $instantly = true;
            $pos++;
        }

        do {
            $chance = null;
            if ($name === 'EITHER' && $pos < $len && $words[$pos] === 'CHANCE') {
                $pos++;
                $chanceWord = self::take($words, $pos, $len, 'chance percent');
                self::expectKeyword($words, $pos, $len, 'PERCENT');
                $chance = (float) rtrim($chanceWord, '%');
            }
            $child = self::consumeAction($words, $pos, $len, $profile);
            $children[] = ['chance' => $chance, 'action' => $child];

            $joiner = $name === 'EITHER' ? 'OR' : 'AND';
            $continues = $pos < $len && $words[$pos] === $joiner;
            if ($continues) {
                $pos++;
            }
        } while ($continues);

        if ($pos < $len && $words[$pos] === 'ALL') {
            $pos++; // "[ALL] DISPLAYING" — ALL just means "show this message for every branch", generator emits DISPLAYING either way
        }

        $displaying = self::consumeDisplaying($words, $pos, $len);
        return new ActionInvocation($name, [], $displaying, $children, $instantly);
    }

    private static function consumeSimpleParams(string $name, array $words, int &$pos, int $len, string $profile): array
    {
        switch ($name) {
            case 'DROP':
                $itemToken = self::take($words, $pos, $len, 'item_id');
                [$itemId, $nbt] = self::splitItemNbt($itemToken);
                $amount = null;
                $metadata = null;
                if ($pos < $len && self::isNumberLike($words[$pos])) {
                    $amount = $words[$pos];
                    $pos++;
                    if ($pos < $len && self::isNumberLike($words[$pos])) {
                        if ($profile === GrammarDefinition::PROFILE_A) {
                            $metadata = $words[$pos];
                            $pos++;
                        } else {
                            throw new ParserSyntaxException(new ParseError(
                                'PROFILE_METADATA_MISMATCH',
                                'DROP has two trailing numbers (amount + metadata), which is 1.12.2-only syntax — ' .
                                'double check you picked the right Minecraft version for this file.'
                            ));
                        }
                    }
                }
                $params = ['item_id' => $itemId, 'amount' => $amount, 'nbt' => $nbt];
                if ($profile === GrammarDefinition::PROFILE_A) {
                    $params['metadata'] = $metadata;
                }
                return $params;

            case 'SUMMON':
                $entityId = self::take($words, $pos, $len, 'entity_id');
                $coords = null;
                if ($pos + 2 < $len
                    && self::isCoordLike($words[$pos])
                    && self::isCoordLike($words[$pos + 1])
                    && self::isCoordLike($words[$pos + 2])) {
                    $coords = $words[$pos] . ' ' . $words[$pos + 1] . ' ' . $words[$pos + 2];
                    $pos += 3;
                }
                $nbt = null;
                if ($pos < $len && !in_array($words[$pos], self::BOUNDARY_KEYWORDS, true)) {
                    $nbt = $words[$pos];
                    $pos++;
                }
                return ['entity_id' => $entityId, 'coords' => $coords, 'nbt' => $nbt];

            case 'EXECUTE':
                $commands = self::takeGreedyList($words, $pos, $len);
                if (empty($commands)) {
                    throw new ParserSyntaxException(new ParseError('MISSING_PARAM', 'EXECUTE needs at least one command.'));
                }
                return ['commands' => $commands];

            case 'THROW':
            case 'CLEAR':
                $slot = self::consumeSlotOrFromClause($words, $pos, $len);
                return ['slot' => $slot];

            case 'SHUFFLE':
                if ($pos < $len && strtolower($words[$pos]) === 'slot' && $pos + 2 < $len) {
                    $pos++;
                    $min = self::take($words, $pos, $len, 'min');
                    $max = self::take($words, $pos, $len, 'max');
                    return ['inventoryOrRange' => "slot {$min} {$max}"];
                }
                $inv = self::take($words, $pos, $len, 'inventory_name');
                return ['inventoryOrRange' => $inv];

            case 'CHANGE':
                $slot = self::consumeSlotOrFromClause($words, $pos, $len);
                self::expectKeyword($words, $pos, $len, 'INTO');
                $intoItemToken = self::take($words, $pos, $len, 'into_item_id');
                [$intoItem, $nbt] = self::splitItemNbt($intoItemToken);
                $amount = null;
                $metadata = null;
                if ($pos < $len && self::isNumberLike($words[$pos])) {
                    $amount = $words[$pos];
                    $pos++;
                    if ($pos < $len && self::isNumberLike($words[$pos])) {
                        if ($profile === GrammarDefinition::PROFILE_A) {
                            $metadata = $words[$pos];
                            $pos++;
                        } else {
                            throw new ParserSyntaxException(new ParseError(
                                'PROFILE_METADATA_MISMATCH',
                                'CHANGE has two trailing numbers (amount + metadata), which is 1.12.2-only syntax — ' .
                                'double check you picked the right Minecraft version for this file.'
                            ));
                        }
                    }
                }
                $params = ['slot' => $slot, 'into_item_id' => $intoItem, 'amount' => $amount, 'nbt' => $nbt];
                if ($profile === GrammarDefinition::PROFILE_A) {
                    $params['metadata'] = $metadata;
                }
                return $params;

            case 'WAIT':
                $amount = self::take($words, $pos, $len, 'amount');
                $unit = self::take($words, $pos, $len, 'unit');
                return ['amount' => $amount, 'unit' => $unit];

            case 'OS_RUN':
                $target = self::take($words, $pos, $len, 'target');
                $shell = self::take($words, $pos, $len, 'shell');
                $script = self::takeGreedyList($words, $pos, $len);
                if (empty($script)) {
                    throw new ParserSyntaxException(new ParseError('MISSING_PARAM', 'OS_RUN needs a script.'));
                }
                return ['target' => $target, 'shell' => $shell, 'script' => $script];

            case 'NOTHING':
            default:
                return [];
        }
    }

    private static function consumeSlotOrFromClause(array $words, int &$pos, int $len): string
    {
        if ($pos < $len && ctype_digit($words[$pos]) && $pos + 2 < $len && $words[$pos + 1] === 'FROM') {
            $idx = $words[$pos];
            $inv = $words[$pos + 2];
            $pos += 3;
            return "slot {$idx} FROM {$inv}";
        }
        return self::take($words, $pos, $len, 'slot');
    }

    private static function consumeDisplaying(array $words, int &$pos, int $len): ?array
    {
        if ($pos >= $len || $words[$pos] !== 'DISPLAYING') {
            return null;
        }
        $pos++;
        if ($pos < $len && $words[$pos] === 'NOTHING') {
            $pos++;
            return ['mode' => 'nothing', 'value' => null];
        }
        $value = self::take($words, $pos, $len, 'displaying value');
        return ['mode' => 'text', 'value' => $value];
    }

    private static function take(array $words, int &$pos, int $len, string $what): string
    {
        if ($pos >= $len) {
            throw new ParserSyntaxException(new ParseError('MISSING_PARAM', "Expected a value for {$what}."));
        }
        return $words[$pos++];
    }

    private static function expectKeyword(array $words, int &$pos, int $len, string $keyword): void
    {
        if ($pos >= $len || $words[$pos] !== $keyword) {
            throw new ParserSyntaxException(new ParseError('EXPECTED_KEYWORD', "Expected the keyword {$keyword} here."));
        }
        $pos++;
    }

    /** @return string[] */
    private static function takeGreedyList(array $words, int &$pos, int $len): array
    {
        $out = [];
        while ($pos < $len && !in_array($words[$pos], self::BOUNDARY_KEYWORDS, true)) {
            $out[] = $words[$pos];
            $pos++;
        }
        return $out;
    }

    /**
     * DROP/CHANGE embed NBT directly after the item ID with no separating space
     * (e.g. "minecraft:diamond_sword{Enchantments:[...]}"), unlike SUMMON's nbt
     * which is its own trailing word. Splits that single token back into the two
     * structured fields the builder UI edits separately.
     *
     * @return array{0:string,1:?string}
     */
    private static function splitItemNbt(string $token): array
    {
        $bracePos = strpos($token, '{');
        if ($bracePos === false) {
            return [$token, null];
        }
        return [substr($token, 0, $bracePos), substr($token, $bracePos)];
    }

    private static function isNumberLike(string $w): bool
    {
        return (bool) preg_match('/^-?\d+(\.\d+)?[fFdD]?$/', $w) || self::isPlaceholder($w);
    }

    private static function isCoordLike(string $w): bool
    {
        return (bool) preg_match('/^~?-?\d*\.?\d*$/', $w) && $w !== '';
    }

    private static function isPlaceholder(string $w): bool
    {
        return (bool) preg_match('/^\$\{[^}]+\}$/', $w);
    }
}
