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

namespace Sulu\Product\Tests\Unit\Infrastructure\Sulu\Route;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sulu\Content\Application\ContentLocalizationsResolver\ContentLocalizationsResolverInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Application\Routing\VariantUrlBuilder;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Infrastructure\Sulu\Route\CurrentVariantProvider;
use Sulu\Product\Infrastructure\Sulu\Route\PerProductLocalizationsResolver;
use Sulu\Product\Infrastructure\Sulu\Route\ProductRouteDefaultsProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

#[CoversClass(PerProductLocalizationsResolver::class)]
class PerProductLocalizationsResolverTest extends TestCase
{
    private const LOCALIZATIONS = ['de' => ['url' => '/de/t-shirt', 'locale' => 'de', 'alternate' => true]];

    public function testLinksTheProductWithTheVariantWhereItIsPublished(): void
    {
        [$productContent, $variantContent] = $this->createContents('RED', ['en', 'de', 'it']);

        $routeLocalizationsResolver = $this->createMock(ContentLocalizationsResolverInterface::class);
        $routeLocalizationsResolver->expects(self::once())->method('resolve')->with($productContent, 'sulu-io')->willReturn([
            'en' => ['url' => '/en/t-shirt', 'locale' => 'en', 'alternate' => true],
            'de' => ['url' => '/de/t-shirt', 'locale' => 'de', 'alternate' => true],
            'fr' => ['url' => '/fr/t-shirt', 'locale' => 'fr', 'alternate' => true],
            // the product has no route in "it", so the entry links the start page
            'it' => ['url' => '/it', 'locale' => 'it', 'alternate' => false],
        ]);

        $resolver = $this->createResolver($routeLocalizationsResolver, $variantContent);

        self::assertSame([
            'en' => ['url' => '/en/t-shirt?variant=RED', 'locale' => 'en', 'alternate' => true],
            'de' => ['url' => '/de/t-shirt?variant=RED', 'locale' => 'de', 'alternate' => true],
            'fr' => ['url' => '/fr/t-shirt', 'locale' => 'fr', 'alternate' => true],
            'it' => ['url' => '/it', 'locale' => 'it', 'alternate' => false],
        ], $resolver->resolve($productContent, 'sulu-io'));
    }

    public function testUsesTheConfiguredParameter(): void
    {
        [$productContent, $variantContent] = $this->createContents('RED', ['de']);

        $routeLocalizationsResolver = $this->createStub(ContentLocalizationsResolverInterface::class);
        $routeLocalizationsResolver->method('resolve')->willReturn(self::LOCALIZATIONS);

        $resolver = $this->createResolver($routeLocalizationsResolver, $variantContent, 'sku');

        self::assertSame('/de/t-shirt?sku=RED', $resolver->resolve($productContent, 'sulu-io')['de']['url']);
    }

    public function testKeepsTheProductUrlsForAVariantWithoutCode(): void
    {
        [$productContent, $variantContent] = $this->createContents(null, ['de']);

        $routeLocalizationsResolver = $this->createStub(ContentLocalizationsResolverInterface::class);
        $routeLocalizationsResolver->method('resolve')->willReturn(self::LOCALIZATIONS);

        $resolver = $this->createResolver($routeLocalizationsResolver, $variantContent);

        self::assertSame(self::LOCALIZATIONS, $resolver->resolve($productContent, 'sulu-io'));
    }

    public function testKeepsTheProductUrlsWithoutAVariant(): void
    {
        [$productContent] = $this->createContents('RED', ['de']);

        $routeLocalizationsResolver = $this->createStub(ContentLocalizationsResolverInterface::class);
        $routeLocalizationsResolver->method('resolve')->willReturn(self::LOCALIZATIONS);

        $resolver = $this->createResolver($routeLocalizationsResolver, null);

        self::assertSame(self::LOCALIZATIONS, $resolver->resolve($productContent, 'sulu-io'));
    }

    public function testContentThatIsNoProductKeepsItsOwnLocalizations(): void
    {
        [, $variantContent] = $this->createContents('RED', ['de']);
        $dimensionContent = $this->createStub(DimensionContentInterface::class);

        $routeLocalizationsResolver = $this->createStub(ContentLocalizationsResolverInterface::class);
        $routeLocalizationsResolver->method('resolve')->willReturn(self::LOCALIZATIONS);

        $resolver = $this->createResolver($routeLocalizationsResolver, $variantContent);

        self::assertSame(self::LOCALIZATIONS, $resolver->resolve($dimensionContent, 'sulu-io'));
    }

    /**
     * @param string[] $variantLocales
     *
     * @return array{ProductDimensionContent, ProductDimensionContent}
     */
    private function createContents(?string $code, array $variantLocales): array
    {
        $productContent = new ProductDimensionContent(new Product('parent-uuid'));
        $productContent->setLocale('en');
        $variant = new Product('variant-uuid');
        $variant->setParent($productContent->getResource());
        $variantContent = new ProductDimensionContent($variant);
        $variantContent->setCode($code);

        foreach ($variantLocales as $locale) {
            $variantContent->addAvailableLocale($locale);
        }

        return [$productContent, $variantContent];
    }

    private function createResolver(
        ContentLocalizationsResolverInterface $routeLocalizationsResolver,
        ?ProductDimensionContent $currentVariant,
        string $parameter = 'variant',
    ): PerProductLocalizationsResolver {
        $request = new Request(attributes: null === $currentVariant ? [] : [ProductRouteDefaultsProvider::VARIANT_ATTRIBUTE => $currentVariant]);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        return new PerProductLocalizationsResolver(
            $routeLocalizationsResolver,
            new CurrentVariantProvider($requestStack),
            new VariantUrlBuilder($parameter),
        );
    }
}
