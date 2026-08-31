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

namespace Sulu\Product\Infrastructure\Doctrine\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Domain\Exception\ProductFamilyNotFoundException;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeGroup;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Domain\Model\ProductFamily;
use Sulu\Product\Domain\Model\ProductFamilyAttribute;
use Sulu\Product\Domain\Model\ProductFamilyInterface;
use Sulu\Product\Domain\Repository\ProductFamilyRepositoryInterface;
use Symfony\Component\Uid\Uuid;
use Webmozart\Assert\Assert;

/**
 * @phpstan-import-type ProductFamilyRepositoryFilters from ProductFamilyRepositoryInterface
 * @phpstan-import-type ProductFamilyRepositorySelects from ProductFamilyRepositoryInterface
 */
final class ProductFamilyRepository implements ProductFamilyRepositoryInterface
{
    private const SELECTS = [
        // GROUPS
        self::GROUP_SELECT_PRODUCT_FAMILY_FORM => [
            self::SELECT_FAMILY_ATTRIBUTES => true,
            self::SELECT_FAMILY_ATTRIBUTE_GROUPS => true,
            self::SELECT_FAMILY_ATTRIBUTE_OPTIONS => true,
        ],
    ];

    /** @var EntityRepository<ProductFamilyInterface> */
    private EntityRepository $entityRepository;

    public function __construct(private EntityManagerInterface $entityManager)
    {
        /** @var EntityRepository<ProductFamilyInterface> $repo */
        $repo = $this->entityManager->getRepository(ProductFamily::class);
        $this->entityRepository = $repo;
    }

    public function create(): ProductFamilyInterface
    {
        $family = new ProductFamily();
        $family->setUuid(Uuid::v7()->toRfc4122());

        return $family;
    }

    public function findOneBy(array $filters, array $selects = []): ?ProductFamilyInterface
    {
        $queryBuilder = $this->createQueryBuilder($filters, $selects);

        try {
            /** @var ProductFamilyInterface $family */
            $family = $queryBuilder->getQuery()->getSingleResult();
        } catch (NoResultException) {
            return null;
        }

        $this->addRowMultiplyingSelects([$family], $selects);

        return $family;
    }

    public function getOneBy(array $filters, array $selects = []): ProductFamilyInterface
    {
        $queryBuilder = $this->createQueryBuilder($filters, $selects);

        try {
            /** @var ProductFamilyInterface $family */
            $family = $queryBuilder->getQuery()->getSingleResult();
        } catch (NoResultException $e) {
            throw new ProductFamilyNotFoundException($filters, $e);
        }

        $this->addRowMultiplyingSelects([$family], $selects);

        return $family;
    }

    public function findBy(array $filters = [], array $selects = []): iterable
    {
        /** @var list<ProductFamilyInterface> $families */
        $families = $this->createQueryBuilder($filters, $selects)
            ->getQuery()
            ->getResult();

        $this->addRowMultiplyingSelects($families, $selects);

        return $families;
    }

    /**
     * @param ProductFamilyRepositoryFilters $filters
     * @param ProductFamilyRepositorySelects $selects
     */
    public function createQueryBuilder(array $filters, array $selects = []): QueryBuilder
    {
        $selects = $this->resolveSelectGroups($selects);

        $queryBuilder = $this->entityRepository->createQueryBuilder('productFamily');

        $uuid = $filters['uuid'] ?? null;
        if (null !== $uuid) {
            Assert::string($uuid); // @phpstan-ignore staticMethod.alreadyNarrowedType
            $queryBuilder->andWhere('productFamily.uuid = :uuid')
                ->setParameter('uuid', $uuid);
        }

        $uuids = $filters['uuids'] ?? null;
        if (null !== $uuids) {
            Assert::isArray($uuids); // @phpstan-ignore staticMethod.alreadyNarrowedType
            $queryBuilder->andWhere('productFamily.uuid IN(:uuids)')
                ->setParameter('uuids', $uuids);
        }

        $externalIdentifier = $filters['externalIdentifier'] ?? null;
        if (null !== $externalIdentifier) {
            Assert::string($externalIdentifier); // @phpstan-ignore staticMethod.alreadyNarrowedType
            $queryBuilder->andWhere('productFamily.externalIdentifier = :externalIdentifier')
                ->setParameter('externalIdentifier', $externalIdentifier);
        }

        $productUuid = $filters['productUuid'] ?? null;
        if (null !== $productUuid) {
            Assert::string($productUuid); // @phpstan-ignore staticMethod.alreadyNarrowedType
            $queryBuilder
                ->innerJoin(
                    ProductDimensionContentInterface::class,
                    'productDimensionContent',
                    Join::WITH,
                    'productDimensionContent.productFamily = productFamily',
                )
                ->andWhere('productDimensionContent.locale IS NULL')
                ->andWhere('productDimensionContent.stage = :stage')
                ->andWhere('productDimensionContent.version = :version')
                ->andWhere('IDENTITY(productDimensionContent.product) = :productUuid')
                ->setParameter('stage', DimensionContentInterface::STAGE_DRAFT)
                ->setParameter('version', DimensionContentInterface::CURRENT_VERSION)
                ->setParameter('productUuid', $productUuid);
        }

        // selects
        if ($selects[self::SELECT_FAMILY_ATTRIBUTES] ?? false) {
            // Translations stay unfiltered, callers fall back to the default locale.
            $queryBuilder
                ->addSelect('familyAttribute', 'attribute', 'attributeTranslation')
                ->leftJoin('productFamily.familyAttributes', 'familyAttribute')
                ->leftJoin('familyAttribute.attribute', 'attribute')
                ->leftJoin('attribute.translations', 'attributeTranslation');
        }

        if ($selects[self::SELECT_FAMILY_ATTRIBUTE_GROUPS] ?? false) {
            Assert::notFalse($selects[self::SELECT_FAMILY_ATTRIBUTES] ?? false);

            $queryBuilder
                ->addSelect('attributeGroup')
                ->leftJoin('attribute.group', 'attributeGroup');
        }

        return $queryBuilder;
    }

    /**
     * Own queries, joined they would multiply the rows of the main query. Hydrating them fills the
     * collections of the loaded entities, the results themselves are not needed.
     *
     * @param list<ProductFamilyInterface> $families
     * @param ProductFamilyRepositorySelects $selects
     */
    private function addRowMultiplyingSelects(array $families, array $selects): void
    {
        if ([] === $families) {
            return;
        }

        $selects = $this->resolveSelectGroups($selects);

        if ($selects[self::SELECT_FAMILY_ATTRIBUTE_GROUPS] ?? false) {
            $this->entityManager->createQueryBuilder()
                ->select('attributeGroup', 'attributeGroupTranslation')
                ->from(AttributeGroup::class, 'attributeGroup')
                ->innerJoin(
                    Attribute::class,
                    'attribute',
                    Join::WITH,
                    'attribute.group = attributeGroup',
                )
                ->innerJoin(
                    ProductFamilyAttribute::class,
                    'familyAttribute',
                    Join::WITH,
                    'familyAttribute.attribute = attribute',
                )
                ->leftJoin('attributeGroup.translations', 'attributeGroupTranslation')
                ->where('familyAttribute.family IN (:families)')
                ->setParameter('families', $families)
                ->getQuery()
                ->getResult();
        }

        if ($selects[self::SELECT_FAMILY_ATTRIBUTE_OPTIONS] ?? false) {
            $this->entityManager->createQueryBuilder()
                ->select('attribute', 'option', 'optionTranslation')
                ->from(Attribute::class, 'attribute')
                ->innerJoin(
                    ProductFamilyAttribute::class,
                    'familyAttribute',
                    Join::WITH,
                    'familyAttribute.attribute = attribute',
                )
                ->leftJoin('attribute.options', 'option')
                ->leftJoin('option.translations', 'optionTranslation')
                ->where('familyAttribute.family IN (:families)')
                ->setParameter('families', $families)
                ->getQuery()
                ->getResult();
        }
    }

    /**
     * @param ProductFamilyRepositorySelects $selects
     *
     * @return ProductFamilyRepositorySelects
     */
    private function resolveSelectGroups(array $selects): array
    {
        foreach ($selects as $selectGroup => $value) {
            if (!$value || !isset(self::SELECTS[$selectGroup])) {
                continue;
            }

            foreach (self::SELECTS[$selectGroup] as $select => $selectValue) {
                $selects[$select] = $selectValue;
            }
        }

        return $selects;
    }

    public function save(ProductFamilyInterface $family): void
    {
        $this->entityManager->persist($family);
    }

    public function remove(ProductFamilyInterface $family): void
    {
        $this->entityManager->remove($family);
    }
}
