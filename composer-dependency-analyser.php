<?php

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

require __DIR__ . '/vendor/symfony/dependency-injection/Loader/Configurator/ContainerConfigurator.php'; // see https://github.com/shipmonk-rnd/composer-dependency-analyser/issues/147#issuecomment-2202156380

$config = new Configuration();

$config->addPathRegexesToExclude([
    '#/var/(cache|log)/#',
    '#/vendor/sulu/sulu/#', // sulu/sulu test files are mapped via autoload-dev but are not our code
]);

// "symfony/ai-agent" is an optional require-dev dependency: the AI tool classes only carry its
// #[AsTool] attribute, which PHP never resolves unless something reflects on it, and the bundle
// does that behind ContainerBuilder::willBeAvailable(), so the package never has to be installed.
$config->ignoreErrorsOnPackageAndPaths(
    'symfony/ai-agent',
    [
        __DIR__ . '/src/Application/Ai',
        __DIR__ . '/src/Infrastructure/Symfony/HttpKernel/SuluProductBundle.php',
    ],
    [ErrorType::DEV_DEPENDENCY_IN_PROD],
);

// The tool test runs symfony/ai-agent's own argument resolver, which needs its ToolCall value object
// from the platform package ai-agent already pulls in.
$config->ignoreErrorsOnPackageAndPaths(
    'symfony/ai-platform',
    [__DIR__ . '/tests/Unit/Application/Ai/SearchProductsByAttributesTest.php'],
    [ErrorType::SHADOW_DEPENDENCY],
);

// "sulu/mcp-bundle" is an optional require-dev dependency that brings mcp/sdk: the MCP tool classes
// only exist for it, and registering them is guarded behind ContainerBuilder::willBeAvailable().
$config->ignoreErrorsOnPackageAndPaths(
    'mcp/sdk',
    [
        __DIR__ . '/src/Infrastructure/Symfony/Mcp',
        __DIR__ . '/tests/Unit/Infrastructure/Symfony/Mcp',
    ],
    [ErrorType::SHADOW_DEPENDENCY],
);

$config->ignoreErrorsOnPackageAndPaths(
    'sulu/mcp-bundle',
    [
        __DIR__ . '/src/Infrastructure/Symfony/Mcp',
        __DIR__ . '/src/Infrastructure/Symfony/HttpKernel/SuluProductBundle.php',
    ],
    [ErrorType::DEV_DEPENDENCY_IN_PROD],
);

return $config;
