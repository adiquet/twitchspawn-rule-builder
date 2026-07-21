<?php
declare(strict_types=1);

namespace TSL\Grammar;

/**
 * Rule[] -> TSL text. Uses the same wrapping rules the Tokenizer/Parser
 * expect, so generate(parse(x)) reproduces x's meaning and parse(generate(x))
 * round-trips back to an equivalent structure.
 */
final class Generator
{
    /** @param Rule[] $rules */
    public static function generateRuleset(array $rules, string $profile): string
    {
        $out = [];
        foreach ($rules as $rule) {
            $out[] = self::generateRule($rule, $profile);
        }
        return implode("\n\n", $out) . ($out ? "\n" : '');
    }

    public static function generateRule(Rule $rule, string $profile): string
    {
        $lines = [];
        if ($rule->note !== null && trim($rule->note) !== '') {
            // A single-line comment directly above the rule, no blank line in between, so the
            // parser reattaches it as this rule's note on re-import (see Tokenizer::tokenize).
            $lines[] = '# ' . str_replace(["\r", "\n"], ' ', trim($rule->note));
        }
        $lines[] = self::renderAction($rule->action, $profile);
        $lines[] = ' ON ' . $rule->event;
        foreach ($rule->predicates as $predicate) {
            $lines[] = ' WITH ' . self::renderPredicate($predicate);
        }
        return implode("\n", $lines);
    }

    private static function renderPredicate(Predicate $p): string
    {
        $comparatorDef = GrammarDefinition::COMPARATORS[$p->comparator] ?? null;
        $value = ($comparatorDef && $comparatorDef['bracketed']) ? $p->value : self::wrapWord($p->value);
        return "{$p->property} {$p->comparator} {$value}";
    }

    private static function renderAction(ActionInvocation $a, string $profile): string
    {
        $def = GrammarDefinition::ACTIONS[$a->action] ?? null;
        $wraps = $def['wraps'] ?? 'none';
        $parts = [$a->action];

        if ($wraps === 'single') {
            if ($a->action === 'FOR') {
                $parts[] = $a->params['count'] ?? '1';
                $parts[] = 'TIMES';
                $parts[] = self::renderAction($a->children[0]['action'], $profile);
            } else { // REFLECT
                if (($a->params['only'] ?? 'false') === 'true') {
                    $parts[] = 'ONLY';
                }
                $parts[] = $a->params['targets'] ?? '*';
                $parts[] = self::renderAction($a->children[0]['action'], $profile);
            }
        } elseif ($wraps === 'multiple') {
            if ($a->action === 'BOTH' && $a->instantly) {
                $parts[] = 'INSTANTLY';
            }
            $joiner = $a->action === 'EITHER' ? ' OR ' : ' AND ';
            $branchStrs = [];
            foreach ($a->children as $child) {
                $s = '';
                if ($a->action === 'EITHER' && $child['chance'] !== null) {
                    $s .= 'CHANCE ' . self::formatNumber($child['chance']) . ' PERCENT ';
                }
                $s .= self::renderAction($child['action'], $profile);
                $branchStrs[] = $s;
            }
            $parts[] = implode($joiner, $branchStrs);
            if (($a->params['all'] ?? 'false') === 'true') {
                $parts[] = 'ALL';
            }
        } else {
            foreach (self::renderSimpleParams($a, $profile) as $token) {
                $parts[] = $token;
            }
        }

        if ($a->displaying !== null) {
            $parts[] = 'DISPLAYING';
            $parts[] = $a->displaying['mode'] === 'nothing' ? 'NOTHING' : self::wrapWord((string) $a->displaying['value']);
        }

        return implode(' ', $parts);
    }

    /** @return string[] */
    private static function renderSimpleParams(ActionInvocation $a, string $profile): array
    {
        $p = $a->params;
        switch ($a->action) {
            case 'DROP':
                $tokens = [self::wrapWord((string) ($p['item_id'] ?? '') . (string) ($p['nbt'] ?? ''))];
                if (!empty($p['amount'])) {
                    $tokens[] = $p['amount'];
                }
                if ($profile === GrammarDefinition::PROFILE_A && !empty($p['metadata'])) {
                    $tokens[] = $p['metadata'];
                }
                return $tokens;

            case 'SUMMON':
                $tokens = [self::wrapWord((string) ($p['entity_id'] ?? ''))];
                if (!empty($p['coords'])) {
                    $tokens = array_merge($tokens, explode(' ', $p['coords']));
                }
                if (!empty($p['nbt'])) {
                    $tokens[] = self::wrapWord($p['nbt']);
                }
                return $tokens;

            case 'EXECUTE':
                return array_map(fn ($c) => self::wrapWord($c), $p['commands'] ?? []);

            case 'THROW':
            case 'CLEAR':
                return [(string) ($p['slot'] ?? '')];

            case 'SHUFFLE':
                return [(string) ($p['inventoryOrRange'] ?? '')];

            case 'CHANGE':
                $tokens = [(string) ($p['slot'] ?? ''), 'INTO', self::wrapWord((string) ($p['into_item_id'] ?? '') . (string) ($p['nbt'] ?? ''))];
                if (!empty($p['amount'])) {
                    $tokens[] = $p['amount'];
                }
                if ($profile === GrammarDefinition::PROFILE_A && !empty($p['metadata'])) {
                    $tokens[] = $p['metadata'];
                }
                return $tokens;

            case 'WAIT':
                return [(string) ($p['amount'] ?? ''), (string) ($p['unit'] ?? '')];

            case 'OS_RUN':
                $tokens = [(string) ($p['target'] ?? ''), (string) ($p['shell'] ?? '')];
                foreach ($p['script'] ?? [] as $s) {
                    $tokens[] = self::wrapWord($s);
                }
                return $tokens;

            case 'NOTHING':
            default:
                return [];
        }
    }

    private static function formatNumber(float $n): string
    {
        return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.') ?: '0';
    }

    /** Wraps a value in %...% (escaping literal % as \%) if it contains whitespace, %, or is empty. */
    public static function wrapWord(string $value): string
    {
        if ($value === '' || preg_match('/[\s%]/', $value)) {
            return '%' . str_replace('%', '\%', $value) . '%';
        }
        return $value;
    }
}
