<?php

namespace AR7\Uuid;

use InvalidArgumentException;

final class VariantMismatchException extends InvalidArgumentException
{
    public function __construct(private readonly int $intendedVariant, private readonly int $actualVariant)
    {
        return parent::__construct("Expected variant {$this->intendedVariant}, got {$this->actualVariant}");
    }
}
