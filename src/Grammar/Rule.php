<?php
declare(strict_types=1);

namespace TSL\Grammar;

/** One TSL rule: an action, the event it fires on, and its WITH predicates. */
final class Rule
{
    /** @param Predicate[] $predicates */
    public function __construct(
        public ActionInvocation $action,
        public string $event,
        public array $predicates = [],
        public ?int $sourceLine = null,
        public ?string $note = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'action' => $this->action->toArray(),
            'event' => $this->event,
            'predicates' => array_map(fn (Predicate $p) => $p->toArray(), $this->predicates),
            'note' => $this->note,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            ActionInvocation::fromArray($data['action']),
            (string) $data['event'],
            array_map(fn (array $p) => Predicate::fromArray($p), $data['predicates'] ?? []),
            null,
            isset($data['note']) && $data['note'] !== '' ? (string) $data['note'] : null,
        );
    }
}
