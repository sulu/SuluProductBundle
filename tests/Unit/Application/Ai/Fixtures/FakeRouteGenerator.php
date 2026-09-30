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

namespace Sulu\Product\Tests\Unit\Application\Ai\Fixtures;

use Sulu\Route\Application\Routing\Generator\RouteGeneratorInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Generates the URL of a webspace with a locale prefix, like the test app's `{host}/en`.
 */
final class FakeRouteGenerator implements RouteGeneratorInterface
{
    public function generate(string $slug, ?string $locale = null, ?string $webspace = null, int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): string
    {
        $path = '/' . $locale . '/' . \ltrim($slug, '/');

        return UrlGeneratorInterface::ABSOLUTE_URL === $referenceType ? 'https://example.org' . $path : $path;
    }
}
