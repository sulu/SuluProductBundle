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

namespace Sulu\Product\Tests\Unit\Domain\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sulu\Product\Domain\Exception\AttributeSetNotFoundException;

#[CoversClass(AttributeSetNotFoundException::class)]
class AttributeSetNotFoundExceptionTest extends TestCase
{
    public function testMessageContainsCriteria(): void
    {
        $e = new AttributeSetNotFoundException(['uuid' => 'abc-123']);
        $this->assertStringContainsString('abc-123', $e->getMessage());
    }

    public function testGetCriteriaReturnsArray(): void
    {
        $e = new AttributeSetNotFoundException(['uuid' => 'abc-123']);
        $this->assertSame(['uuid' => 'abc-123'], $e->getCriteria());
    }
}
