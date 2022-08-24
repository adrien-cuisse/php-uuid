<?php

namespace AR7\Uuid;

use InvalidArgumentException;

final class InvalidUuidException extends InvalidArgumentException
{
    public function __construct(private readonly string $format)
    {
        parent::__construct("UUID '{$format}' is not RFC compliant");
    }
}
