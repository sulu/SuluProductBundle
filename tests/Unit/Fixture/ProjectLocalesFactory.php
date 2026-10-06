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
use Sulu\Component\Localization\Localization;
use Sulu\Component\Webspace\Manager\WebspaceCollection;
use Sulu\Component\Webspace\Manager\WebspaceManagerInterface;
use Sulu\Component\Webspace\Webspace;
use Sulu\Product\Application\Mcp\ProjectLocales;

/**
 * @internal
 */
final class ProjectLocalesFactory
{
    /**
     * @param list<string> $locales
     */
    public static function create(array $locales = ['en', 'de']): ProjectLocales
    {
        $webspace = new Webspace();
        $webspace->setKey('website');
        foreach ($locales as $locale) {
            $webspace->addLocalization(new Localization($locale));
        }

        $webspaceManager = (new Prophet())->prophesize(WebspaceManagerInterface::class);
        $webspaceManager->getWebspaceCollection()->willReturn(new WebspaceCollection(['website' => $webspace]));

        return new ProjectLocales($webspaceManager->reveal());
    }
}
