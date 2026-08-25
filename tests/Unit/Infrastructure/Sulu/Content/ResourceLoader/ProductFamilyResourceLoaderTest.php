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

namespace Sulu\Product\Tests\Unit\Infrastructure\Sulu\Content\ResourceLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Sulu\Product\Domain\Model\ProductFamily;
use Sulu\Product\Domain\Model\ProductFamilyTranslation;
use Sulu\Product\Domain\Repository\ProductFamilyRepositoryInterface;
use Sulu\Product\Infrastructure\Sulu\Content\ProductFamilyWrapper;
use Sulu\Product\Infrastructure\Sulu\Content\ResourceLoader\ProductFamilyResourceLoader;

#[CoversClass(ProductFamilyResourceLoader::class)]
#[CoversClass(ProductFamilyWrapper::class)]
class ProductFamilyResourceLoaderTest extends TestCase
{
    use ProphecyTrait;

    public function testLoadsFamiliesKeyedByUuidAndWrappedForTheLocale(): void
    {
        $family = new ProductFamily();
        $family->setUuid('uuid-1');
        $family->addTranslation(new ProductFamilyTranslation($family, 'de', 'XLR'));

        $repository = $this->prophesize(ProductFamilyRepositoryInterface::class);
        $repository->findBy(['uuids' => ['uuid-1']])->willReturn([$family]);

        $loader = new ProductFamilyResourceLoader($repository->reveal());
        $result = $loader->load(['uuid-1'], 'de');

        self::assertArrayHasKey('uuid-1', $result);
        $wrapper = $result['uuid-1'];
        self::assertInstanceOf(ProductFamilyWrapper::class, $wrapper);
        self::assertSame('XLR', $wrapper->getName());
    }

    public function testSkipsFamiliesWithoutAUuid(): void
    {
        $family = new ProductFamily();

        $repository = $this->prophesize(ProductFamilyRepositoryInterface::class);
        $repository->findBy(['uuids' => ['uuid-1']])->willReturn([$family]);

        $loader = new ProductFamilyResourceLoader($repository->reveal());

        self::assertSame([], $loader->load(['uuid-1'], 'de'));
    }

    public function testReturnsNothingWithoutALocale(): void
    {
        $repository = $this->prophesize(ProductFamilyRepositoryInterface::class);

        $loader = new ProductFamilyResourceLoader($repository->reveal());

        self::assertSame([], $loader->load(['uuid-1'], null));
    }

    public function testKeyIsProductFamily(): void
    {
        self::assertSame('product_family', ProductFamilyResourceLoader::getKey());
    }

    public function testWrapperExposesTheFamilyFieldsForTheGivenLocale(): void
    {
        $family = new ProductFamily();
        $family->setUuid('uuid-1');
        $family->setExternalIdentifier('ext-1');
        $family->addTranslation(new ProductFamilyTranslation($family, 'de', 'XLR'));
        (new \ReflectionProperty(ProductFamily::class, 'id'))->setValue($family, 9);

        $wrapper = new ProductFamilyWrapper($family, 'de');

        self::assertSame(9, $wrapper->getId());
        self::assertSame('uuid-1', $wrapper->getUuid());
        self::assertSame('ext-1', $wrapper->getExternalIdentifier());
        self::assertSame('XLR', $wrapper->getName());
    }

    public function testWrapperReturnsNullNameWithoutALocale(): void
    {
        $family = new ProductFamily();

        $wrapper = new ProductFamilyWrapper($family, null);

        self::assertNull($wrapper->getName());
    }
}
