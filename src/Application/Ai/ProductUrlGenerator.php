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

namespace Sulu\Product\Application\Ai;

use Sulu\Product\Application\Routing\VariantSlugResolver;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Route\Application\Routing\Generator\RouteGeneratorInterface;
use Sulu\Route\Domain\Exception\MissingRequestContextParameterException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The absolute URL of a product's page, including the webspace's locale prefix: the route slug
 * alone is not a working link on a webspace like `{host}/en`.
 */
final class ProductUrlGenerator
{
    public function __construct(
        private readonly RouteGeneratorInterface $routeGenerator,
        private readonly VariantSlugResolver $variantSlugResolver,
    ) {
    }

    /**
     * @param string|null $code the product code, which the localized dimension content does not carry
     */
    public function generate(ProductDimensionContentInterface $localized, string $locale, ?string $code = null): ?string
    {
        $slug = $this->variantSlugResolver->resolve($localized, $code);

        if (null === $slug) {
            return null;
        }

        try {
            return $this->routeGenerator->generate($slug, $locale, $localized->getMainWebspace(), UrlGeneratorInterface::ABSOLUTE_URL);
        } catch (MissingRequestContextParameterException|\RuntimeException) {
            return null;
        }
    }
}
