<?php

namespace Tests\AR7\Uuid;

use AR7\Uuid\InvalidByteException;
use AR7\Uuid\InvalidBytesCountException;
use AR7\Uuid\InvalidUuidException;
use AR7\Uuid\Uuid;
use AR7\Uuid\VariantMismatchException;
use AR7\Uuid\VersionMismatchException;

use PHPUnit\Framework\TestCase;
use function PHPUnit\Framework\assertSame;

final class UuidTest extends TestCase
{
    private const UUID_SIZE_IN_BYTES = 16;

    private static function bytes(
        array $timestamp = [0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x0, 0x00],
        int $version = 0,
        array $clockSequence = [0x0, 0x00],
        int $variant = 0,
        array $node = [0x00, 0x00, 0x00, 0x00, 0x00, 0x00],
    ): array {
        return [
            ...array_slice($timestamp, 0, 6),
            ($version << 4) | ($timestamp[6] & 0x0f),
            $timestamp[7],
            ($variant << 4) | ($clockSequence[0] & 0x0f),
            $clockSequence[1],
            ...$node
        ];
    }

    public static function bytesCount(): Iterable
    {
        yield 'not enough' => [
            array_pad([], self::UUID_SIZE_IN_BYTES - 1, 0x00)
        ];
        yield 'too many' => [
            array_pad([], self::UUID_SIZE_IN_BYTES + 1, 0x00)
        ];
    }

    /**
     * @test
     * @dataProvider bytesCount
     */
    public function requires_16_bytes(array $bytes): void
    {
        // given a number of bytes different than UUID_SIZE_IN_BYTES

        // when trying to create an uuid from them
        $instantiation = fn () => new Uuid($bytes);

        // then it should fail
        $this->expectException(InvalidBytesCountException::class);
        $instantiation();
    }

    public function invalidBytes(): Iterable
    {
        for ($byteIndex = 0; $byteIndex < self::UUID_SIZE_IN_BYTES; $byteIndex++)
        {
            $bytes = array_pad([], self::UUID_SIZE_IN_BYTES, 0x00);
            $bytes[$byteIndex] = rand(256, PHP_INT_MAX);
            yield "overflowing byte at index {$byteIndex}" => [$bytes, $byteIndex];

            $bytes = array_pad([], self::UUID_SIZE_IN_BYTES, 0x00);
            $bytes[$byteIndex] = rand(PHP_INT_MIN, -1);
            yield "negative byte at index {$byteIndex}" => [$bytes, $byteIndex];
        }
    }

    /**
     * @test
     * @dataProvider invalidBytes
     */
    public function requires_bytes(array $invalidBytes): void
    {
        // given bytes, with an invalid one

        // when trying to create an uuid from them
        $instantiation = fn () => new Uuid($invalidBytes);

        // then it should fail
        $this->expectException(InvalidByteException::class);
        $instantiation();
    }

    public static function format(): Iterable
    {
        yield 'nil uuid' => [
            self::bytes(),
            '00000000-0000-0000-0000-000000000000'
        ];
        yield 'all digits' => [
            [0x01, 0x23, 0x45, 0x67, 0x89, 0xab, 0xcd, 0xef, 0x01, 0x23, 0x45, 0x67, 0x89, 0xab, 0xcd, 0xef],
            '01234567-89ab-cdef-0123-456789abcdef',
        ];
    }

    /**
     * @test
     * @dataProvider format
     */
    public function is_rfc_compliant(array $bytes, string $expectedRfcFormat): void
    {
        // given an uuid
        $uuid = new Uuid($bytes);

        // when checking its string representation
        $representation = (string) $uuid;

        // then it should be RFC compliant
        assertSame($expectedRfcFormat, $representation);
    }

    /**
     * @test
     */
    public function requires_rfc_compliant_string(): void
    {
        // given an uuid-string which is not RFC compliant
        $format = "invalid format";

        // when trying to make an instance of it
        $instantiation = fn () => Uuid::fromString($format, 0, 0);

        // then it should fail
        $this->expectException(InvalidUuidException::class);
        $instantiation();
    }

    /**
     * @test
     */
    public function creates_from_string(): void
    {
        // given an uuid made from a string
        $expectedFormat = 'ffffffff-ffff-ffff-ffff-ffffffffffff';
        $uuid = Uuid::fromString($expectedFormat, 0xf, 0xf);

        // when checking its format
        $actualFormat = (string) $uuid;

        // then it should be the expected one
        assertSame($expectedFormat, $actualFormat);
    }

    /**
     * @test
     */
    public function requires_matching_version(): void
    {
        // given an uuid-string, and a non-matching version
        $format = '00000000-0000-5000-0000-000000000000';
        $invalidVersion = 0;

        // when trying to make an instance from them
        $instantiation = fn () => Uuid::fromString($format, $invalidVersion, 0);

        // then it should fail
        $this->expectException(VersionMismatchException::class);
        $instantiation();
    }

    /**
     * @test
     */
    public function requires_matching_variant(): void
    {
        // given an uuid-string, and a non-matching variant
        $format = "00000000-0000-0000-8000-000000000000";
        $invalidVariant = 0;

        // when trying to make an instance from them
        $instantiation = fn () => Uuid::fromString($format, 0, $invalidVariant);

        // then it should fail
        $this->expectException(VariantMismatchException::class);
        $instantiation();
    }

    public static function timestamp(): Iterable
    {
        $timestamp = 240551012960000000;

        yield 'from bytes' => [
            new Uuid(self::bytes(timestamp: [0x2d, 0xd7, 0x58, 0x00, 0x9b, 0xdf, 0x03, 0x56])),
            $timestamp,
        ];
        yield 'from string' => [
            Uuid::fromString('2dd75800-9bdf-0356-0000-000000000000', 0, 0),
            $timestamp,
        ];
    }

    /**
     * @test
     * @dataProvider timestamp
     */
    public function parses_timestamp_from_gregorian_calendar(Uuid $uuid, int $expectedTimestamp): void
    {
        // given an uuid issued on a specific date

        // when checking its timestamp
        $actualTimestamp = $uuid->timestamp();

        // then it should be the expected one
        assertSame($expectedTimestamp, $actualTimestamp);
    }

    public static function creationDate(): Iterable
    {
        /** extracted from {@link https://www.famkruithof.net/uuid/uuidgen?typeReq=-1} */
        $dateTime = '23/01/2345 12:34:56';

        yield 'from bytes' => [
            new Uuid(self::bytes(timestamp: [0x2d, 0xd7, 0x58, 0x00, 0x9b, 0xdf, 0x03, 0x56], version: 1, variant: 8)),
            $dateTime,
        ];
        yield 'from string' => [
            Uuid::fromString('2dd75800-9bdf-1356-8000-000000000000', 1, 8),
            $dateTime,
        ];
    }

    /**
     * @test
     * @dataProvider creationDate
     */
    public function parses_creation_date(Uuid $uuid, string $expectedDateTime): void
    {
        // given an uuid issued on a specific date

        // when checking its actual creation date
        $actualCreationDate = $uuid->creationDate();

        // then it should be the expected one
        assertSame($expectedDateTime, $actualCreationDate->format('d/m/Y H:i:s'));
    }

    public static function version(): Iterable
    {
        for ($version = 0x00; $version <= 0xf; $version++)
        {
            yield "version {$version} from bytes" => [
                new Uuid(self::bytes(version: $version)),
                $version,
            ];

            $versionDigit = sprintf('%x', $version);
            yield "version {$version} from string" => [
                Uuid::fromString("00000000-0000-{$versionDigit}000-0000-000000000000", $version, 0),
                $version,
            ];
        }
    }

    /**
     * @test
     * @dataProvider version
     */
    public function parses_version(Uuid $uuid, int $expectedVersion): void
    {
        // given an uuid issued with a specific version

        // when checking its actual version
        $actualVersion = $uuid->version();

        // then it should be the expected one
        assertSame($expectedVersion, $actualVersion);
    }

    public static function clockSequence(): Iterable
    {
        $clockSequence = (0x02) << 4 | (0x34);
        yield 'from increasing bytes' => [
            new Uuid(self::bytes(clockSequence: [0x02, 0x34])),
            $clockSequence,
        ];
        yield 'from increasing string' => [
            Uuid::fromString('00000000-0000-0000-0234-000000000000', 0, 0),
            $clockSequence,
        ];

        $clockSequence = (0xd) << 4 | (0xef);
        yield 'from decreasing bytes' => [
            new Uuid(self::bytes(clockSequence: [0x0d, 0xef])),
            $clockSequence,
        ];
        yield 'from decreasing string' => [
            Uuid::fromString('00000000-0000-0000-0def-000000000000', 0, 0),
            $clockSequence,
        ];
    }

    /**
     * @test
     * @dataProvider clockSequence
     */
    public function parses_clock_sequence(Uuid $uuid, int $expectedClockSequence): void
    {
        // given an uuid issued with a specific clock-sequence

        // when checking its clock-sequence
        $actualClockSequence = $uuid->clockSequence();

        // then it should be the expected one
        assertSame($actualClockSequence, $expectedClockSequence);
    }

    public static function variant(): Iterable
    {
        for ($variant = 0x00; $variant <= 0xf; $variant++)
        {
            yield "variant {$variant} from bytes" => [
                new Uuid(self::bytes(variant: $variant)),
                $variant,
            ];

            $variantDigit = sprintf('%x', $variant);
            yield "variant {$variant} from string" => [
                Uuid::fromString("00000000-0000-0000-{$variantDigit}000-000000000000", 0, $variant),
                $variant,
            ];
        }
    }

    /**
     * @test
     * @dataProvider variant
     */
    public function parses_variant(Uuid $uuid, int $expectedVariant): void
    {
        // given an uuid issued with a specific variant

        // when checking its actual variant
        $actualVariant = $uuid->variant();

        // then it should be the expected one
        assertSame($expectedVariant, $actualVariant);
    }

    public static function node(): Iterable
    {
        $node = [0x12, 0x34, 0x56, 0x78, 0x9a, 0xbc];

        yield 'from bytes' => [
            new Uuid(self::bytes(node: [0x12, 0x34, 0x56, 0x78, 0x9a, 0xbc])),
            $node,
        ];
        yield 'from string' => [
            Uuid::fromString('00000000-0000-0000-0000-123456789abc', 0, 0),
            $node,
        ];
    }

    /**
     * @test
     * @dataProvider node
     */
    public function parses_node(Uuid $uuid, array $expectedNodeBytes): void
    {
        // given an uuid issued with a specific node

        // when checking its actual node
        $actualNodeBytes = $uuid->node();

        assertSame($expectedNodeBytes, $actualNodeBytes);
    }
}
