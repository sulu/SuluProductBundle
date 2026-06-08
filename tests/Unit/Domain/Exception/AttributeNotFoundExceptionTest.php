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
use Sulu\Product\Domain\Exception\AttributeNotFoundException;

#[CoversClass(AttributeNotFoundException::class)]
class AttributeNotFoundExceptionTest extends TestCase
{
    public function testGetCriteria(): void
    {
        $criteria = ['uuid' => 'some-uuid'];

        $exception = new AttributeNotFoundException($criteria);

        $this->assertSame($criteria, $exception->getCriteria());
    }

    public function testGetMessage(): void
    {
        $criteria = ['uuid' => 'some-uuid'];

        $exception = new AttributeNotFoundException($criteria);

        $this->assertSame(
            \sprintf('Attribute with criteria (%s) not found.', \json_encode($criteria)),
            $exception->getMessage()
        );
    }

    public function testGetPrevious(): void
    {
        $previous = new \Exception('previous');

        $exception = new AttributeNotFoundException([], $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }
}
