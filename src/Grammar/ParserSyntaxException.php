<?php
declare(strict_types=1);

namespace TSL\Grammar;

/** Internal control-flow exception carrying a structural ParseError; caught per-block by Parser. */
final class ParserSyntaxException extends \RuntimeException
{
    public function __construct(public ParseError $error)
    {
        parent::__construct($error->message);
    }
}
