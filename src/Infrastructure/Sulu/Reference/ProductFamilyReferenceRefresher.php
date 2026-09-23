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

namespace Sulu\Product\Infrastructure\Sulu\Reference;

use Sulu\Bundle\MediaBundle\Entity\MediaInterface;
use Sulu\Bundle\ReferenceBundle\Application\Collector\ReferenceCollector;
use Sulu\Bundle\ReferenceBundle\Application\Refresh\ReferenceRefresherInterface;
use Sulu\Bundle\ReferenceBundle\Domain\Repository\ReferenceRepositoryInterface;
use Sulu\Component\Localization\Manager\LocalizationManagerInterface;
use Sulu\Product\Domain\Model\ProductFamilyInterface;
use Sulu\Product\Domain\Repository\ProductFamilyRepositoryInterface;

/**
 * References the family image once per admin locale, as reference lists filter by locale.
 *
 * @internal
 */
class ProductFamilyReferenceRefresher implements ReferenceRefresherInterface
{
    public function __construct(
        private readonly ProductFamilyRepositoryInterface $productFamilyRepository,
        private readonly ReferenceRepositoryInterface $referenceRepository,
        private readonly LocalizationManagerInterface $localizationManager,
    ) {
    }

    public static function getResourceKey(): string
    {
        return ProductFamilyInterface::RESOURCE_KEY;
    }

    /**
     * A family has no locale or stage, so a filter refreshes all references of its resource.
     */
    public function refresh(?array $filter = null): \Generator
    {
        $filters = null !== $filter ? ['uuid' => $filter['resourceId']] : [];

        foreach ($this->productFamilyRepository->findBy($filters) as $family) {
            $this->refreshFamily($family);

            yield $family;
        }
    }

    private function refreshFamily(ProductFamilyInterface $family): void
    {
        $uuid = (string) $family->getUuid();
        $image = $family->getImage();
        $defaultName = $family->getTranslation((string) $family->getDefaultLocale())?->getName() ?? '';

        foreach ($this->localizationManager->getLocales() as $locale) {
            $referenceCollector = new ReferenceCollector(
                referenceRepository: $this->referenceRepository,
                referenceResourceKey: ProductFamilyInterface::RESOURCE_KEY,
                referenceResourceId: $uuid,
                referenceLocale: $locale,
                referenceTitle: $family->getTranslation($locale)?->getName() ?? $defaultName,
                referenceContext: '',
                referenceRouterAttributes: ['locale' => $locale],
            );

            if ($image instanceof MediaInterface) {
                $referenceCollector->addReference(MediaInterface::RESOURCE_KEY, (string) $image->getId(), 'image');
            }

            $referenceCollector->persistReferences();
        }
    }
}
