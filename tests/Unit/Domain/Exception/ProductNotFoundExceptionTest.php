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
use Sulu\Product\Domain\Exception\ProductNotFoundException;
use Sulu\Product\Domain\Model\ProductInterface;

#[CoversClass(ProductNotFoundException::class)]
class ProductNotFoundExceptionTest extends TestCase
{
    public function testGetModel(): void
    {
        $exception = new ProductNotFoundException(['uuid' => 'some-uuid']);

        $this->assertSame(ProductInterface::class, $exception->getModel());
    }

    public function testGetFilters(): void
    {
        $filters = ['uuid' => 'some-uuid', 'locale' => 'en'];

        $exception = new ProductNotFoundException($filters);

        $this->assertSame($filters, $exception->getFilters());
    }

    public function testGetMessageWithScalarFilters(): void
    {
        $filters = ['uuid' => 'some-uuid'];

        $exception = new ProductNotFoundException($filters);

        $this->assertSame(
            \sprintf(
                'Model "%s" with "uuid" %s not found',
                ProductInterface::class,
                \json_encode('some-uuid')
            ),
            $exception->getMessage()
        );
    }

    public function testGetMessageWithMultipleFilters(): void
    {
        $filters = ['uuid' => 'some-uuid', 'locale' => 'en'];

        $exception = new ProductNotFoundException($filters);

        $this->assertSame(
            \sprintf(
                'Model "%s" with "uuid" %s and "locale" %s not found',
                ProductInterface::class,
                \json_encode('some-uuid'),
                \json_encode('en')
            ),
            $exception->getMessage()
        );
    }

    public function testGetMessageWithObjectFilter(): void
    {
        $object = new \stdClass();
        $filters = ['parent' => $object];

        $exception = new ProductNotFoundException($filters);

        $this->assertSame(
            \sprintf(
                'Model "%s" with "parent" %s not found',
                ProductInterface::class,
                \get_debug_type($object)
            ),
            $exception->getMessage()
        );
    }

    public function testGetCode(): void
    {
        $exception = new ProductNotFoundException(['uuid' => 'some-uuid'], 42);

        $this->assertSame(42, $exception->getCode());
    }

    public function testGetPrevious(): void
    {
        $previous = new \Exception('previous');

        $exception = new ProductNotFoundException([], 0, $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }
}
