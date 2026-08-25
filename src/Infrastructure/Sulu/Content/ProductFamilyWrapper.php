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

namespace Sulu\Product\Infrastructure\Sulu\Content;

use Sulu\Product\Domain\Model\ProductFamilyInterface;

/**
 * A family as a template sees it: one locale, no entity graph.
 */
final readonly class ProductFamilyWrapper
{
    public function __construct(
        private ProductFamilyInterface $family,
        private ?string $locale,
    ) {
    }

    public function getId(): int
    {
        return $this->family->getId();
    }

    public function getUuid(): ?string
    {
        return $this->family->getUuid();
    }

    public function getExternalIdentifier(): ?string
    {
        return $this->family->getExternalIdentifier();
    }

    public function getName(): ?string
    {
        if (null === $this->locale) {
            return null;
        }

        return $this->family->getTranslation($this->locale)?->getName();
    }
}
