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

namespace Sulu\Product\Infrastructure\Sulu\Content\Select;

use Doctrine\ORM\EntityManagerInterface;
use Sulu\Product\Domain\Model\Attribute;

final class AttributeSelectService
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /**
     * @return array<int, array{name: string, title: string}>
     */
    public function getValues(string $locale): array
    {
        $attributes = $this->entityManager->getRepository(Attribute::class)->findAll();

        $values = [];
        foreach ($attributes as $attribute) {
            $uuid = (string) $attribute->getUuid();
            $translation = $attribute->getTranslation($locale);
            $values[] = [
                'name' => $uuid,
                'title' => $translation?->getName() ?? $uuid,
            ];
        }

        return $values;
    }
}
