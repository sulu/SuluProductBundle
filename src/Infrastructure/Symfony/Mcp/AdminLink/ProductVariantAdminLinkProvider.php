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

namespace Sulu\Product\Infrastructure\Symfony\Mcp\AdminLink;

use Sulu\Bundle\AdminBundle\Admin\View\ViewRegistry;
use Sulu\Mcp\Application\AdminLink\AdminLinkProviderInterface;
use Sulu\Mcp\Infrastructure\Sulu\AdminLink\AdminLinkContextTrait;
use Sulu\Product\Infrastructure\Sulu\Admin\ProductAdmin;

/**
 * Variants open as an overlay on the parent's "variants" tab, so `uuid` is the PARENT's uuid.
 */
final readonly class ProductVariantAdminLinkProvider implements AdminLinkProviderInterface
{
    use AdminLinkContextTrait;

    public function __construct(
        private ViewRegistry $viewRegistry,
    ) {
    }

    public function getType(): string
    {
        return 'product_variant';
    }

    public function buildPath(array $context): ?string
    {
        $locale = $this->requireString($context, 'locale');
        $uuid = $this->requireString($context, 'uuid');

        if (null === $locale || null === $uuid) {
            return null;
        }

        $parentPath = $this->resolveViewPath($this->viewRegistry, ProductAdmin::EDIT_TABS_VIEW, [
            ':locale' => $locale,
            ':id' => $uuid,
        ]);

        return null !== $parentPath ? $parentPath . '/variants' : null;
    }
}
