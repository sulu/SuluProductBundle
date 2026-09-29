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

namespace Sulu\Product\Tests\Unit\Infrastructure\Symfony\Mcp;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Application\Message\ApplyWorkflowTransitionProductMessage;
use Sulu\Product\Application\Message\ModifyProductMessage;
use Sulu\Product\Application\Message\RemoveProductMessage;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\Infrastructure\Sulu\Admin\ProductAdmin;
use Sulu\Product\Infrastructure\Symfony\Mcp\ContentTypeExtension;

#[CoversClass(ContentTypeExtension::class)]
final class ContentTypeExtensionTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<ProductRepositoryInterface> */
    private ObjectProphecy $productRepository;
    private ContentTypeExtension $extension;

    protected function setUp(): void
    {
        $this->productRepository = $this->prophesize(ProductRepositoryInterface::class);
        $this->extension = new ContentTypeExtension($this->productRepository->reveal());
    }

    public function testAProductCanAlwaysBeRemoved(): void
    {
        $this->expectNotToPerformAssertions();

        $this->extension->assertCanRemove('any-uuid');
    }

    public function testGetTemplateTypeReturnsProduct(): void
    {
        $this->assertSame('product', $this->extension->getTemplateType());
    }

    public function testGetResourceKeyMatchesTheIndexedResourceKey(): void
    {
        $this->assertSame(ProductInterface::RESOURCE_KEY, $this->extension->getResourceKey());
    }

    public function testSecurityContextsMatchProductAdmin(): void
    {
        $this->assertSame([ProductAdmin::SECURITY_CONTEXT], $this->extension->getViewSecurityContexts());
        $this->assertSame(ProductAdmin::SECURITY_CONTEXT, $this->extension->getEntitySecurityContext(new \stdClass(), null));
        $this->assertFalse($this->extension->requiresResolvedContent());
        $this->assertNull($this->extension->getAclObjectType());
        $this->assertNull($this->extension->getWebspaceKey(new \stdClass()));
    }

    public function testLoadDraftQueriesTheDraftStageWithAdminSelects(): void
    {
        $product = new Product('product-uuid');

        $this->productRepository->getOneBy(
            [
                'uuid' => 'product-uuid',
                'locale' => 'en',
                'stage' => DimensionContentInterface::STAGE_DRAFT,
                'loadGhost' => false,
            ],
            [ProductRepositoryInterface::GROUP_SELECT_PRODUCT_ADMIN => true],
        )->shouldBeCalledOnce()->willReturn($product);

        $this->assertSame($product, $this->extension->loadDraft('product-uuid', 'en'));
    }

    public function testLoadForTransitionQueriesDraftAndLiveStages(): void
    {
        $product = new Product('product-uuid');

        $this->productRepository->getOneBy(
            Argument::that(static fn (array $filters): bool => 'product-uuid' === $filters['uuid']
                && DimensionContentInterface::STAGE_DRAFT === $filters['stage']),
            Argument::that(static function(array $selects): bool {
                $contentSelect = $selects[ProductRepositoryInterface::SELECT_PRODUCT_CONTENT] ?? null;
                $dimensionAttributes = \is_array($contentSelect) ? ($contentSelect['dimensionAttributes'] ?? null) : null;

                return \is_array($dimensionAttributes)
                    && [DimensionContentInterface::STAGE_DRAFT, DimensionContentInterface::STAGE_LIVE] === ($dimensionAttributes['stage'] ?? null);
            }),
        )->shouldBeCalledOnce()->willReturn($product);

        $this->assertSame($product, $this->extension->loadForTransition('product-uuid', 'en'));
    }

    public function testCreateModifyMessageBuildsAModifyProductMessage(): void
    {
        $message = $this->extension->createModifyMessage('product-uuid', ['title' => 'New title']);

        $this->assertInstanceOf(ModifyProductMessage::class, $message);
    }

    public function testCreateRemoveMessageBuildsARemoveProductMessage(): void
    {
        $message = $this->extension->createRemoveMessage('product-uuid', 'en');

        $this->assertInstanceOf(RemoveProductMessage::class, $message);
    }

    public function testCreateTransitionMessageBuildsAWorkflowTransitionMessage(): void
    {
        $message = $this->extension->createTransitionMessage('product-uuid', 'en', 'publish');

        $this->assertInstanceOf(ApplyWorkflowTransitionProductMessage::class, $message);
    }
}
