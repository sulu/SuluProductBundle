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

namespace Sulu\Product\Infrastructure\Sulu\Admin;

use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManagerInterface;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadataVisitorInterface;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\OptionMetadata;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;

/**
 * Keeps the main category selected in the excerpt categories of a product: the selection disables it
 * and does not allow to deselect it. The server keeps it there as well, this only shows it.
 *
 * @internal
 */
class ProductExcerptFormMetadataVisitor implements FormMetadataVisitorInterface
{
    private const FORM_KEY = 'content_excerpt';

    private const FIELD_NAME = 'excerptCategories';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function visitFormMetadata(FormMetadata $formMetadata, string $locale, array $metadataOptions = []): void
    {
        $id = $metadataOptions['id'] ?? null;
        if (self::FORM_KEY !== $formMetadata->getKey() || !\is_string($id)) {
            return;
        }

        $field = $formMetadata->getItems()[self::FIELD_NAME] ?? null;
        if (!$field instanceof FieldMetadata) {
            return;
        }

        // The admin keeps cacheable metadata for the whole session, so a changed main category would not show up.
        $formMetadata->setCacheable(false);

        $contentLocale = $metadataOptions['locale'] ?? $locale;
        $mainCategoryId = \is_string($contentLocale) ? $this->findMainCategoryId($id, $contentLocale) : null;
        if (null === $mainCategoryId) {
            return;
        }

        $this->addOption($field, 'item_disabled_condition', 'id == ' . $mainCategoryId);
        $this->addOption($field, 'allow_deselect_for_disabled_items', false);
    }

    private function findMainCategoryId(string $productUuid, string $locale): ?int
    {
        /** @var int|string|null $mainCategoryId */
        $mainCategoryId = $this->entityManager->createQueryBuilder()
            ->select('IDENTITY(dimensionContent.mainCategory)')
            ->from(ProductDimensionContentInterface::class, 'dimensionContent')
            ->innerJoin('dimensionContent.product', 'product')
            ->where('product.uuid = :uuid')
            ->andWhere('dimensionContent.stage = :stage')
            ->andWhere('dimensionContent.locale = :locale')
            ->setParameter('uuid', $productUuid)
            ->setParameter('stage', DimensionContentInterface::STAGE_DRAFT)
            ->setParameter('locale', $locale)
            ->getQuery()
            ->getOneOrNullResult(AbstractQuery::HYDRATE_SINGLE_SCALAR);

        return null === $mainCategoryId ? null : (int) $mainCategoryId;
    }

    private function addOption(FieldMetadata $field, string $name, bool|string $value): void
    {
        $option = new OptionMetadata();
        $option->setName($name);
        $option->setValue($value);

        $field->addOption($option);
    }
}
