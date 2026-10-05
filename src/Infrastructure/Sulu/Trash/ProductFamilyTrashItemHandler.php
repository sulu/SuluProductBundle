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

namespace Sulu\Product\Infrastructure\Sulu\Trash;

use Doctrine\ORM\EntityManagerInterface;
use Sulu\Bundle\ActivityBundle\Application\Collector\DomainEventCollectorInterface;
use Sulu\Bundle\MediaBundle\Entity\MediaInterface;
use Sulu\Bundle\TrashBundle\Application\RestoreConfigurationProvider\RestoreConfiguration;
use Sulu\Bundle\TrashBundle\Application\RestoreConfigurationProvider\RestoreConfigurationProviderInterface;
use Sulu\Bundle\TrashBundle\Application\TrashItemHandler\RestoreTrashItemHandlerInterface;
use Sulu\Bundle\TrashBundle\Application\TrashItemHandler\StoreTrashItemHandlerInterface;
use Sulu\Bundle\TrashBundle\Domain\Model\TrashItemInterface;
use Sulu\Bundle\TrashBundle\Domain\Repository\TrashItemRepositoryInterface;
use Sulu\Component\Security\Authentication\UserInterface;
use Sulu\Product\Domain\Event\ProductFamilyRestoredEvent;
use Sulu\Product\Domain\Exception\ProductFamilyKeyNotUniqueException;
use Sulu\Product\Domain\Model\ProductFamilyAttribute;
use Sulu\Product\Domain\Model\ProductFamilyInterface;
use Sulu\Product\Domain\Model\ProductFamilyTranslation;
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;
use Sulu\Product\Domain\Repository\ProductFamilyRepositoryInterface;
use Sulu\Product\Infrastructure\Sulu\Admin\ProductFamilyAdmin;
use Webmozart\Assert\Assert;

/**
 * @phpstan-type ProductFamilyRestoreData array{
 *     key: string,
 *     externalIdentifier: string|null,
 *     defaultLocale: string|null,
 *     imageId: int|null,
 *     created: string,
 *     creatorId: int|null,
 *     translations: list<array{locale: string, name: string, description: string|null}>,
 *     familyAttributes: list<array{attributeUuid: string, required: bool, variantSpecific: bool}>,
 * }
 *
 * @internal
 */
final class ProductFamilyTrashItemHandler implements
    StoreTrashItemHandlerInterface,
    RestoreTrashItemHandlerInterface,
    RestoreConfigurationProviderInterface
{
    public function __construct(
        private TrashItemRepositoryInterface $trashItemRepository,
        private ProductFamilyRepositoryInterface $productFamilyRepository,
        private AttributeRepositoryInterface $attributeRepository,
        private EntityManagerInterface $entityManager,
        private DomainEventCollectorInterface $domainEventCollector,
    ) {
    }

    public static function getResourceKey(): string
    {
        return ProductFamilyInterface::RESOURCE_KEY;
    }

    public function store(object $resource, array $options = []): TrashItemInterface
    {
        Assert::isInstanceOf($resource, ProductFamilyInterface::class);
        $family = $resource;

        $titles = [];
        $translations = [];
        foreach ($family->getTranslations() as $translation) {
            $titles[$translation->getLocale()] = $translation->getName();
            $translations[] = [
                'locale' => $translation->getLocale(),
                'name' => $translation->getName(),
                'description' => $translation->getDescription(),
            ];
        }

        $familyAttributes = [];
        foreach ($family->getFamilyAttributes() as $familyAttribute) {
            $familyAttributes[] = [
                'attributeUuid' => $familyAttribute->getAttribute()->getUuid(),
                'required' => $familyAttribute->isRequired(),
                'variantSpecific' => $familyAttribute->isVariantSpecific(),
            ];
        }

        $data = [
            'key' => $family->getKey(),
            'externalIdentifier' => $family->getExternalIdentifier(),
            'defaultLocale' => $family->getDefaultLocale(),
            'imageId' => $family->getImage()?->getId(),
            'created' => $family->getCreated()->format('c'),
            'creatorId' => $family->getCreator()?->getId(),
            'translations' => $translations,
            'familyAttributes' => $familyAttributes,
        ];

        return $this->trashItemRepository->create(
            ProductFamilyInterface::RESOURCE_KEY,
            $family->getUuid(),
            $titles,
            $data,
            null,
            $options,
            ProductFamilyAdmin::SECURITY_CONTEXT,
            null,
            null,
        );
    }

    public function restore(TrashItemInterface $trashItem, array $restoreFormData = []): object
    {
        /** @var ProductFamilyRestoreData $data */
        $data = $trashItem->getRestoreData();

        if (null !== $this->productFamilyRepository->findOneBy(['key' => $data['key']])) {
            throw new ProductFamilyKeyNotUniqueException($data['key']);
        }

        $family = $this->productFamilyRepository->createNew($trashItem->getResourceId());
        $family->setKey($data['key']);
        $family->setExternalIdentifier($data['externalIdentifier']);
        if (null !== $data['defaultLocale']) {
            $family->setDefaultLocale($data['defaultLocale']);
        }
        $family->setImage(null !== $data['imageId'] ? $this->entityManager->find(MediaInterface::class, $data['imageId']) : null);
        $family->setCreated(new \DateTimeImmutable($data['created']));
        $family->setCreator(null !== $data['creatorId'] ? $this->entityManager->find(UserInterface::class, $data['creatorId']) : null);

        foreach ($data['translations'] as $translationData) {
            $translation = new ProductFamilyTranslation($family, $translationData['locale'], $translationData['name']);
            $translation->setDescription($translationData['description']);
            $family->addTranslation($translation);
        }

        foreach ($data['familyAttributes'] as $familyAttributeData) {
            $attribute = $this->attributeRepository->findOneBy(['uuid' => $familyAttributeData['attributeUuid']]);
            if (null === $attribute) {
                continue;
            }

            $familyAttribute = new ProductFamilyAttribute($family, $attribute);
            $familyAttribute->setRequired($familyAttributeData['required']);
            $familyAttribute->setVariantSpecific($familyAttributeData['variantSpecific']);
            $family->addFamilyAttribute($familyAttribute);
        }

        $this->productFamilyRepository->save($family);

        $this->domainEventCollector->collect(new ProductFamilyRestoredEvent($family, $data));

        return $family;
    }

    public function getConfiguration(): RestoreConfiguration
    {
        return new RestoreConfiguration(null, ProductFamilyAdmin::EDIT_TABS_VIEW, ['uuid' => 'id']);
    }
}
