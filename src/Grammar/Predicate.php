<?php
declare(strict_types=1);

namespace TSL\Grammar;

/** One WITH clause: canonical property name, comparator token, literal value. */
final class Predicate
{
    public function __construct(
        public string $property,
        public string $comparator,
        public string $value,
        public ?int $sourceLine = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'property' => $this->property,
            'comparator' => $this->comparator,
            'value' => $this->value,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self((string) $data['property'], (string) $data['comparator'], (string) $data['value']);
    }
}
