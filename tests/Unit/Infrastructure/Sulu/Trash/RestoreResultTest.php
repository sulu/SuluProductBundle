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
use PHPUnit\Framework\TestCase;
use Sulu\Product\Infrastructure\Sulu\Trash\RestoreResult;

#[CoversClass(RestoreResult::class)]
class RestoreResultTest extends TestCase
{
    public function testGetters(): void
    {
        $result = new RestoreResult('uuid');

        $this->assertSame('uuid', $result->getId());
    }
}
