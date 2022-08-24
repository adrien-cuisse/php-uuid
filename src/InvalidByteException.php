<?php

namespace AR7\Uuid;

use InvalidArgumentException;

final class InvalidByteException extends InvalidArgumentException
{
    public function __construct(private readonly int $byte, private readonly int $byteIndex)
    {
        parent::__construct(sprintf(
            "Invalid byte at index %d, got %d",
            $byte,
            $byteIndex
        ));
    }
}
