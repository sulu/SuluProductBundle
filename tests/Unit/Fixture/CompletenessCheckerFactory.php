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

namespace Sulu\Product\Tests\Unit\Fixture;

use Prophecy\Prophet;
use Sulu\Bundle\AdminBundle\Metadata\MetadataProviderInterface;
use Sulu\Component\Localization\Localization;
use Sulu\Component\Webspace\Manager\WebspaceCollection;
use Sulu\Component\Webspace\Manager\WebspaceManagerInterface;
use Sulu\Component\Webspace\Webspace;
use Sulu\Product\Application\Mcp\ProductCompletenessChecker;

/**
 * @internal
 */
final class CompletenessCheckerFactory
{
    /**
     * @param list<string> $projectLocales
     */
    public static function create(?MetadataProviderInterface $metadataProvider = null, array $projectLocales = ['en']): ProductCompletenessChecker
    {
        $webspace = new Webspace();
        $webspace->setKey('website');
        foreach ($projectLocales as $locale) {
            $localization = new Localization($locale);
            $webspace->addLocalization($localization);
        }

        $webspaceManager = (new Prophet())->prophesize(WebspaceManagerInterface::class);
        $webspaceManager->getWebspaceCollection()->willReturn(new WebspaceCollection(['website' => $webspace]));

        return new ProductCompletenessChecker($metadataProvider ?? new ArrayMetadataProvider(), $webspaceManager->reveal(), ProductUrlHelperFactory::create());
    }
}
