<?php

declare(strict_types=1);

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Product\Tests\Unit\Infrastructure\Sulu\Trash;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sulu\Product\Infrastructure\Sulu\Trash\FamilyAttributeModeRestoreData;

#[CoversClass(FamilyAttributeModeRestoreData::class)]
class FamilyAttributeModeRestoreDataTest extends TestCase
{
    /**
     * @return iterable<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function provideRestoreData(): iterable
    {
        yield 'mode' => [['mode' => 'overridable'], 'overridable'];
        yield 'mode wins over the legacy flag' => [['mode' => 'shared', 'variantSpecific' => true], 'shared'];
        yield 'legacy variant specific flag' => [['variantSpecific' => true], 'variantSpecific'];
        yield 'legacy shared flag' => [['variantSpecific' => false], 'shared'];
        yield 'unknown mode falls back to the flag' => [['mode' => 'everywhere', 'variantSpecific' => true], 'variantSpecific'];
        yield 'nothing stored' => [[], 'shared'];
    }

    /**
     * @param array<string, mixed> $data
     */
    #[DataProvider('provideRestoreData')]
    public function testMode(array $data, string $expected): void
    {
        $this->assertSame($expected, FamilyAttributeModeRestoreData::mode($data));
    }
}
