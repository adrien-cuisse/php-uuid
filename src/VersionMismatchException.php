<?php

namespace AR7\Uuid;

use InvalidArgumentException;

final class VersionMismatchException extends InvalidArgumentException
{
    public function __construct(private readonly int $intendedVersion, private readonly int $actualVersion)
    {
        return parent::__construct("Expected version {$this->intendedVersion}, got {$this->actualVersion}");
    }
}
