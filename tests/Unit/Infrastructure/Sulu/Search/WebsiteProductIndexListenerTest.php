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

namespace Sulu\Product\Tests\Unit\Infrastructure\Sulu\Search;

use CmsIg\Seal\Reindex\ReindexConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Sulu\Product\Domain\Event\ProductRemovedEvent;
use Sulu\Product\Domain\Event\ProductTranslationRemovedEvent;
use Sulu\Product\Domain\Event\ProductWorkflowTransitionAppliedEvent;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Infrastructure\Sulu\Search\WebsiteProductIndexListener;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[CoversClass(WebsiteProductIndexListener::class)]
class WebsiteProductIndexListenerTest extends TestCase
{
    use ProphecyTrait;

    public function testParentTransitionReindexesParentAndVariants(): void
    {
        $parent = new Product('parent-uuid');
        $parent->setType(ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);
        $variant = new Product('variant-uuid');
        $variant->setType(ProductInterface::TYPE_VARIANT);
        $variant->setParent($parent);
        $parent->getVariants()->add($variant);

        $messageBus = $this->prophesize(MessageBusInterface::class);
        $messageBus->dispatch(Argument::that(function(ReindexConfig $config): bool {
            $this->assertSame('website', $config->getIndex());
            $this->assertEqualsCanonicalizing([
                'products__parent-uuid__en',
                'products__variant-uuid__en',
            ], $config->getIdentifiers());

            return true;
        }))->willReturn(new Envelope(new \stdClass()))->shouldBeCalledOnce();

        $listener = new WebsiteProductIndexListener($messageBus->reveal());
        $listener->onProductChanged(new ProductWorkflowTransitionAppliedEvent($parent, 'publish', 'en'));
    }

    public function testVariantTransitionReindexesVariantAndParent(): void
    {
        $parent = new Product('parent-uuid');
        $variant = new Product('variant-uuid');
        $variant->setType(ProductInterface::TYPE_VARIANT);
        $variant->setParent($parent);

        $messageBus = $this->prophesize(MessageBusInterface::class);
        $messageBus->dispatch(Argument::that(function(ReindexConfig $config): bool {
            $this->assertEqualsCanonicalizing([
                'products__variant-uuid__de',
                'products__parent-uuid__de',
            ], $config->getIdentifiers());

            return true;
        }))->willReturn(new Envelope(new \stdClass()))->shouldBeCalledOnce();

        $listener = new WebsiteProductIndexListener($messageBus->reveal());
        $listener->onProductChanged(new ProductWorkflowTransitionAppliedEvent($variant, 'publish', 'de'));
    }

    public function testRemovedProductReindexesAllLocales(): void
    {
        $messageBus = $this->prophesize(MessageBusInterface::class);
        $messageBus->dispatch(Argument::that(function(ReindexConfig $config): bool {
            $this->assertEqualsCanonicalizing([
                'products__gone-uuid__en',
                'products__gone-uuid__de',
            ], $config->getIdentifiers());

            return true;
        }))->willReturn(new Envelope(new \stdClass()))->shouldBeCalledOnce();

        $listener = new WebsiteProductIndexListener($messageBus->reveal());
        $listener->onProductChanged(new ProductRemovedEvent('gone-uuid', 'Gone', ['locales' => ['en', 'de']]));
    }

    /**
     * The removal cascades to the variants, so their documents are reindexed as well. The reindex
     * provider yields nothing for a deleted row, so the engine deletes those identifiers.
     */
    public function testRemovedProductReindexesItsVariantsAndParent(): void
    {
        $messageBus = $this->prophesize(MessageBusInterface::class);
        $messageBus->dispatch(Argument::that(function(ReindexConfig $config): bool {
            $this->assertEqualsCanonicalizing([
                'products__parent-uuid__en',
                'products__variant-one-uuid__en',
                'products__variant-two-uuid__en',
            ], $config->getIdentifiers());

            return true;
        }))->willReturn(new Envelope(new \stdClass()))->shouldBeCalledOnce();

        $listener = new WebsiteProductIndexListener($messageBus->reveal());
        $listener->onProductChanged(new ProductRemovedEvent('parent-uuid', 'Gone', [
            'locales' => ['en'],
            'variantUuids' => ['variant-one-uuid', 'variant-two-uuid'],
        ]));
    }

    /**
     * A variant document holds its parent's url, so removing the parent's translation reindexes
     * the whole family in that locale.
     */
    public function testTranslationRemovedReindexesParentAndVariantsInThatLocale(): void
    {
        $parent = new Product('parent-uuid');
        $parent->setType(ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);
        $variant = new Product('variant-uuid');
        $variant->setType(ProductInterface::TYPE_VARIANT);
        $variant->setParent($parent);
        $parent->getVariants()->add($variant);

        $messageBus = $this->prophesize(MessageBusInterface::class);
        $messageBus->dispatch(Argument::that(function(ReindexConfig $config): bool {
            $this->assertEqualsCanonicalizing([
                'products__parent-uuid__de',
                'products__variant-uuid__de',
            ], $config->getIdentifiers());

            return true;
        }))->willReturn(new Envelope(new \stdClass()))->shouldBeCalledOnce();

        $listener = new WebsiteProductIndexListener($messageBus->reveal());
        $listener->onProductChanged(new ProductTranslationRemovedEvent($parent, 'de'));
    }

    public function testTranslationRemovedOnAVariantReindexesItAndItsParent(): void
    {
        $parent = new Product('parent-uuid');
        $variant = new Product('variant-uuid');
        $variant->setType(ProductInterface::TYPE_VARIANT);
        $variant->setParent($parent);

        $messageBus = $this->prophesize(MessageBusInterface::class);
        $messageBus->dispatch(Argument::that(function(ReindexConfig $config): bool {
            $this->assertEqualsCanonicalizing([
                'products__variant-uuid__de',
                'products__parent-uuid__de',
            ], $config->getIdentifiers());

            return true;
        }))->willReturn(new Envelope(new \stdClass()))->shouldBeCalledOnce();

        $listener = new WebsiteProductIndexListener($messageBus->reveal());
        $listener->onProductChanged(new ProductTranslationRemovedEvent($variant, 'de'));
    }

    public function testEventWithoutLocaleDispatchesNothing(): void
    {
        $messageBus = $this->prophesize(MessageBusInterface::class);
        $messageBus->dispatch(Argument::cetera())->shouldNotBeCalled();

        $listener = new WebsiteProductIndexListener($messageBus->reveal());
        $listener->onProductChanged(new ProductRemovedEvent('gone-uuid', 'Gone'));
    }

    public function testProductWithoutRelativesReindexesItself(): void
    {
        $messageBus = $this->prophesize(MessageBusInterface::class);
        $messageBus->dispatch(Argument::that(function(ReindexConfig $config): bool {
            $this->assertSame('website', $config->getIndex());
            $this->assertSame(['products__123__en'], $config->getIdentifiers());

            return true;
        }))->willReturn(new Envelope(new \stdClass()))->shouldBeCalledOnce();

        $listener = new WebsiteProductIndexListener($messageBus->reveal());
        $listener->onProductChanged(new ProductWorkflowTransitionAppliedEvent(new Product('123'), 'publish', 'en'));
    }

    public function testTransitionWithoutLocaleDispatchesNothing(): void
    {
        $messageBus = $this->prophesize(MessageBusInterface::class);
        $messageBus->dispatch(Argument::cetera())->shouldNotBeCalled();

        $listener = new WebsiteProductIndexListener($messageBus->reveal());
        $listener->onProductChanged(new ProductWorkflowTransitionAppliedEvent(new Product('123'), 'publish', ''));
    }
}
