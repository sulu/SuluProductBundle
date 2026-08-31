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

use Rector\Config\RectorConfig;
use Rector\PHPUnit\PHPUnit100\Rector\Class_\StaticDataProviderClassMethodRector;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\Renaming\Rector\MethodCall\RenameMethodRector;
use Rector\Renaming\ValueObject\MethodCallRename;
use Symfony\Component\Uid\Uuid;

return RectorConfig::configure()
    ->withRootFiles()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withSkipPath('*/tests/Application/var/cache')
    // Config-reference shapes Symfony's DI component dumps on cache warmup.
    ->withSkipPath('*/tests/Application/config/reference.php')
    ->withPHPStanConfigs([
        __DIR__ . '/phpstan.dist.neon',
    ])
    ->withSymfonyContainerXml(__DIR__ . '/tests/Application/var/cache/admin/test/default/Sulu_Product_Tests_Application_KernelTestDebugContainer.xml')
    ->withSets([
        PHPUnitSetList::ANNOTATIONS_TO_ATTRIBUTES,
    ])
    ->withRules([
        StaticDataProviderClassMethodRector::class,
    ])
    ->withConfiguredRule(RenameMethodRector::class, [
        new MethodCallRename(Uuid::class, '__toString', 'toRfc4122'),
    ])
;
