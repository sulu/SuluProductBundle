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

namespace Sulu\Product\Tests\Unit\UserInterface\Agent\Tool;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Application\Routing\PerProductProductSlugProvider;
use Sulu\Product\Application\Routing\PerVariantProductSlugProvider;
use Sulu\Product\Application\Routing\VariantUrlBuilder;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Tests\Unit\UserInterface\Agent\Tool\Fixtures\FakeRouteGenerator;
use Sulu\Product\UserInterface\Agent\Tool\ProductUrlGenerator;
use Sulu\Route\Application\Routing\Generator\RouteGeneratorInterface;
use Sulu\Route\Domain\Exception\MissingRequestContextParameterException;
use Sulu\Route\Domain\Model\Route;
use Sulu\Route\Domain\Repository\RouteRepositoryInterface;

#[CoversClass(ProductUrlGenerator::class)]
class ProductUrlGeneratorTest extends TestCase
{
    public function testGeneratesTheAbsoluteUrlWithTheLocalePrefix(): void
    {
        $generator = new ProductUrlGenerator(new FakeRouteGenerator(), new PerVariantProductSlugProvider());

        $this->assertSame('https://example.org/en/my-product', $generator->generate($this->localized('/my-product'), 'en'));
    }

    public function testWithoutARouteThereIsNoUrl(): void
    {
        $generator = new ProductUrlGenerator(new FakeRouteGenerator(), new PerVariantProductSlugProvider());

        $this->assertNull($generator->generate($this->localized(null), 'en'));
    }

    public function testAMissingRequestContextParameterYieldsNoUrl(): void
    {
        $generator = new ProductUrlGenerator($this->throwing(new MissingRequestContextParameterException('host')), new PerVariantProductSlugProvider());

        $this->assertNull($generator->generate($this->localized('/my-product'), 'en'));
    }

    public function testARuntimeFailureOfTheRouteGeneratorYieldsNoUrl(): void
    {
        $generator = new ProductUrlGenerator($this->throwing(new \RuntimeException('no webspace')), new PerVariantProductSlugProvider());

        $this->assertNull($generator->generate($this->localized('/my-product'), 'en'));
    }

    public function testLinksAVariantByItsProductUrlInPerProductRouting(): void
    {
        $parent = new Product('parent-uuid');
        $variant = new Product('variant-uuid');
        $variant->setParent($parent);
        $localized = $variant->createDimensionContent();
        $localized->setLocale('en');
        $localized->setStage(DimensionContentInterface::STAGE_LIVE);

        $routeRepository = $this->createStub(RouteRepositoryInterface::class);
        $routeRepository->method('findFirstBy')->willReturn(new Route(ProductInterface::RESOURCE_KEY, 'parent-uuid', 'en', '/t-shirt'));

        $generator = new ProductUrlGenerator(new FakeRouteGenerator(), new PerProductProductSlugProvider($routeRepository, new VariantUrlBuilder('variant')));

        $this->assertSame('https://example.org/en/t-shirt?variant=RED', $generator->generate($localized, 'en', 'RED'));
    }

    private function throwing(\Throwable $exception): RouteGeneratorInterface
    {
        return new class($exception) implements RouteGeneratorInterface {
            public function __construct(private readonly \Throwable $exception)
            {
            }

            public function generate(string $slug, ?string $locale = null, ?string $webspace = null, int $referenceType = 1): string
            {
                throw $this->exception;
            }
        };
    }

    private function localized(?string $slug): ProductDimensionContentInterface
    {
        $product = new Product();
        $localized = $product->createDimensionContent();
        $localized->setLocale('en');
        $localized->setStage(DimensionContentInterface::STAGE_LIVE);

        if (null !== $slug) {
            $localized->setRoute(new Route(ProductInterface::RESOURCE_KEY, $product->getUuid(), 'en', $slug));
        }

        return $localized;
    }
}
