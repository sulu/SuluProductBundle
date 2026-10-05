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

use Sulu\Component\Webspace\Manager\WebspaceManagerInterface;
use Sulu\Product\Domain\Exception\UnknownLocaleException;

/**
 * The locales of all webspaces. A locale outside of them has no admin UI and no website, so content
 * saved in it can never be shown or edited.
 *
 * @internal
 */
final readonly class ProjectLocales
{
    public function __construct(
        private WebspaceManagerInterface $webspaceManager,
    ) {
    }

    /**
     * @throws UnknownLocaleException
     */
    public function assertExists(string $locale): void
    {
        $locales = [];
        foreach ($this->webspaceManager->getWebspaceCollection()->getWebspaces() as $webspace) {
            foreach ($webspace->getAllLocalizations() as $localization) {
                $locales[$localization->getLocale()] = true;
            }
        }

        if (!isset($locales[$locale])) {
            throw new UnknownLocaleException($locale, \array_map('strval', \array_keys($locales)));
        }
    }
}
