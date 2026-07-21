<?php
declare(strict_types=1);

namespace TSL\Grammar;

/** A structural problem: the rule-block could not be parsed at all. */
final class ParseError
{
    public function __construct(
        public string $code,
        public string $message,
        public ?int $line = null,
        public ?string $snippet = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'severity' => 'error',
            'code' => $this->code,
            'message' => $this->message,
            'line' => $this->line,
            'snippet' => $this->snippet,
        ];
    }
}
