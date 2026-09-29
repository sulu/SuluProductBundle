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

// "symfony/ai-agent" is an optional require-dev dependency: the AI tool classes reference it,
// but registering the services they need is itself guarded behind
// ContainerBuilder::willBeAvailable(), so the package never has to be installed at runtime.
$config->ignoreErrorsOnPackageAndPaths(
    'symfony/ai-agent',
    [
        __DIR__ . '/src/Infrastructure/Symfony/Ai',
        __DIR__ . '/src/Infrastructure/Symfony/HttpKernel/SuluProductBundle.php',
    ],
    [ErrorType::DEV_DEPENDENCY_IN_PROD],
);

return $config;
