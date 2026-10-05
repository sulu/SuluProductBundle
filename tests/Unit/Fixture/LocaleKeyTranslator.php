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

use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Translates an id to "<locale>:<id>", so a test sees which key was asked for in which locale.
 *
 * @internal
 */
final class LocaleKeyTranslator implements TranslatorInterface
{
    /**
     * @param array<string, mixed> $parameters
     */
    public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
    {
        return $locale . ':' . $id;
    }

    public function getLocale(): string
    {
        return 'en';
    }
}
