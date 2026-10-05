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
use Sulu\Bundle\TrashBundle\Application\RestoreConfigurationProvider\RestoreConfiguration;
use Sulu\Bundle\TrashBundle\Application\RestoreConfigurationProvider\RestoreConfigurationProviderInterface;
use Sulu\Bundle\TrashBundle\Application\TrashItemHandler\RestoreTrashItemHandlerInterface;
use Sulu\Bundle\TrashBundle\Application\TrashItemHandler\StoreTrashItemHandlerInterface;
use Sulu\Bundle\TrashBundle\Domain\Model\TrashItemInterface;
use Sulu\Bundle\TrashBundle\Domain\Repository\TrashItemRepositoryInterface;
use Sulu\Component\Security\Authentication\UserInterface;
use Sulu\Product\Domain\Event\AttributeRestoredEvent;
use Sulu\Product\Domain\Exception\AttributeGroupNotFoundException;
use Sulu\Product\Domain\Exception\AttributeKeyNotUniqueException;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\AttributeOption;
use Sulu\Product\Domain\Model\AttributeOptionTranslation;
use Sulu\Product\Domain\Model\AttributeTranslation;
use Sulu\Product\Domain\Model\ProductFamilyAttribute;
use Sulu\Product\Domain\Repository\AttributeGroupRepositoryInterface;
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;
use Sulu\Product\Domain\Repository\ProductFamilyRepositoryInterface;
use Sulu\Product\Infrastructure\Sulu\Admin\AttributeAdmin;
use Webmozart\Assert\Assert;

/**
 * Product attribute values are removed with the attribute by the database and are not restored.
 *
 * @phpstan-type AttributeRestoreData array{
 *     key: string,
 *     type: string,
 *     config: array<string, mixed>,
 *     position: int,
 *     localized: bool,
 *     filterable: bool,
 *     externalIdentifier: string|null,
 *     defaultLocale: string|null,
 *     groupUuid: string|null,
 *     created: string,
 *     creatorId: int|null,
 *     translations: list<array{locale: string, name: string, description: string|null}>,
 *     options: list<array{uuid: string, key: string, position: int, translations: list<array{locale: string, name: string}>}>,
 *     familyAttributes: list<array{familyUuid: string, required: bool, variantSpecific: bool}>,
 * }
 *
 * @internal
 */
final class AttributeTrashItemHandler implements
    StoreTrashItemHandlerInterface,
    RestoreTrashItemHandlerInterface,
    RestoreConfigurationProviderInterface
{
    public function __construct(
        private TrashItemRepositoryInterface $trashItemRepository,
        private AttributeRepositoryInterface $attributeRepository,
        private AttributeGroupRepositoryInterface $attributeGroupRepository,
        private ProductFamilyRepositoryInterface $productFamilyRepository,
        private EntityManagerInterface $entityManager,
        private DomainEventCollectorInterface $domainEventCollector,
    ) {
    }

    public static function getResourceKey(): string
    {
        return AttributeInterface::RESOURCE_KEY;
    }

    public function store(object $resource, array $options = []): TrashItemInterface
    {
        Assert::isInstanceOf($resource, AttributeInterface::class);
        $attribute = $resource;

        $titles = [];
        $translations = [];
        foreach ($attribute->getTranslations() as $translation) {
            $titles[$translation->getLocale()] = $translation->getName();
            $translations[] = [
                'locale' => $translation->getLocale(),
                'name' => $translation->getName(),
                'description' => $translation->getDescription(),
            ];
        }

        $attributeOptions = [];
        foreach ($attribute->getOptions() as $option) {
            $optionTranslations = [];
            foreach ($option->getTranslations() as $optionTranslation) {
                $optionTranslations[] = [
                    'locale' => $optionTranslation->getLocale(),
                    'name' => $optionTranslation->getName(),
                ];
            }

            $attributeOptions[] = [
                'uuid' => $option->getUuid(),
                'key' => $option->getKey(),
                'position' => $option->getPosition(),
                'translations' => $optionTranslations,
            ];
        }

        // The database removes these links with the attribute. Scalar results keep them out of the
        // unit of work, where they would still point at the removed attribute.
        /** @var list<array{familyUuid: string, required: bool, variantSpecific: bool}> $familyAttributes */
        $familyAttributes = $this->entityManager->createQueryBuilder()
            ->select('family.uuid AS familyUuid', 'familyAttribute.required AS required', 'familyAttribute.variantSpecific AS variantSpecific')
            ->from(ProductFamilyAttribute::class, 'familyAttribute')
            ->innerJoin('familyAttribute.family', 'family')
            ->where('familyAttribute.attribute = :attribute')
            ->setParameter('attribute', $attribute)
            ->getQuery()
            ->getArrayResult();

        $data = [
            'key' => $attribute->getKey(),
            'type' => $attribute->getType(),
            'config' => $attribute->getConfig(),
            'position' => $attribute->getPosition(),
            'localized' => $attribute->isLocalized(),
            'filterable' => $attribute->isFilterable(),
            'externalIdentifier' => $attribute->getExternalIdentifier(),
            'defaultLocale' => $attribute->getDefaultLocale(),
            'groupUuid' => $attribute->getGroup()->getUuid(),
            'created' => $attribute->getCreated()->format('c'),
            'creatorId' => $attribute->getCreator()?->getId(),
            'translations' => $translations,
            'options' => $attributeOptions,
            'familyAttributes' => $familyAttributes,
        ];

        return $this->trashItemRepository->create(
            AttributeInterface::RESOURCE_KEY,
            $attribute->getUuid(),
            $titles,
            $data,
            null,
            $options,
            AttributeAdmin::SECURITY_CONTEXT,
            null,
            null,
        );
    }

    public function restore(TrashItemInterface $trashItem, array $restoreFormData = []): object
    {
        /** @var AttributeRestoreData $data */
        $data = $trashItem->getRestoreData();

        if (null !== $this->attributeRepository->findOneBy(['key' => $data['key']])) {
            throw new AttributeKeyNotUniqueException($data['key']);
        }

        $group = null !== $data['groupUuid'] ? $this->attributeGroupRepository->findOneBy(['uuid' => $data['groupUuid']]) : null;
        if (null === $group) {
            throw new AttributeGroupNotFoundException(['uuid' => $data['groupUuid']]);
        }

        $attribute = $this->attributeRepository->createNew($group, $trashItem->getResourceId());
        $attribute->setKey($data['key']);
        $attribute->setType($data['type']);
        $attribute->setConfig($data['config']);
        $attribute->setLocalized($data['localized']);
        $attribute->setFilterable($data['filterable']);
        $attribute->setExternalIdentifier($data['externalIdentifier']);
        if (null !== $data['defaultLocale']) {
            $attribute->setDefaultLocale($data['defaultLocale']);
        }
        $attribute->setCreated(new \DateTimeImmutable($data['created']));
        $attribute->setCreator(null !== $data['creatorId'] ? $this->entityManager->find(UserInterface::class, $data['creatorId']) : null);

        // Takes its old position back, the attributes from there on move down by one.
        foreach ($this->attributeRepository->findByGroupWithPositionAtLeast($group, $data['position']) as $other) {
            $other->setPosition($other->getPosition() + 1);
        }
        $attribute->setPosition($data['position']);

        foreach ($data['translations'] as $translationData) {
            $translation = new AttributeTranslation($attribute, $translationData['locale'], $translationData['name']);
            $translation->setDescription($translationData['description']);
            $attribute->addTranslation($translation);
        }

        foreach ($data['options'] as $optionData) {
            $option = new AttributeOption($attribute, $optionData['key'], $optionData['uuid']);
            $option->setPosition($optionData['position']);
            foreach ($optionData['translations'] as $optionTranslationData) {
                $option->addTranslation(
                    new AttributeOptionTranslation($option, $optionTranslationData['locale'], $optionTranslationData['name']),
                );
            }
            $attribute->addOption($option);
        }

        foreach ($data['familyAttributes'] as $familyAttributeData) {
            $family = $this->productFamilyRepository->findOneBy(['uuid' => $familyAttributeData['familyUuid']]);
            if (null === $family) {
                continue;
            }

            $familyAttribute = new ProductFamilyAttribute($family, $attribute);
            $familyAttribute->setRequired($familyAttributeData['required']);
            $familyAttribute->setVariantSpecific($familyAttributeData['variantSpecific']);
            $family->addFamilyAttribute($familyAttribute);
        }

        $this->attributeRepository->save($attribute);

        $this->domainEventCollector->collect(new AttributeRestoredEvent($attribute, $data));

        return $attribute;
    }

    public function getConfiguration(): RestoreConfiguration
    {
        return new RestoreConfiguration(null, AttributeAdmin::EDIT_TABS_VIEW, ['uuid' => 'id']);
    }
}
