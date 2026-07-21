<?php
declare(strict_types=1);

namespace TSL\Grammar;

/** A blank-line-delimited chunk of the .tsl file, tokenized into words, ready for the Parser. */
final class RuleBlock
{
    /**
     * @param int[] $sourceLines
     * @param string[] $words
     */
    public function __construct(
        public int $startLine,
        public array $sourceLines,
        public array $words,
        public string $rawText,
        public ?string $leadingComment = null,
    ) {
    }
}
