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

namespace Sulu\Product\Infrastructure\Symfony\Mcp;

use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Content\Infrastructure\Doctrine\DimensionContentQueryEnhancer;
use Sulu\Mcp\Domain\Content\ContentSecurity;
use Sulu\Mcp\Domain\Content\ContentTypeExtensionInterface;
use Sulu\Product\Application\Message\ApplyWorkflowTransitionProductMessage;
use Sulu\Product\Application\Message\ModifyProductMessage;
use Sulu\Product\Application\Message\RemoveProductMessage;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\Infrastructure\Sulu\Admin\ProductAdmin;

final readonly class ProductContentTypeExtension implements ContentTypeExtensionInterface
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
    ) {
    }

    public function getResourceKey(): string
    {
        return ProductInterface::RESOURCE_KEY;
    }

    public function getTemplateType(): string
    {
        return 'product';
    }

    public function getViewSecurityContexts(): array
    {
        return [ProductAdmin::SECURITY_CONTEXT];
    }

    public function getSecurity(object $aggregate, string $locale): ContentSecurity
    {
        return new ContentSecurity(ProductAdmin::SECURITY_CONTEXT);
    }

    public function loadDraft(string $uuid, string $locale, bool $loadGhost = false): ProductInterface
    {
        $filters = [
            'uuid' => $uuid,
            'locale' => $locale,
            'stage' => DimensionContentInterface::STAGE_DRAFT,
            'loadGhost' => $loadGhost,
        ];

        return $this->productRepository->getOneBy($filters, [ProductRepositoryInterface::GROUP_SELECT_PRODUCT_ADMIN => true]);
    }

    public function loadForTransition(string $uuid, string $locale): ProductInterface
    {
        $filters = [
            'uuid' => $uuid,
            'locale' => $locale,
            'stage' => DimensionContentInterface::STAGE_DRAFT,
        ];

        $contentSelect = [
            'selects' => [DimensionContentQueryEnhancer::GROUP_SELECT_CONTENT_ADMIN => true],
            'dimensionAttributes' => [
                'locale' => $locale,
                'stage' => [DimensionContentInterface::STAGE_DRAFT, DimensionContentInterface::STAGE_LIVE],
            ],
        ];

        return $this->productRepository->getOneBy($filters, [ProductRepositoryInterface::SELECT_PRODUCT_CONTENT => $contentSelect]);
    }

    public function createModifyMessage(string $uuid, array $data): object
    {
        return new ModifyProductMessage(['uuid' => $uuid], $data); // @phpstan-ignore argument.type (message shape is validated by its own handler)
    }

    public function createRemoveMessage(string $uuid, string $locale, bool $forceRemoveChildren = false): object
    {
        // A product has no subtree, so forceRemoveChildren means nothing here.
        return new RemoveProductMessage(['uuid' => $uuid], $locale);
    }

    public function createTransitionMessage(string $uuid, string $locale, string $transition): object
    {
        return new ApplyWorkflowTransitionProductMessage(['uuid' => $uuid], $locale, $transition);
    }
}
