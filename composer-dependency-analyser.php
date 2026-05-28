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

require __DIR__ . '/vendor/symfony/dependency-injection/Loader/Configurator/ContainerConfigurator.php'; // see https://github.com/shipmonk-rnd/composer-dependency-analyser/issues/147#issuecomment-2202156380

$config = new Configuration();

$config->addPathRegexesToExclude([
    '#/var/(cache|log)/#',
    '#/vendor/sulu/sulu/#', // sulu/sulu test files are mapped via autoload-dev but are not our code
]);

return $config;
