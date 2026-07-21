<?php
declare(strict_types=1);

namespace TSL\Grammar;

/**
 * One action call: DROP/SUMMON/.../EITHER/BOTH/etc, its raw param values
 * (already unwrapped of %...% grouping), an optional DISPLAYING clause, and
 * — for meta-actions — nested child ActionInvocations.
 */
final class ActionInvocation
{
    /**
     * @param array<string,?string> $params raw param name => value (string) or null if omitted
     * @param array{mode:string,value:?string}|null $displaying mode is 'text'|'nothing'
     * @param array<int,array{action:ActionInvocation,chance:?float}> $children
     */
    public function __construct(
        public string $action,
        public array $params = [],
        public ?array $displaying = null,
        public array $children = [],
        public bool $instantly = false,
    ) {
    }

    public function toArray(): array
    {
        return [
            'action' => $this->action,
            'params' => $this->params,
            'displaying' => $this->displaying,
            'instantly' => $this->instantly,
            'children' => array_map(
                fn (array $c) => ['chance' => $c['chance'], 'action' => $c['action']->toArray()],
                $this->children
            ),
        ];
    }

    public static function fromArray(array $data): self
    {
        $children = [];
        foreach ($data['children'] ?? [] as $c) {
            $children[] = [
                'chance' => isset($c['chance']) ? (float) $c['chance'] : null,
                'action' => self::fromArray($c['action']),
            ];
        }
        return new self(
            (string) $data['action'],
            (array) ($data['params'] ?? []),
            $data['displaying'] ?? null,
            $children,
            (bool) ($data['instantly'] ?? false),
        );
    }
}
