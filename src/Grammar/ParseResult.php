<?php
declare(strict_types=1);

namespace TSL\Grammar;

/** Everything Parser::parse() produces from one .tsl document. */
final class ParseResult
{
    /**
     * @param Rule[] $rules
     * @param ParseError[] $errors block-level, each carries the raw text of the unparsed block
     * @param ParseWarning[] $warnings soft issues found during parsing (unknown event/property names)
     */
    public function __construct(
        public array $rules = [],
        public array $errors = [],
        public array $warnings = [],
    ) {
    }

    public function toArray(): array
    {
        return [
            'rules' => array_map(fn (Rule $r) => $r->toArray(), $this->rules),
            'errors' => array_map(fn (ParseError $e) => $e->toArray(), $this->errors),
            'warnings' => array_map(fn (ParseWarning $w) => $w->toArray(), $this->warnings),
        ];
    }
}
