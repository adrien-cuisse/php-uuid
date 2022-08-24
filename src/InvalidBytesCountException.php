<?php

namespace AR7\Uuid;

use InvalidArgumentException;

final class InvalidBytesCountException extends InvalidArgumentException
{
    public function __construct(private readonly Iterable $bytes)
    {
        parent::__construct('Expected 16 bytes, got ' . count($bytes));
    }
}
