<?php

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Product\Tests\Application;

use League\Bundle\OAuth2ServerBundle\LeagueOAuth2ServerBundle;
use Sulu\Article\Infrastructure\Symfony\HttpKernel\SuluArticleBundle;
use Sulu\Bundle\TestBundle\Kernel\SuluTestKernel;
use Sulu\Component\HttpKernel\SuluKernel;
use Sulu\Content\Tests\Application\ExampleTestBundle\ExampleTestBundle;
use Sulu\Mcp\Infrastructure\Symfony\HttpKernel\SuluMcpBundle;
use Sulu\Product\Infrastructure\Symfony\HttpKernel\SuluProductBundle;
use Sulu\Snippet\Infrastructure\Symfony\HttpKernel\SuluSnippetBundle;
use Symfony\AI\McpBundle\McpBundle;
use Symfony\Component\Config\Loader\LoaderInterface;

class Kernel extends SuluTestKernel
{
    private string $config = 'default';

    public function __construct(string $environment, bool $debug, string $suluContext = SuluKernel::CONTEXT_ADMIN)
    {
        $environmentParts = \explode('_', $environment, 2);
        $environment = $environmentParts[0];
        $this->config = $environmentParts[1] ?? $this->config;

        parent::__construct($environment, $debug, $suluContext);
    }

    public function registerBundles(): iterable
    {
        $bundles = [...parent::registerBundles()];

        $bundles[] = new SuluProductBundle();
        $bundles[] = new ExampleTestBundle();
        $bundles[] = new SuluSnippetBundle();

        if (\str_starts_with($this->config, 'mcp')) {
            $bundles[] = new SuluArticleBundle();
            $bundles[] = new McpBundle();
            $bundles[] = new LeagueOAuth2ServerBundle();
            $bundles[] = new SuluMcpBundle();
        }

        return $bundles;
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        parent::registerContainerConfiguration($loader);

        $loader->load(__DIR__ . '/config/config.yml');

        if (\file_exists(__DIR__ . '/config/config_' . $this->config . '.yml')) {
            $loader->load(__DIR__ . '/config/config_' . $this->config . '.yml');
        }
    }

    public function getCacheDir(): string
    {
        return parent::getCacheDir() . '/' . $this->config;
    }

    public function getShareDir(): string
    {
        return parent::getShareDir() . '/' . $this->config;
    }
}
