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
use Sulu\Product\Domain\Exception\InvalidProductAssociationException;

#[CoversClass(InvalidProductAssociationException::class)]
class InvalidProductAssociationExceptionTest extends TestCase
{
    public function testCarriesMessageAndHint(): void
    {
        $exception = new InvalidProductAssociationException('Unknown association type "x".', 'Use a listed key.');

        $this->assertSame('Unknown association type "x".', $exception->getMessage());
        $this->assertSame('Use a listed key.', $exception->getHint());
    }
}
