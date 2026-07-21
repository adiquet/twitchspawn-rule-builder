<?php
declare(strict_types=1);

namespace TSL\Grammar;

/** A rule parsed fine but is semantically suspicious (e.g. silently never fires). */
final class ParseWarning
{
    public function __construct(
        public string $code,
        public string $message,
        public ?int $line = null,
        public ?int $ruleIndex = null,
        public ?string $field = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'severity' => 'warning',
            'code' => $this->code,
            'message' => $this->message,
            'line' => $this->line,
            'ruleIndex' => $this->ruleIndex,
            'field' => $this->field,
        ];
    }
}
