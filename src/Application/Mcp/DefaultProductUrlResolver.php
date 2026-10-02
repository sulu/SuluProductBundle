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

namespace Sulu\Product\Application\Mcp;

use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Route\Application\ResourceLocator\ResourceLocatorGeneratorInterface;
use Sulu\Route\Application\ResourceLocator\ResourceLocatorRequest;

/**
 * The admin fills the route field from the title while the editor types. A tool has no editor, so
 * without this a product created through it has no route and its page is a 404.
 *
 * @internal
 */
final readonly class DefaultProductUrlResolver
{
    /**
     * @param array<string, scalar|null> $routeParams
     */
    public function __construct(
        private ResourceLocatorGeneratorInterface $resourceLocatorGenerator,
        private string $routeType,
        private array $routeParams,
    ) {
    }

    /**
     * Returns null for a route field that needs a parent page, which only the caller can name.
     */
    public function resolve(string $title, string $locale): ?string
    {
        if ('route' !== $this->routeType) {
            return null;
        }

        $routeSchema = $this->routeParams['route_schema'] ?? null;

        return $this->resourceLocatorGenerator->generate(new ResourceLocatorRequest(
            ['title' => $title],
            $locale,
            null,
            ProductInterface::RESOURCE_KEY,
            null,
            null,
            null,
            \is_string($routeSchema) ? $routeSchema : null,
        ));
    }
}
