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

namespace Sulu\Product\Tests\Unit\Controller\Website;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Sulu\Content\Application\ContentResolver\ContentResolverInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\UserInterface\Controller\Website\ProductController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class ProductControllerTest extends TestCase
{
    private const VARIANTS = [
        ['product' => ['code' => 'NC3FXX', 'position' => 0]],
        ['product' => ['code' => 'NC3FXX-B', 'position' => 1]],
    ];

    private const LOCALIZATIONS = [
        'de' => ['locale' => 'de', 'url' => '/de/produkt/nc3fxx', 'alternate' => true],
        'en' => ['locale' => 'en', 'url' => '/en/product/nc3fxx', 'alternate' => true],
    ];

    public function testTheRequestedCodeSelectsItsVariant(): void
    {
        $this->assertSame(self::VARIANTS[1], $this->createController('?variant=NC3FXX-B')->selectVariant(self::VARIANTS));
    }

    /** The bare URL shows the first variant, which the resolver orders by position. */
    public function testWithoutACodeTheFirstVariantIsSelected(): void
    {
        $this->assertSame(self::VARIANTS[0], $this->createController('')->selectVariant(self::VARIANTS));
    }

    public function testAnEmptyCodeIsNoCode(): void
    {
        $this->assertSame(self::VARIANTS[0], $this->createController('?variant=')->selectVariant(self::VARIANTS));
    }

    public function testAnUnknownCodeFallsBackInsteadOfFailing(): void
    {
        $this->assertSame(self::VARIANTS[0], $this->createController('?variant=GONE')->selectVariant(self::VARIANTS));
    }

    /** `get()` would throw a 400 here, which would take the page down instead of falling back. */
    public function testAnArrayCodeFallsBackInsteadOfFailing(): void
    {
        $this->assertSame(self::VARIANTS[0], $this->createController('?variant[]=NC3FXX-B')->selectVariant(self::VARIANTS));
    }

    public function testWithoutARequestTheFirstVariantIsSelected(): void
    {
        $controller = new ProductController(new RequestStack(), 'variant');

        $this->assertSame(self::VARIANTS[0], $controller->selectVariant(self::VARIANTS));
    }

    /** A product that carries the article itself has nothing to select. */
    public function testAProductWithoutVariantsSelectsNothing(): void
    {
        $this->assertNull($this->createController('?variant=NC3FXX-B')->selectVariant([]));
    }

    /** The variant is the address, so every locale that has it links to it. */
    public function testTheSelectionRidesIntoEveryLocaleThatPublishesIt(): void
    {
        $localizations = $this->createController('?variant=NC3FXX-B')->localizeSelection(self::LOCALIZATIONS, [
            'product' => ['code' => 'NC3FXX-B', 'position' => 1],
            'availableLocales' => ['de', 'en'],
        ]);

        $this->assertSame(
            [
                'de' => ['locale' => 'de', 'url' => '/de/produkt/nc3fxx?variant=NC3FXX-B', 'alternate' => true],
                'en' => ['locale' => 'en', 'url' => '/en/product/nc3fxx?variant=NC3FXX-B', 'alternate' => true],
            ],
            $localizations,
        );
    }

    /** A locale without the variant shows the default one, so hreflang must not claim a match. */
    public function testALocaleWithoutTheVariantKeepsTheBareUrlAndLeavesHreflang(): void
    {
        $localizations = $this->createController('?variant=NC3FXX-B')->localizeSelection(self::LOCALIZATIONS, [
            'product' => ['code' => 'NC3FXX-B', 'position' => 1],
            'availableLocales' => ['de'],
        ]);

        $this->assertSame(
            [
                'de' => ['locale' => 'de', 'url' => '/de/produkt/nc3fxx?variant=NC3FXX-B', 'alternate' => true],
                'en' => ['locale' => 'en', 'url' => '/en/product/nc3fxx', 'alternate' => false],
            ],
            $localizations,
        );
    }

    /** The default variant is the bare URL, so the localizations are already right. */
    public function testTheDefaultVariantLeavesTheLocalizationsAlone(): void
    {
        $localizations = $this->createController('')->localizeSelection(self::LOCALIZATIONS, [
            'product' => ['code' => 'NC3FXX', 'position' => 0],
            'availableLocales' => ['de', 'en'],
        ]);

        $this->assertSame(self::LOCALIZATIONS, $localizations);
    }

    public function testAProductWithoutVariantsLeavesTheLocalizationsAlone(): void
    {
        $this->assertSame(self::LOCALIZATIONS, $this->createController('')->localizeSelection(self::LOCALIZATIONS, null));
    }

    /** No availableLocales is no evidence the variant exists anywhere else. */
    public function testAMissingAvailableLocalesDropsEveryAlternate(): void
    {
        $localizations = $this->createController('?variant=NC3FXX-B')->localizeSelection(self::LOCALIZATIONS, [
            'product' => ['code' => 'NC3FXX-B', 'position' => 1],
        ]);

        $this->assertSame(
            [
                'de' => ['locale' => 'de', 'url' => '/de/produkt/nc3fxx', 'alternate' => false],
                'en' => ['locale' => 'en', 'url' => '/en/product/nc3fxx', 'alternate' => false],
            ],
            $localizations,
        );
    }

    /** The parameter name is configurable, so both halves of a variant URL stay in step. */
    public function testTheConfiguredParameterNameIsUsed(): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('/produkt/nc3fxx?v=NC3FXX-B'));

        $controller = new ProductController($requestStack, 'v');

        $this->assertSame(self::VARIANTS[1], $controller->selectVariant(self::VARIANTS));
        $this->assertSame(
            ['de' => ['locale' => 'de', 'url' => '/de/produkt/nc3fxx?v=NC3FXX-B', 'alternate' => true]],
            $controller->localizeSelection(
                ['de' => ['locale' => 'de', 'url' => '/de/produkt/nc3fxx', 'alternate' => true]],
                ['product' => ['code' => 'NC3FXX-B', 'position' => 1], 'availableLocales' => ['de']],
            ),
        );
    }

    public function testACodeIsEncodedInTheQuery(): void
    {
        $localizations = $this->createController('')->localizeSelection(
            ['de' => ['locale' => 'de', 'url' => '/de/produkt/xxr', 'alternate' => true]],
            ['product' => ['code' => 'XXR-*', 'position' => 1], 'availableLocales' => ['de']],
        );

        $this->assertSame('/de/produkt/xxr?variant=XXR-%2A', $localizations['de']['url']);
    }

    /**
     * The wiring: the resolved data keeps the selection the localizations were built from. The
     * rules themselves are covered by the select/localize tests above.
     */
    public function testTheResolvedParametersCarryTheSelection(): void
    {
        $controller = $this->createController('?variant=NC3FXX-B');
        $controller->setContainer($this->createContainer(['product' => ['variants' => self::VARIANTS]]));

        $parameters = $this->resolveSuluParameters($controller);

        $this->assertSame(self::VARIANTS[1], $parameters['selectedVariant']);
        $this->assertSame([], $parameters['localizations']);
        $this->assertSame(['variants' => self::VARIANTS], $parameters['product']);
    }

    /** A product without variants resolves to no selection rather than to an error. */
    public function testTheResolvedParametersOfAProductWithoutVariants(): void
    {
        $controller = $this->createController('');
        $controller->setContainer($this->createContainer(['product' => []]));

        $parameters = $this->resolveSuluParameters($controller);

        $this->assertNull($parameters['selectedVariant']);
        $this->assertSame([], $parameters['localizations']);
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveSuluParameters(ProductController $controller): array
    {
        // The parent skips its localization lookup for anything that is not routable, so the
        // wiring can be exercised without the route and webspace services.
        $object = $this->createStub(DimensionContentInterface::class);

        $method = new \ReflectionMethod($controller, 'resolveSuluParameters');

        /** @var array<string, mixed> $parameters */
        $parameters = $method->invoke($controller, $object, 'sulu_io', false);

        return $parameters;
    }

    /**
     * @param array<string, mixed> $resolved
     */
    private function createContainer(array $resolved): ContainerInterface
    {
        $contentResolver = $this->createStub(ContentResolverInterface::class);
        $contentResolver->method('resolve')->willReturn($resolved);

        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')->willReturn($contentResolver);

        return $container;
    }

    private function createController(string $queryString): ProductController
    {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('/produkt/nc3fxx' . $queryString));

        return new ProductController($requestStack, 'variant');
    }
}
