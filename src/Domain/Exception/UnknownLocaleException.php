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

namespace Sulu\Product\Domain\Exception;

class UnknownLocaleException extends \RuntimeException
{
    /**
     * @param list<string> $validLocales
     */
    public function __construct(
        string $locale,
        private readonly array $validLocales,
    ) {
        parent::__construct(\sprintf('The locale "%s" is not configured for this installation.', $locale));
    }

    public function getHint(): string
    {
        return \sprintf('Use one of these locales: %s.', \implode(', ', \array_map(static fn (string $locale): string => '"' . $locale . '"', $this->validLocales)));
    }
}
