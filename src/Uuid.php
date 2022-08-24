<?php

namespace AR7\Uuid;

use DateInterval;
use DateTime;
use DateTimeInterface;
use DateTimeZone;

final class Uuid
{
    private readonly string $rfcFormat;

    /** @var int[] */
    private readonly array $timestampBytes;

    private readonly int $version;

    /** @var int[] */
    private readonly array $clockSequenceBytes;

    private readonly int $variant;

    /** @var int[] */
    private readonly array $nodeBytes;

    /**
     * @throws InvalidBytesCountException - if bytes count isn't 16
     * @throws InvalidByteException - if any byte is not in range [0;255]
     */
    public function __construct(private readonly array $bytes)
    {
        if (count($bytes) !== 16)
            throw new InvalidBytesCountException($bytes);

        foreach ($bytes as $byteIndex => $byte)
        {
            $isNotInByteRange = ($byte < 0) || ($byte > 255);
            if ($isNotInByteRange)
                throw new InvalidByteException($byte, $byteIndex);
        }

        $this->extractBytesGroups();
    }

    /**
     * @throws InvalidUuidException - if uuid is not RFC compliant
     * @throws VersionMismatchException - if version digit mismatches from expected version
     * @throws VariantMismatchException - if variant digit mismatches from expected variant
     */
    public static function fromString(string $uuid, int $intendedVersion, int $intendedVariant): self
    {
        $rfcValidationPattern = '/[0-9a-f]{8}(-[0-9a-f]{4}){3}-[0-9a-f]{12}/';
        if (!preg_match($rfcValidationPattern, $uuid))
            throw new InvalidUuidException($uuid);

        $bytes = self::getBytesFromString($uuid);
        $instance = new self($bytes);

        if ($instance->version !== $intendedVersion)
            throw new VersionMismatchException($intendedVersion, $instance->version);
        if ($instance->variant !== $intendedVariant)
            throw new VariantMismatchException($intendedVariant, $instance->variant);

        $instance->rfcFormat = $uuid;

        return $instance;
    }

    /**
     * @return int the count of 100-nanosecond intervals since 00:00:00.00, 15 October 1582
     * @see https://www.rfc-editor.org/rfc/rfc4122.html#section-4.1.4
     */
    public function timestamp(): int
    {
        // only 1 nibble, other one in byte is version, no shifting needed
        $timestamp = $this->timestampBytes[6];

        $orderedTimestampBytes = [
            $this->timestampBytes[7],
            ...array_slice($this->timestampBytes, 4, 2),
            ...array_slice($this->timestampBytes, 0, 4),
        ];

        foreach ($orderedTimestampBytes as $timestampByte)
        {
            $timestamp <<= 8;
            $timestamp |= $timestampByte;
        }

        return $timestamp;
    }

    public function creationDate(): DateTimeInterface
    {
        $timestamp = $this->timestamp();

        $nanoSeconds = $timestamp * 100;
        $seconds = $nanoSeconds / (1000 * 1000 * 1000);

        $gregorianCalendarStart = new DateTime(
            '1582-10-15 00:00:00',
            new DateTimeZone('UTC'),
        );

        return $gregorianCalendarStart->add(new DateInterval("PT{$seconds}S"));
    }

    public function version(): int
    {
        return $this->version;
    }

    public function clockSequence(): int
    {
        return ($this->clockSequenceBytes[0] << 4) | ($this->clockSequenceBytes[1]);
    }

    public function variant(): int
    {
        return $this->variant;
    }

    public function node(): Iterable
    {
        return $this->nodeBytes;
    }

    public function __toString(): string
    {
        if (!isset($this->rfcFormat))
            $this->rfcFormat = $this->rfcFormat();

        return $this->rfcFormat;
    }

    private static function getBytesFromString(string $uuid): array
    {
        $digits = str_replace('-', '', $uuid);
        $bytes = sscanf($digits, str_repeat('%02x', 16));

        return $bytes;
    }

    private function extractBytesGroups(): void
    {
        $this->timestampBytes = [
            ...array_slice($this->bytes, 0, 6),
            $this->bytes[6] & 0x0f,
            $this->bytes[7],
        ];
        $this->version = $this->bytes[6] >> 4;
        $this->clockSequenceBytes = [$this->bytes[8] & 0x0f, $this->bytes[9]];
        $this->variant = $this->bytes[8] >> 4;
        $this->nodeBytes = array_slice($this->bytes, 10);
    }

    private static function createBytesHexString(int ...$bytesGroup): string
    {
        $bytesHexString = [];

        foreach ($bytesGroup as $byte)
            $bytesHexString[] = sprintf('%02x', $byte);

        return implode('', $bytesHexString);
    }

    private function rfcFormat(): string
    {
        $bytesGroups = [
            array_slice($this->bytes, 0, 4),
            array_slice($this->bytes, 4, 2),
            array_slice($this->bytes, 6, 2),
            array_slice($this->bytes, 8, 2),
            array_slice($this->bytes, 10),
        ];

        $stringGroups = [];
        foreach ($bytesGroups as $byteGroup)
            $stringGroups[] = self::createBytesHexString(...$byteGroup);

        return implode('-', $stringGroups);
    }
}
